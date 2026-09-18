<?php

namespace App\Observers;

use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Support\SmartTags\SmartTagSeekerSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Referential hygiene for `smart_tag_seeker_preferences`.
 *
 * `smart_tag_seeker_preferences` carries no foreign key to the criteria tables —
 * the house convention for tables addressed by (subject_type, subject_id), which
 * the existing Smart Tag tables already follow. Cleanup is therefore explicit,
 * and this observer is where it happens.
 *
 * NOT GATED BY THE FEATURE FLAG, deliberately. Rows written while
 * SMART_TAGS_SEEKER_PREFERENCES_ENABLED was on must still be removed when the
 * criteria record is deleted later with the flag off — otherwise switching a
 * feature off is what mints orphans. See
 * {@see \App\Support\SmartTags\SmartTagSeekerPreferenceGate::purgeAlwaysAllowed()}.
 *
 * AFTER the delete, never before, and never inside the caller's transaction:
 * the same reasoning `BelongsToListingWorkflow::purgeListingRows()` records for
 * the listing-side purge. Orphaned preference rows are inert — nothing reads
 * them, and the reader drops keys it cannot resolve — while a criteria record
 * that fails to delete because its preferences could not be cleaned up is a real
 * defect the customer sees.
 *
 * IT CANNOT THROW. A failure is logged and swallowed, so a cleanup problem can
 * never turn a deletion the user asked for into an error page.
 *
 * LIMIT, STATED RATHER THAN IMPLIED: Eloquent events do not fire for a
 * query-builder mass delete (`DB::table(...)->delete()`) or a bulk
 * `Model::whereIn(...)->delete()`. There is no such path for criteria records
 * today — this application has no criteria deletion route at all — but a future
 * bulk purge must call {@see SmartTagSeekerPreferenceWriter::purge()} explicitly,
 * exactly as the listing-side trait does.
 */
class SmartTagSeekerPreferenceObserver
{
    public function deleted(Model $criteria): void
    {
        $type = SmartTagSeekerSubjectType::forModel($criteria);

        if ($type === null) {
            return;
        }

        $id = (int) ($criteria->id ?? 0);

        if ($id <= 0) {
            return;
        }

        try {
            app(SmartTagSeekerPreferenceWriter::class)->purge($type, $id);
        } catch (\Throwable $e) {
            Log::warning('smart_tag_seeker_preferences purge failed', [
                'subject_type' => $type->value,
                'subject_id'   => $id,
                'exception'    => $e::class,
            ]);
        }
    }
}
