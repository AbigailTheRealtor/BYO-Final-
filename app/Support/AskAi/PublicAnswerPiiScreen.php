<?php

namespace App\Support\AskAi;

/**
 * PublicAnswerPiiScreen — the deterministic contact/address screen an owner-written
 * answer must pass before it is published on a public listing page.
 *
 * WHAT THIS IS FOR. Batch 4 publishes a curated subset of the owner's AI Knowledge
 * Base answers. Those answers are free text typed into a form whose original audience
 * was a private chatbot, so an owner may reasonably have written "call me on
 * 727-555-0147" or "see 123 Main Street" without ever intending a public page to carry
 * it. This class decides, by pattern alone, whether an answer carries that kind of
 * detail.
 *
 * WHOLE-ANSWER, FAIL-CLOSED. A hit hides the ENTIRE question. There is no redaction,
 * no masking and no "safe prefix": rewriting somebody's sentence to make it publishable
 * would put words in their mouth, and a partially blanked answer advertises that
 * something was removed. Hidden is the quiet, honest outcome.
 *
 * THE FALSE-POSITIVE PROBLEM IS THE DESIGN PROBLEM. Property answers are full of
 * numbers — "2 car garage", "3 bedrooms", "50 amp service", "1,500 sq ft", "roof
 * replaced 2019". A screen loose enough to read those as phone numbers or street
 * addresses would hide most of the useful answers and teach nobody anything. So every
 * rule here demands STRUCTURE rather than digits:
 *
 *   - a phone number needs separators in the 3-3-4 shape, or ten consecutive digits;
 *   - a street address needs a house number AND at least one intervening word AND a
 *     street-type word, so "50 amp service" cannot reach it;
 *   - "sq"/"square" is deliberately NOT a street type, because "1500 sq ft" would
 *     otherwise read as an address;
 *   - a domain needs a real TLD after letters, so "3.5 acres" is not a web link.
 *
 * NO PERSON-NAME DETECTION. Deliberately out of scope: a name detector is either a
 * dictionary (which misses most names) or a capitalisation heuristic (which would hide
 * "Trane HVAC" and "Pinellas County"). That is a later decision, not a quiet one.
 *
 * NO NETWORK, NO AI, NO DATABASE. Pure functions over a string.
 */
class PublicAnswerPiiScreen
{
    /** An e-mail address. */
    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    /**
     * A web link. Either an explicit scheme/host prefix, or a bare domain whose label is
     * alphabetic and whose suffix is a real TLD — the alphabetic label is what keeps
     * "3.5 acres", "2.5 baths" and version-like numbers out.
     */
    private const URL = [
        '~\bhttps?://~i',
        '~\bwww\.[a-z0-9\-]+\.[a-z]{2,}~i',
        '~\b[a-z][a-z0-9\-]*\.(?:com|net|org|io|co|us|info|biz|gov|edu|realtor|homes|rentals|properties|app|site|online)\b~i',
    ];

    /**
     * A telephone number.
     *
     * The first pattern requires the separators, which is what distinguishes a number
     * somebody can dial from a number that happens to be in a sentence: "1500 sq ft"
     * and "$450,000" carry no 3-3-4 separator structure. The second catches the
     * unseparated ten-digit form. Both are anchored so a longer digit run (a parcel
     * number, an MLS id) does not partially match.
     */
    private const PHONE = [
        '/(?<!\d)(?:\+?1[\s.\-])?\(?\d{3}\)?[\s.\-]\d{3}[\s.\-]\d{4}(?!\d)/',
        '/(?<!\d)\d{10}(?!\d)/',
    ];

    /**
     * Identifiers that are TEN DIGITS LONG AND NOT A PHONE NUMBER.
     *
     * A bare ten-digit run is ambiguous by itself, and the previous rule resolved that
     * ambiguity by always calling it a phone number — which hid "Parcel number
     * 1234567890 on file", an ordinary and useful answer, as though the owner had
     * published their mobile. Real estate answers are full of long identifiers: parcel
     * and folio numbers, APNs, tax accounts, permit and MLS numbers.
     *
     * The disambiguator is the LABEL the writer put in front of the digits. A number
     * somebody introduces as a parcel number is a parcel number; a number with no such
     * label, or one introduced with "call", stays a phone number. This is why the
     * separated 3-3-4 form is NOT subject to this exemption below — nobody writes a
     * parcel number as 727-555-0147, and a labelled-looking phone number is still a
     * phone number.
     */
    private const IDENTIFIER_LABEL = '/\b(?:parcel|apn|a\.p\.n\.|folio|strap|tax(?:\s+|-)?(?:id|account|roll)?|account|permit|mls|listing|invoice|policy|serial|meter|case|file|order|reference|ref)\b[^.;!?]{0,24}$/i';

