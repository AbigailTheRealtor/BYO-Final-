<?php

namespace App\Services\Dna\Relevance\Validation;

use App\Services\Dna\Relevance\CandidateAttributeResolverInterface;
use App\Services\Dna\Relevance\DnaScoreRepository;
use Illuminate\Support\Facades\DB;

/**
 * ValidationRosterBuilder — Matching V2 C6.1 (read-only validation harness).
 *
 * Discovers a bounded, deterministic roster of real validation subjects from the
 * dna_scores corpus + on-platform attributes, ordered COMPLIANCE FIRST. Each entry
 * is a scenario the MatchingValidationRunner then exercises through the same
 * read-only pipeline matching:preview uses.
 *
 * GOVERNANCE: PURE READ-ONLY — only SELECTs, no writes, no scoring, no generation.
 * A pinned roster file (fromFile) bypasses discovery for reproducible runs.
 *
 * @see docs/matching-v2-c6_1-validation-harness-scope.md
 */
class ValidationRosterBuilder
{
    public function __construct(
        private readonly DnaScoreRepository $scores,
        private readonly CandidateAttributeResolverInterface $attributes,
    ) {
    }

    /**
     * Auto-discover the roster, compliance scenarios first.
     *
     * @return array<int,array{scenario:string,listing_type:string,listing_id:int,note:string}>
     */
    public function build(int $limitPerCategory): array
    {
        $limit    = max(1, $limitPerCategory);
        $demand   = $this->demandSubjects($limit);   // buyer_agent + tenant_agent
        $property = $this->propertySubjects($limit); // seller_agent + landlord_agent

        $entries = [];

        // --- compliance FIRST (the load-bearing legal checks) ---
        foreach ($this->nonEligibleSeekers($demand) as $s) {
            $entries[] = $this->entry('compliance-seeker', $s['listing_type'], $s['listing_id'],
                'non-eligible seeker must NOT receive senior-restricted listings');
        }
        foreach ($this->seniorListings($property) as $s) {
            $entries[] = $this->entry('compliance-listing', $s['listing_type'], $s['listing_id'],
                'senior-restricted listing must NOT surface non-eligible seekers');
        }

        // --- direction coverage ---
        foreach ($this->ofType($demand, 'buyer_agent') as $s) {
            $entries[] = $this->entry('buyer-to-listings', 'buyer_agent', $s['listing_id'], 'buyer demand → listings');
        }
        foreach ($this->ofType($demand, 'tenant_agent') as $s) {
            $entries[] = $this->entry('tenant-to-listings', 'tenant_agent', $s['listing_id'], 'tenant demand → listings');
        }
        foreach ($property as $s) {
            $entries[] = $this->entry('listing-to-demand', $s['listing_type'], $s['listing_id'], 'listing → buyer/tenant demand');
        }

        // --- mixed seller_agent/landlord_agent pool (runner verifies type correctness) ---
        if ($demand !== []) {
            $entries[] = $this->entry('mixed-pool', $demand[0]['listing_type'], $demand[0]['listing_id'],
                'pool may span seller_agent + landlord_agent; each match keeps the correct type');
        }

        // --- confidence / coverage spectrum (richest-scored demand subject) ---
        if ($rich = $this->richestDemandSubject()) {
            $entries[] = $this->entry('confidence', $rich['listing_type'], $rich['listing_id'], 'confidence/coverage spectrum');
        }

        // --- low-DNA (sparse subjects must degrade gracefully) ---
        foreach ($this->lowDnaDemandSubjects($limit) as $s) {
            $entries[] = $this->entry('low-dna', $s['listing_type'], $s['listing_id'], 'sparse (≤2 score keys) — must degrade gracefully');
        }

        // --- no-DNA (synthetic id guaranteed to carry no scores) ---
        $entries[] = $this->entry('no-dna', 'buyer_agent', $this->unusedBuyerId(),
            'subject with no dna_scores — determined must be 0');

        // --- truncation (runner runs cap=1 vs a high cap over this subject) ---
        if ($demand !== []) {
            $entries[] = $this->entry('truncation', $demand[0]['listing_type'], $demand[0]['listing_id'],
                'candidate_pool_truncated honesty across caps');
        }

        return $entries;
    }

    /**
     * Load a pinned roster JSON file (read-only). The file is an array of
     * {scenario, listing_type, listing_id, note?} objects, used verbatim.
     *
     * @return array<int,array{scenario:string,listing_type:string,listing_id:int,note:string}>
     */
    public function fromFile(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Roster file not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Roster file is not a JSON array: {$path}");
        }

        $entries = [];
        foreach ($decoded as $i => $row) {
            if (! isset($row['scenario'], $row['listing_type'], $row['listing_id'])) {
                throw new \RuntimeException("Roster entry #{$i} missing scenario/listing_type/listing_id.");
            }
            $entries[] = $this->entry(
                (string) $row['scenario'],
                (string) $row['listing_type'],
                (int) $row['listing_id'],
                (string) ($row['note'] ?? 'pinned'),
            );
        }

