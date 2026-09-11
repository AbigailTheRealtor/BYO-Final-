<?php

namespace Tests\Feature\Explore\Support;

use App\Services\Bridge\BridgeApiService;

/**
 * A stand-in for the live Stellar/Bridge provider.
 *
 * Subclasses the real service and overrides only the two fetch methods, so the
 * code under test resolves and calls exactly the class it does in production —
 * the seam is the network boundary and nothing above it.
 *
 * It counts requests, which is the point of half these tests: "Explore must not
 * issue one provider request per marker" is a claim about call volume, and the
 * only way to assert it is to count.
 */
class FakeBridgeApi extends BridgeApiService
{
    /** @var list<array<string,mixed>> raw records the provider currently returns */
    public array $records = [];

    /** @var list<array{filter:?string,top:int,skip:int}> */
    public array $paginatedCalls = [];

    /** @var list<array{filter:?string,limit:int}> */
    public array $singleCalls = [];

    public bool $shouldFail = false;

    public function __construct()
    {
        // Deliberately does not call parent::__construct(): the real one reads
        // credentials, and a test double must never need them.
    }

    public function fetchPropertiesPaginated(int $top = 200, int $skip = 0, ?string $filter = null): array
    {
        $this->paginatedCalls[] = ['filter' => $filter, 'top' => $top, 'skip' => $skip];

        if ($this->shouldFail) {
            throw new \RuntimeException('provider unavailable (test double)');
        }

        $matching = $this->matching($filter);

        // Honour the window the caller asked for, so a budget test that needs a
        // genuinely multi-page pass gets one. The importer stops when a page
        // comes back shorter than the page size, which a real slice produces
        // naturally at the end of the set.
        return array_slice($matching, $skip, $top);
    }

    public function fetchProperties(int $limit = 10, ?string $filter = null): array
    {
        $this->singleCalls[] = ['filter' => $filter, 'limit' => $limit];

        if ($this->shouldFail) {
            return [];
        }

        foreach ($this->records as $record) {
            $key = (string) ($record['ListingKey'] ?? '');

            if ($key !== '' && $filter !== null && str_contains($filter, $key)) {
                return [$record];
            }
        }

        return [];
    }

    public function providerRequestCount(): int
    {
        return count($this->paginatedCalls) + count($this->singleCalls);
    }

    /**
     * Records whose PropertyType the filter actually asked for.
     *
     * Crude, and deliberately so — it is not a reimplementation of OData. It
     * exists because a discovery pass runs once per transaction type, and a
     * double that ignored the filter would let the sale pass return the rental
     * records, which would make the sale/rent separation tests pass for the
     * wrong reason.
     *
     * @return list<array<string,mixed>>
     */
    private function matching(?string $filter): array
    {
        if ($filter === null) {
            return $this->records;
        }

        return array_values(array_filter($this->records, static function (array $record) use ($filter): bool {
            $type = (string) ($record['PropertyType'] ?? '');

            return $type !== '' && str_contains($filter, "PropertyType eq '{$type}'");
        }));
    }
}
