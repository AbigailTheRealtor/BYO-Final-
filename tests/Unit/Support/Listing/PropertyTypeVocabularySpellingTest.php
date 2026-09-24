<?php

namespace Tests\Unit\Support\Listing;

use App\Support\Listing\PropertyTypeVocabulary as T;
use PHPUnit\Framework\TestCase;

/**
 * P1-B — PropertyTypeVocabulary::recognisedTypeFor(), the one additive accessor.
 *
 * It is the same table answering the reverse question: for each of the seven
 * observed entries (cached / composed), classifying the type and asking the reverse
 * returns that type. RESO aliases and BYO entries are never returned.
 */
class PropertyTypeVocabularySpellingTest extends TestCase
{
    private const OBSERVED = [
        'Residential', 'Residential Lease', 'Income', 'Commercial Sale',
        'Commercial Lease', 'Business Opportunity', 'Vacant Land',
    ];

    public function test_it_round_trips_every_observed_type(): void
    {
        $pairs = [];

        foreach (self::OBSERVED as $type) {
            $row = T::classifySource($type);

            $this->assertContains($row['provenance'], [T::PROVENANCE_CACHED, T::PROVENANCE_COMPOSED], $type);
            $this->assertSame($type, T::recognisedTypeFor($row['seller'], $row['transaction']), $type);
            $pairs[] = $row['seller'] . '|' . $row['transaction'];
        }

        $this->assertSame($pairs, array_unique($pairs), 'each (category, transaction) pair is answered by exactly one type');
    }

    public function test_it_never_returns_a_reso_alias_or_a_byo_word(): void
    {
        foreach (['ResidentialIncome', 'Land', 'Commercial', 'Business'] as $value) {
            $row = T::classifySource($value);
            $this->assertNotNull($row, $value);

            $this->assertNotSame($value, T::recognisedTypeFor($row['seller'], $row['transaction']), $value);
        }

        $this->assertSame('Income', T::recognisedTypeFor('Income', T::TRANSACTION_SALE));
        $this->assertSame('Vacant Land', T::recognisedTypeFor('Vacant Land', T::TRANSACTION_SALE));
    }

    public function test_anything_unanswerable_is_null(): void
    {
        $this->assertNull(T::recognisedTypeFor(null, T::TRANSACTION_SALE));
        $this->assertNull(T::recognisedTypeFor('Residential', null));
        $this->assertNull(T::recognisedTypeFor('Residential Property', T::TRANSACTION_LEASE), 'a landlord wording is not a category');
        $this->assertNull(T::recognisedTypeFor('Business', T::TRANSACTION_LEASE));
        $this->assertNull(T::recognisedTypeFor('Vacant Land', T::TRANSACTION_LEASE));
        $this->assertNull(T::recognisedTypeFor('Farm', T::TRANSACTION_SALE));
        $this->assertNull(T::recognisedTypeFor('residential', T::TRANSACTION_SALE), 'categories are exact');
    }

    public function test_classify_source_keeps_its_row_shape(): void
    {
        foreach (array_merge(self::OBSERVED, ['ResidentialIncome', 'Land', 'Commercial', 'Business']) as $value) {
            $this->assertSame(['seller', 'landlord', 'transaction', 'provenance'], array_keys(T::classifySource($value)), $value);
        }
    }
}
