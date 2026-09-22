/*
 |-----------------------------------------------------------------------------
 | Real-application server for the listing-preference browser specs
 |-----------------------------------------------------------------------------
 |
 | WHY THIS EXISTS ALONGSIDE static-server.js
 | ------------------------------------------
 | The static fixture server was built for the Location DNA renderer, where the
 | risk lives in JavaScript and a booted Laravel would only be overhead. The
 | listing-preference risk is the opposite shape: the interesting failures are
 | persistence, authentication, CSRF and the feature flag, none of which a static
 | fixture can have. So these specs drive the REAL app — the real Blade component
 | on the real /offer-listing/seller/view page, posting through the real `web`
 | middleware to the real controller and database.
 |
 | ISOLATION IS THE WHOLE POINT
 | ----------------------------
 | On this host the ambient shell is production (DATABASE_URL, PGHOST=helium,
 | APP_ENV=production). This script therefore builds its own environment from
 | scratch rather than inheriting one:
 |
 |   DATABASE_URL, PGHOST, PGDATABASE  blanked, so config/database.php cannot
 |                                     resolve the production connection
 |   DB_CONNECTION=sqlite              against a throwaway file under storage/
 |   APP_ENV=local                     never `production`
 |
 | The seeder it runs additionally refuses on any production signal of its own
 | (ProductionDatabaseRefused), so isolation is asserted twice by two mechanisms.
 |
 | It migrates, seeds one buyer and one published listing, turns the feature flag
 | ON for this process only, and serves. Nothing here touches the developer's
 | .env or any shared database.
 */

