<?php

namespace App\Services\ListingPreferences;

use RuntimeException;

/**
 * The listing has no durable identity, so no preference may be stored.
 *
 * Distinct from a validation failure: the request was well formed and the
 * customer did nothing wrong — this listing simply cannot be addressed
 * durably (a Bridge row with no ListingKey). Inventing a key would attach the
 * preference to whatever that key later means.
 */
class ListingPreferenceSubjectUnresolvable extends RuntimeException
{
}
