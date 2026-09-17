<?php

namespace App\Services\ListingPreferences;

use RuntimeException;

/**
 * Reasons were sent for a listing the customer holds no current preference on.
 *
 * Reasons explain a choice; a reason set with nothing to explain is not stored.
 */
class ListingPreferenceMissing extends RuntimeException
{
}