    /**
     * Wording that makes a number a number to CALL, whatever else surrounds it.
     *
     * Checked before the identifier exemption, so "Call the tax office at 7275550147"
     * stays blocked even though "tax" appears: an explicit instruction to dial outranks
     * an incidental label.
     */
    private const DIALLING_CONTEXT = '/\b(?:call|calls|called|calling|phone|phoned|dial|text|texts|texting|reach(?:ed)?|contact|ring|cell|mobile|tel|telephone|fax|whatsapp|sms)\b[^.;!?]{0,40}$/i';

    /**
     * Street types spelled in full. "square" is absent on purpose (see the class note),
     * and so is "circle"'s bare abbreviation risk — those live in ABBREVIATIONS below
     * under a stricter rule.
     */
    private const STREET_WORDS = 'street|avenue|road|boulevard|lane|drive|court|circle|terrace|place|parkway|highway|trail|loop|alley|plaza|crossing|commons|way';

    /** Abbreviated street types. Matched only under the same "house number + word" shape. */
    private const STREET_ABBREVIATIONS = 'st|ave|rd|blvd|ln|dr|ct|cir|ter|pl|pkwy|hwy|trl';

    /**
     * A street address: a house number, AT LEAST ONE intervening word, then a street
     * type. The required intervening word is doing real work — without it "2 way",
     * "3 pl" and "50 dr" would all read as addresses.
     */
    private static function streetPatterns(): array
    {
        return [
            '/\b\d{1,6}\s+(?:[A-Za-z0-9.\'\-]+\s+){1,4}(?:' . self::STREET_WORDS . ')\b/i',
            '/\b\d{1,6}\s+(?:[A-Za-z0-9.\'\-]+\s+){1,4}(?:' . self::STREET_ABBREVIATIONS . ')\b\.?/i',
        ];
    }

    /**
     * A PROXIMITY statement wearing an address's clothes.
     *
     * "2 blocks from Central Avenue" and "Located 3 houses from Oak Lane" have exactly
     * the shape the street rules look for — a number, some words, a street type — and
     * neither is an address. They are the most natural way to answer "what is nearby",
     * and hiding them taught a shopper nothing while looking like a bug.
     *
     * What separates them from "123 Oak Lane" is a RELATIONAL WORD sitting between the
     * number and the street: a unit of distance ("blocks", "houses", "miles", "minutes")
     * and/or a preposition of relation ("from", "off", "past", "north of"). A street
     * address has no such word — the number belongs TO the street, it is not measured
     * FROM it.
     *
     * So this is applied to the MATCHED SPAN, not to the whole answer: an answer may
     * legitimately contain both a proximity phrase and a real address, and judging the
     * sentence as a whole would let the first excuse the second. Each candidate is
     * judged on its own text, and one surviving candidate still blocks the answer.
     */
    private const PROXIMITY_SPAN = '/\b(?:from|off|past|toward|towards|beyond|behind|between|near|nearest|around|approx|approximately)\b|\b(?:blocks?|houses?|homes?|doors?|miles?|mi|minutes?|mins?|steps?|lots?|units?|streets?|intersections?|lights?|stoplights?)\s+(?:from|down|away|off|to|north|south|east|west)\b/i';

    /**
     * Decide whether one answer may be published.
     *
     * @param  string   $text            the owner's answer, already whitespace-normalised.
     * @param  string[] $withheldAddress address fragments the PAGE is withholding from this
     *                                   viewer. Empty when the page is showing the address.
     * @return array{blocked:bool, reason:?string}  reason is a category name, NEVER the
     *                                              matched text — a failure message that
     *                                              quoted the match would republish the
     *                                              very detail the screen just withheld.
     */
    public static function screen(string $text, array $withheldAddress = []): array
    {
        $normalised = self::normalise($text);

        if ($normalised === '') {
            return ['blocked' => false, 'reason' => null];
        }

        if (@preg_match(self::EMAIL, $normalised) === 1) {
            return ['blocked' => true, 'reason' => 'email'];
        }

        if (self::hasPhoneNumber($normalised)) {
            return ['blocked' => true, 'reason' => 'phone'];
        }

        foreach (self::URL as $pattern) {
            if (@preg_match($pattern, $normalised) === 1) {
                return ['blocked' => true, 'reason' => 'url'];
            }
        }

        if (self::hasStreetAddress($normalised)) {
            return ['blocked' => true, 'reason' => 'street_address'];
        }

        // The listing's OWN address, when this page has decided this viewer may not see
        // it. The generic street rule above would miss "on Main Street at the corner" —
        // no house number — and that is still the withheld address written out.
        if (self::mentionsWithheldAddress($normalised, $withheldAddress)) {
            return ['blocked' => true, 'reason' => 'withheld_listing_address'];
        }

        return ['blocked' => false, 'reason' => null];
    }

