<?php

namespace App\Services\Spatial\OvertureV2Import;

/**
 * An Overture v2 import that must not proceed: a contract, manifest, file, row, reconciliation or
 * database check failed. Raised before any write, or after the import transaction rolled back.
 */
final class InvalidOvertureV2Import extends \RuntimeException
{
}