const { spawn, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../../..');
const PORT = Number(process.env.LP_APP_PORT || 8932);

/*
 | The harness runs TWO instances: one with the feature on (the interaction
 | specs) and one with it off (the flag spec). They must not share a database —
 | two `php artisan serve` processes writing one SQLite file is a lock fight, and
 | the off instance re-seeding would reset the on instance's fixture mid-run.
 */
const FLAG   = process.env.LP_FEATURE_ENABLED === 'false' ? 'false' : 'true';
const SUFFIX = process.env.LP_DB_SUFFIX || (FLAG === 'false' ? '-off' : '');
const DB     = path.join(ROOT, `storage/app/lp-browser${SUFFIX}.sqlite`);
const FIXTURE = path.join(ROOT, `storage/app/lp-browser-fixture${SUFFIX}.json`);

/** An environment with every production pointer removed. */
function isolatedEnv(extra = {}) {
    return {
        ...process.env,
        DATABASE_URL: '',
        PGHOST: '',
        PGDATABASE: '',
        PGUSER: '',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB,
        APP_ENV: 'local',
        APP_DEBUG: 'true',
        // A FIXED, PUBLIC, THROWAWAY key. Sessions and CSRF need one, and this
        // worktree has no .env. It is deliberately hard-coded and deliberately
        // worthless: it encrypts one disposable SQLite file for the length of a
        // test run. It must never appear in any real environment — the harness
        // is the only thing that sets it.
        APP_KEY: 'base64:bHAtYnJvd3Nlci1zdWl0ZS10aHJvd2F3YXkta2V5MzI=',
        // The feature under test. The shipped default is false; this instance
        // sets it explicitly so BOTH postures are exercised by a real server
        // rather than by a config poke.
        LISTING_PREFERENCES_ENABLED: FLAG,
        // Phase 4 — "Your Home Taste" follows the same posture: on for the
        // interaction instance, off (absent both gates) for the flag instance.
        LISTING_PREFERENCE_TASTE_DNA_ENABLED: FLAG,
        // No credential may reach a provider from a browser run.
        GOOGLE_PLACES_API_KEY: '',
        GOOGLE_PLACES_ENABLED: 'false',
        GOOGLE_MAPS_BROWSER_ENABLED: 'false',
        GOOGLE_MAPS_BROWSER_KEY: '',
        /*
         | THE VIRTUAL DRIVE PROOF PAGE, WITH GOOGLE OFF.
         |
         | The proof is enabled (APP_ENV=local is on its allow-list) so the
         | Phase 3B spec can drive the real page. Google is switched OFF, keyless
         | and given a zero daily ceiling — three independent refusals — so this
         | harness cannot construct a panorama or claim a launch however a spec
         | behaves. Apple's MapKit token is a worthless placeholder: the spec
         | fulfils the MapKit script from the repository's fake, and the network
         | guard aborts anything that would otherwise leave the origin.
         */
        VIRTUAL_DRIVE_PROOF_ENABLED: 'true',
        VIRTUAL_DRIVE_GOOGLE_ENABLED: 'false',
        VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY: '',
        VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT: '0',
        VIRTUAL_DRIVE_MAPKIT_JS_TOKEN: 'harness-placeholder-not-a-token',
        VIRTUAL_DRIVE_TEST_LISTING_KEYS: 'LP-VD-HOME-A,LP-VD-HOME-B',
        VIRTUAL_DRIVE_DEFAULT_LISTING_KEY: 'LP-VD-HOME-A',
        LP_FIXTURE_PATH: FIXTURE,
        ...extra,
    };
}

function artisan(args, label) {
    const result = spawnSync('php', ['artisan', ...args], {
        cwd: ROOT,
        env: isolatedEnv(),
        encoding: 'utf8',
    });

    if (result.status !== 0) {
        process.stderr.write(`[app-server] ${label} failed\n${result.stdout || ''}${result.stderr || ''}\n`);
        process.exit(1);
    }

    return result.stdout || '';
}

// A fresh database every run: the specs assert on counts and on "no preference
// yet", which a reused file would quietly falsify.
fs.mkdirSync(path.dirname(DB), { recursive: true });
if (fs.existsSync(DB)) { fs.unlinkSync(DB); }
fs.writeFileSync(DB, '');

process.stderr.write('[app-server] migrating throwaway sqlite…\n');
artisan(['migrate', '--force'], 'migrate');

process.stderr.write('[app-server] seeding fixture…\n');
artisan(['db:seed', '--class=ListingPreferenceBrowserTestSeeder', '--force'], 'seed');

/*
 | No config:clear here, deliberately. It rewrites bootstrap/cache/config.php,
 | which BOTH instances share — two concurrent artisan processes racing on one
 | cache file is how the harness failed to start. There is no cached config in a
 | test worktree to clear anyway.
 */

process.stderr.write(`[app-server] serving on 127.0.0.1:${PORT} (feature ${FLAG})\n`);

/*
 | `--no-reload` IS REQUIRED, NOT A PREFERENCE.
 |
 | Laravel's ServeCommand re-execs its worker through a reloader that rebuilds
 | the child environment from an ALLOWLIST, dropping everything not on it. APP_ENV
 | survives; APP_KEY, APP_DEBUG and the DB_* variables do not — so the worker
 | booted with no encryption key and no database pointer, and every page 500'd
 | with "No application encryption key has been specified" while the parent
 | process had one all along.
 |
 | This repository already knows that failure: it is why deploy/start-serving.sh
 | passes --no-reload and why ServeWorkerRuntimeEnvironmentTest exists.
 */
const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`, '--no-reload'], {
    cwd: ROOT,
    env: isolatedEnv(),
    stdio: ['ignore', 'inherit', 'inherit'],
    /*
     | THIS PROCESS'S GROUP, DELIBERATELY — there is no `detached` here, and
     | adding one back reintroduces a teardown hang.
     |
     | Playwright tears a `webServer` down by SIGKILLing its PROCESS GROUP.
     | SIGKILL cannot be caught, so nothing below this line runs at teardown:
     | the handlers are for a developer's Ctrl-C, never for Playwright. A
     | detached child is therefore not a tree we get to signal — it is a tree
     | Playwright's kill CANNOT REACH, and it survived every run.
     |
     | That orphan then hung the whole suite. `inherit` hands the child THIS
     | process's stdout and stderr, which are Playwright's own pipes; Playwright
     | waits for those streams to close before finishing, and a surviving
     | orphan holds them open forever. Every test reported green and the run
     | never exited — twice observed, once per server instance.
     |
     | Inside this group, `php artisan serve` AND the `php -S` child that
     | actually holds the port are reaped by the same SIGKILL that reaps this
     | process, so the port is free for the next run and the streams close.
     */
});

/*
 | NEVER a negative pid here. The child shares THIS process's group now, so
 | `process.kill(-server.pid, …)` would signal Playwright's own wrapper and this
 | process along with it. Signal the child by its own pid; its `php -S` child is
 | in the same group and dies with the group whenever the group is signalled —
 | Playwright's teardown, or a terminal's Ctrl-C, which goes to the whole
 | foreground group.
 */
const stop = () => {
    try { server.kill('SIGTERM'); } catch (e) { /* already gone */ }
};

/*
 | Exit PROMPTLY once the child is signalled, for the Ctrl-C path. Playwright
 | waits for this process to end before finishing the run, so lingering here
 | would hang the suite after every test had already reported.
 */
const stopAndExit = () => { stop(); process.exit(0); };

process.on('SIGTERM', stopAndExit);
process.on('SIGINT', stopAndExit);
process.on('exit', stop);

server.on('exit', (code) => process.exit(code ?? 0));
