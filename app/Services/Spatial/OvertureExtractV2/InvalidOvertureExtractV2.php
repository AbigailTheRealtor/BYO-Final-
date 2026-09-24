<?php

namespace App\Services\Spatial\OvertureExtractV2;

/**
 * The recipe config, or the raw input's structure, is broken. Always aborts the whole run: a
 * malformed recipe or a drifted SQL projection must never produce a partial corpus.
 */
final class InvalidOvertureExtractV2 extends \RuntimeException
{
}