        // Compliance always runs first, even in a pinned roster.
        return $this->complianceFirst($entries);
    }

    /**
     * Reorder so every compliance-* scenario precedes the rest (stable otherwise).
     *
     * @param array<int,array{scenario:string,listing_type:string,listing_id:int,note:string}> $entries
     * @return array<int,array{scenario:string,listing_type:string,listing_id:int,note:string}>
     */
    public function complianceFirst(array $entries): array
    {
        $compliance = array_values(array_filter($entries, static fn ($e) => str_starts_with($e['scenario'], 'compliance-')));
        $rest       = array_values(array_filter($entries, static fn ($e) => ! str_starts_with($e['scenario'], 'compliance-')));

        return array_merge($compliance, $rest);
    }

    /** @return array{scenario:string,listing_type:string,listing_id:int,note:string} */
    private function entry(string $scenario, string $type, int $id, string $note): array
    {
        return ['scenario' => $scenario, 'listing_type' => $type, 'listing_id' => $id, 'note' => $note];
    }

    /** @return array<int,array{listing_type:string,listing_id:int}> */
    private function demandSubjects(int $limit): array
    {
        return array_merge(
            $this->scores->distinctSubjects('demand', ['buyer_agent'], $limit),
            $this->scores->distinctSubjects('demand', ['tenant_agent'], $limit),
        );
    }

    /** @return array<int,array{listing_type:string,listing_id:int}> */
    private function propertySubjects(int $limit): array
    {
        return array_merge(
            $this->scores->distinctSubjects('property', ['seller_agent'], $limit),
            $this->scores->distinctSubjects('property', ['landlord_agent'], $limit),
        );
    }

    /**
     * @param array<int,array{listing_type:string,listing_id:int}> $subjects
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    private function ofType(array $subjects, string $type): array
    {
        return array_values(array_filter($subjects, static fn ($s) => $s['listing_type'] === $type));
    }

    /**
     * Demand subjects whose seeker is explicitly NOT 55+ eligible (age55 === false).
     *
     * @param array<int,array{listing_type:string,listing_id:int}> $demand
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    private function nonEligibleSeekers(array $demand): array
    {
        if ($demand === []) {
            return [];
        }

        $profiles = $this->attributes->resolveMany('demand', $demand);
        $out = [];
        foreach ($demand as $s) {
            $p = $profiles[$s['listing_type'] . ':' . $s['listing_id']] ?? null;
            if ($p !== null && $p->age55 === false) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * Property subjects that are senior-restricted (age55 === true).
     *
     * @param array<int,array{listing_type:string,listing_id:int}> $property
     * @return array<int,array{listing_type:string,listing_id:int}>
     */
    private function seniorListings(array $property): array
    {
        if ($property === []) {
            return [];
        }

        $profiles = $this->attributes->resolveMany('property', $property);
        $out = [];
        foreach ($property as $s) {
            $p = $profiles[$s['listing_type'] . ':' . $s['listing_id']] ?? null;
            if ($p !== null && $p->age55 === true) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /** @return array{listing_type:string,listing_id:int}|null */
    private function richestDemandSubject(): ?array
    {
        $row = DB::table('dna_scores')
            ->where('side', 'demand')
            ->whereIn('listing_type', ['buyer_agent', 'tenant_agent'])
            ->select('listing_type', 'listing_id')
            ->groupBy('listing_type', 'listing_id')
            ->orderByRaw('COUNT(DISTINCT score_key) DESC')
            ->orderBy('listing_type')
            ->orderBy('listing_id')
            ->first();

        return $row ? ['listing_type' => (string) $row->listing_type, 'listing_id' => (int) $row->listing_id] : null;
    }

    /** @return array<int,array{listing_type:string,listing_id:int}> */
    private function lowDnaDemandSubjects(int $limit): array
    {
        return DB::table('dna_scores')
            ->where('side', 'demand')
            ->whereIn('listing_type', ['buyer_agent', 'tenant_agent'])
            ->select('listing_type', 'listing_id')
            ->groupBy('listing_type', 'listing_id')
            ->havingRaw('COUNT(DISTINCT score_key) <= 2')
            ->orderBy('listing_type')
            ->orderBy('listing_id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['listing_type' => (string) $r->listing_type, 'listing_id' => (int) $r->listing_id])
            ->all();
    }

    /** A buyer_agent listing_id guaranteed to carry no dna_scores rows. */
    private function unusedBuyerId(): int
    {
        return ((int) DB::table('dna_scores')->where('listing_type', 'buyer_agent')->max('listing_id')) + 1;
    }
}
