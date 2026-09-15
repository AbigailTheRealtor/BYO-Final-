<?php

namespace App\Support\SmartTags;

/**
 * The two states a Smart Tag row can hold.
 *
 * There is deliberately no `unknown` case. Unknown is the ABSENCE of a row:
 * no evidence, no assignment. Storing "unknown" as a value would invite a
 * query to treat it as a third answer, and a missing description phrase or an
 * unselected checkbox must never be recorded as anything at all.
 */
enum SmartTagState: string
{
    /** Confirmed present. */
    case Present = 'present';

    /** Confirmed not present — structured sources only. */
    case Absent = 'absent';
}
