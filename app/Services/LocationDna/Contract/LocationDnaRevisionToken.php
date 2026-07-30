<?php

namespace App\Services\LocationDna\Contract;

/**
 * LocationDnaRevisionToken — the §5.6 revision token for the private canonical document.
 *
 * G1c contract core. INERT. Approved by D-G1-3 (option 3-C).
 *
 * SEPARATE FROM THE BRIDGE CACHE KEY — BY APPROVAL
 * ------------------------------------------------
 * D-G1-3 approved separating this token from `CriteriaHashService`, and its carried
 * condition forbids changing `CriteriaHashService` or the Bridge cache key during G1c. This
 * class therefore does not call, extend or reuse that service. The Bridge key keeps its
 * current semantics — including the vertex-order and clear insensitivities G1b proved — until
 * a separately authorized compatibility increment.
 *
 * APPROVED SEMANTICS
 * ------------------
 *   deterministic for equivalent canonical documents           yes
 *   associative key order                                      no effect (ksort at every level)
 *   polygon VERTEX order                                       MEANINGFUL — reordering changes the token
 *   polygon COLLECTION order                                   no effect
 *   radius-search COLLECTION order                             no effect
 *   explicit clear (where a value existed)                      changes the token
 *   administrative-label change                                changes the token
 *   location_notes change                                      changes the token
 *   geometry change                                            changes the token
 *   malformed document                                         cannot be tokenised (it cannot exist)
 *   input mutation                                             none
 *
 * WHY schema_version IS NOT AN INPUT
 * ----------------------------------
 * D-G1-3 approved two clauses that must hold together: `schema_version` affects the token
 * "when it changes interpretation", and an interpretation-neutral lazy upgrade "does not
 * change the token". Including the version, or the interpretation mode, would break the
 * second clause — a lazy upgrade moves the mode from Legacy to Canonical while changing no
 * values. So the token is computed over the INTERPRETED presence set and values only, and
 * the first clause is satisfied through them: if a version change genuinely alters
 * interpretation, the interpreted values differ and the token moves with them. This is also
 * what §5.6 states directly — the token is "independent of `schema_version`". The approval
 * record documents this reconciliation.
 *
 * ALGORITHM VERSIONING
 * --------------------
 * Every token carries the `ldna-r1:` prefix so a future algorithm change is introducible
 * without any stored value being ambiguous. SHA-256 is used, matching the established house
 * pattern (§5.6, and `CriteriaHashService` / `LocationDnaVersionService`); a
 * non-cryptographic hash would diverge from that standard for no benefit.
 */
final class LocationDnaRevisionToken
{
    /** Algorithm identity. Bump the ordinal if the canonicalisation or digest ever changes. */
    public const ALGORITHM_PREFIX = 'ldna-r1';

    private const DIGEST = 'sha256';

    /** The whole-document token, for record-level concurrency (§5.6, §6.4). */
    public function forDocument(LocationDnaDocument $document): string
    {
        $canonical = [];

        foreach ($document->toDimensionArray() as $key => $value) {
            $canonical[$key] = $this->canonicalise($value);
        }

        // Retained extensions participate deterministically: a change to an unknown-future
        // key is a change to the document, even though this layer never interprets it.
        $extensions = [];

        foreach ($document->extensions() as $key => $value) {
            $extensions[$key] = $this->canonicalise($value);
        }

        ksort($canonical);
        ksort($extensions);

        return $this->digest([
            'dimensions' => $canonical,
            'extensions' => $extensions,
        ]);
    }

    /**
     * A per-dimension token, so a conflict can be scoped to the dimension that diverged
     * (§5.6, §6.4 — dimension-scoped optimistic concurrency).
     *
     * An absent dimension and a cleared dimension yield DIFFERENT tokens: presence is part of
     * the canonical meaning, so a clear is a real change even though the value is empty.
     */
    public function forDimension(LocationDnaDocument $document, Dimension $dimension): string
    {
        return $this->digest([
            'dimension' => $dimension->value,
            'present'   => $document->isPresent($dimension),
            'value'     => $document->isPresent($dimension)
                ? $this->canonicalise($document->value($dimension))
                : null,
        ]);
    }

    /**
     * Recursively canonicalise a value for hashing.
     *
     * Associative arrays are key-sorted at every depth, so key order never affects the token.
     * LISTS ARE NEVER REORDERED — that is deliberate and load-bearing. A polygon's `path` is
     * a list, and D-G1-3 approved that vertex order is semantically meaningful, so sorting
     * lists here would erase exactly the distinction this token exists to preserve. Collection
     * order for `polygons` and `radius_searches` is instead normalised upstream by
     * {@see LocationDnaNormalizer}, where it can be applied to those two dimensions only.
     */
    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];

        foreach ($value as $key => $entry) {
            $out[$key] = $this->canonicalise($entry);
        }

        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }

    private function digest(array $payload): string
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::ALGORITHM_PREFIX.':'.hash(self::DIGEST, $json);
    }
}
