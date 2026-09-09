/*
 |-----------------------------------------------------------------------------
 | Static fixture server for the browser suite
 |-----------------------------------------------------------------------------
 |
 | Serves three roots on one origin, and nothing else:
 |
 |   /                -> tests/browser/fixtures      the harness pages
 |   /js/spatial/     -> resources/js/spatial        the renderer, as written
 |   /vendor/         -> node_modules                maplibre-gl + pmtiles UMD
 |
 | WHY A SERVER AND NOT file://
 | ----------------------------
 | ES modules, workers and fetch all behave differently — or refuse outright —
 | under file://, and a suite whose job is to prove the real renderer works must
 | not exercise it through a loading mechanism production never uses.
 |
 | WHY IT SERVES THE RENDERER SOURCE DIRECTLY
 | ------------------------------------------
 | No build step. `npm install` cannot complete in this environment (the package
 | firewall blocks websocket-driver@0.7.4, a pre-existing laravel-mix dependency),
 | so Laravel Mix cannot run here. Serving `resources/js/spatial/*.js` as ES
 | modules means the suite exercises exactly the source that ships, rather than a
 | bundled artefact it cannot currently produce. The bundling path is covered
 | separately by webpack.mix.js and is explicitly UNVERIFIED — see the report.
 |
 | It binds 127.0.0.1 only. Nothing here should be reachable off the machine.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.env.LDNA_FIXTURE_PORT || 8931);
const ROOT = path.resolve(__dirname, '../../..');

const MOUNTS = [
    { prefix: '/js/spatial/', dir: path.join(ROOT, 'resources/js/spatial') },
    { prefix: '/vendor/', dir: path.join(ROOT, 'node_modules') },
    { prefix: '/', dir: path.join(__dirname, '../fixtures') },
];

const TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.map': 'application/json; charset=utf-8',
};

/**
 * Resolve a URL path to a file inside one of the mounts.
 *
 * Every candidate is re-resolved and re-checked against its mount root, so a
 * `..` sequence cannot escape into the rest of the repository.
 */
function resolve(urlPath) {
    const decoded = decodeURIComponent(urlPath.split('?')[0]);

    for (const mount of MOUNTS) {
        if (!decoded.startsWith(mount.prefix)) {
            continue;
        }

        const relative = decoded.slice(mount.prefix.length) || 'index.html';
        const candidate = path.resolve(mount.dir, relative);

        if (!candidate.startsWith(mount.dir)) {
            return null;
        }

        if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
            return candidate;
        }
    }

    return null;
}

const server = http.createServer((req, res) => {
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'text/plain' });
        res.end('ok');
        return;
    }

    const file = resolve(req.url);

    if (!file) {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        res.end('not found');
        return;
    }

    res.writeHead(200, {
        'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream',
        'Cache-Control': 'no-store',
    });
    fs.createReadStream(file).pipe(res);
});

server.listen(PORT, '127.0.0.1', () => {
    process.stdout.write(`ldna fixture server on http://127.0.0.1:${PORT}\n`);
});