    /** Convenience predicate. */
    public static function isPublishable(string $text, array $withheldAddress = []): bool
    {
        return self::screen($text, $withheldAddress)['blocked'] === false;
    }

    /**
     * Is there a number here somebody could dial?
     *
     * The separated 3-3-4 form is decided on its shape alone — that shape IS a phone
     * number and no label makes it something else. The bare ten-digit form is decided on
     * context: dialling wording blocks it, an identifier label ahead of it exempts it,
     * and an unlabelled run stays blocked, because fail-closed is the right default for
     * the ambiguous case.
     */
    private static function hasPhoneNumber(string $text): bool
    {
        if (@preg_match(self::PHONE[0], $text) === 1) {
            return true;
        }

        $matches = [];
        if (@preg_match_all(self::PHONE[1], $text, $matches, PREG_OFFSET_CAPTURE) !== 1
            && empty($matches[0])) {
            return false;
        }

        foreach ($matches[0] ?? [] as $match) {
            $before = substr($text, 0, (int) $match[1]);

            // An explicit instruction to call outranks any label before it.
            if (@preg_match(self::DIALLING_CONTEXT, $before) === 1) {
                return true;
            }

            if (@preg_match(self::IDENTIFIER_LABEL, $before) === 1) {
                continue; // A labelled identifier, not a phone number.
            }

            return true;
        }

        return false;
    }

    /**
     * Is there a street address here, as opposed to a statement of distance FROM one?
     *
     * Every candidate the street rules find is re-judged against PROXIMITY_SPAN on its
     * own matched text. One candidate that is not a proximity phrase blocks the answer;
     * an answer whose only candidates are proximity phrases publishes.
     */
    private static function hasStreetAddress(string $text): bool
    {
        foreach (self::streetPatterns() as $pattern) {
            $matches = [];

            if (@preg_match_all($pattern, $text, $matches) === false) {
                continue;
            }

            foreach ($matches[0] ?? [] as $candidate) {
                if (@preg_match(self::PROXIMITY_SPAN, $candidate) === 1) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Does the answer restate a fragment of the address the page is withholding?
     *
     * Compared on a letters-and-digits-only reduction so punctuation, casing and
     * spacing cannot smuggle it past ("123 Main St." vs "123 main st"). Fragments
     * shorter than five reduced characters are ignored: a unit number like "3" or a
     * state like "FL" would otherwise match nearly every sentence.
     */
    private static function mentionsWithheldAddress(string $text, array $fragments): bool
    {
        $haystack = self::reduce($text);

        if ($haystack === '') {
            return false;
        }

        foreach ($fragments as $fragment) {
            if (!is_string($fragment)) {
                continue;
            }

            $needle = self::reduce($fragment);

            if (strlen($needle) < 5) {
                continue;
            }

            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fragments of a listing's own address that a withheld page must not let an
     * answer restate.
     *
     * Returns the full street line AND the street line with its leading house number
     * removed. Both are needed: "1234 Gulf Boulevard" is the address, but so, on a page
     * that is withholding it, is "the entrance is on Gulf Boulevard". The five-character
     * floor in the comparison keeps a short street name ("Main", "Elm") from matching
     * ordinary prose, which is the right trade — a bare common street name is weak
     * evidence, and over-blocking every answer containing the word "main" would be worse
     * than the leak it prevents.
     *
     * Returns an EMPTY array when the page is showing the address, so the comparison is
     * skipped entirely rather than run against values nobody is hiding.
     *
     * @return string[]
     */
    public static function addressFragments(bool $addressWithheld, mixed $address, mixed $unit = null): array
    {
        if (!$addressWithheld) {
            return [];
        }

        $out = [];

        foreach ([$address, $unit] as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $line  = self::normalise($value);
            $out[] = $line;

            // Same line without a leading house number.
            $withoutNumber = trim((string) preg_replace('/^\s*\d+[A-Za-z]?\s+/', '', $line));
            if ($withoutNumber !== '' && $withoutNumber !== $line) {
                $out[] = $withoutNumber;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** Collapse the punctuation and whitespace people actually type. */
    private static function normalise(string $text): string
    {
        $text = str_replace(
            ["\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x93", "\xE2\x80\x94", "\xC2\xA0"],
            ["'", "'", '-', '-', ' '],
            $text
        );

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Letters and digits only, lowercased — for address comparison. */
    private static function reduce(string $text): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $text));
    }
}
