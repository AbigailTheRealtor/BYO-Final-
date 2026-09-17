<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Preferences Phase 2 — let a preference be CLEARED, not just changed.
 *
 * Phase 1 modelled the three transitions INTO a state and never the transition
 * out of all of them: `from_state` was nullable ("no prior state") but
 * `to_state` was NOT NULL, so "the customer withdrew their choice" was
 * unwritable. Deleting the current row already works and the reasons already
 * cascade; the history event was the blocker, and an unwritable history event
 * means either losing the audit record or faking a state.
 *
 * AFTER THIS MIGRATION, `to_state` NULL MEANS EXACTLY ONE THING:
 * no current preference after this transition.
 *
 *   from_state = null,            to_state = save|maybe|pass  first preference
 *   from_state = save|maybe|pass, to_state = save|maybe|pass  state change
 *   from_state = save|maybe|pass, to_state = null             cleared
 *
 * There is deliberately NO fourth `cleared` state. ListingPreferenceState is
 * also the vocabulary of `listing_preferences.state`, and a value that can
 * never be a current state must not live in that enum. `pass` is not overloaded
 * either: "I passed on this house" and "I withdrew my opinion" are different
 * facts, and conflating them would corrupt the Pass list and the negative
 * signal any future learner reads.
 *
 * THE PHASE 1 MIGRATION IS NOT EDITED. It is merged; this is a forward change.
 *
 * IT MUST NOT NEED doctrine/dbal, AND THAT IS NOT A STYLE CHOICE.
 * `->change()` is the obvious way to write this and it would have failed the
 * deploy. Laravel 8 implements it through Doctrine (ChangeColumn throws
 * "Changing columns … requires Doctrine DBAL" without it), dbal is in this
 * repository's `require-dev` ONLY — pulled in by a dev migrations-generator —
 * and the Replit deployment builds with `composer install --no-dev`. So the
 * package is present in every test run and absent in production, where
 * `deploy/start-production.sh` runs `php artisan migrate --force` and a failed
 * migration stops the deploy.
 *
 * PostgreSQL — the production driver — therefore gets the one statement this
 * actually is, `ALTER COLUMN … DROP NOT NULL`, which needs nothing. SQLite
 * (the test driver) cannot drop a NOT NULL in place; it keeps the `->change()`
 * path, which is safe precisely because dbal is always installed wherever the
 * suite runs. The branch is on the DRIVER rather than on whether dbal happens
 * to be loadable, because the reason is the driver's own DDL support and a
 * capability probe would hide which path production takes.
 *
 * ROLLBACK SAFETY — down() REFUSES RATHER THAN CORRUPTS.
 * Restoring NOT NULL would have to do something with rows where `to_state` IS
 * NULL, and every available something is data loss: coercing them to a state
 * invents a preference the customer never expressed, and deleting them erases
 * audit history from an append-only table. So down() counts those rows first
 * and THROWS, naming the count, leaving the column nullable and the history
 * intact. On a database with no clear events — a deploy rolled back before the
 * feature was used, which is the realistic rollback — it restores NOT NULL
 * normally.
 */
class AllowNullToStateOnListingPreferenceEvents extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('listing_preference_events')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE listing_preference_events ALTER COLUMN to_state DROP NOT NULL');

            return;
        }

        Schema::table('listing_preference_events', function (Blueprint $table) {
            $table->string('to_state', 12)->nullable()->change();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('listing_preference_events')) {
            return;
        }

        $clearEvents = DB::table('listing_preference_events')->whereNull('to_state')->count();

        if ($clearEvents > 0) {
            throw new RuntimeException(
                "Refusing to restore NOT NULL on listing_preference_events.to_state: {$clearEvents} "
                . 'clear event(s) record that a customer removed their preference, and this column is the '
                . 'only place that fact is stored. Restoring the constraint would mean inventing a '
                . 'preference for those rows or deleting append-only audit history. Resolve the rows '
                . 'deliberately first if this rollback is really intended.'
            );
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE listing_preference_events ALTER COLUMN to_state SET NOT NULL');

            return;
        }

        Schema::table('listing_preference_events', function (Blueprint $table) {
            $table->string('to_state', 12)->nullable(false)->change();
        });
    }
}
