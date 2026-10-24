<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns free-typed institution / program names into comparison keys, so that
 * "TU München", "Technische Universität München" and "technical univ. of munich"
 * collapse to the same key regardless of case, accents, punctuation or word order.
 */
class NameNormalizer
{
    /**
     * Whole-word synonyms applied after transliteration.
     *
     * @var array<string, string>
     */
    private const SYNONYMS = [
        'univ' => 'university', 'uni' => 'university', 'universitat' => 'university', 'universitaet' => 'university',
        'universitet' => 'university', 'universidad' => 'university', 'universita' => 'university', 'universite' => 'university',
        'universiteit' => 'university', 'universities' => 'university',
        'technische' => 'technical', 'technischen' => 'technical', 'tech' => 'technology', 'technologie' => 'technology',
        'hochschule' => 'appliedsciences', 'fachhochschule' => 'appliedsciences', 'fh' => 'appliedsciences', 'uas' => 'appliedsciences',
        'haw' => 'appliedsciences', 'th' => 'technical appliedsciences',
        'munchen' => 'munich', 'koln' => 'cologne', 'nurnberg' => 'nuremberg', 'braunschweig' => 'brunswick',
        'hannover' => 'hanover', 'frankfurt am main' => 'frankfurt', 'inst' => 'institute', 'institut' => 'institute',
        'st' => 'saint', 'sankt' => 'saint', 'mt' => 'mount', 'dept' => 'department', 'coll' => 'college',
    ];

    /**
     * Abbreviations that expand to several words before synonym handling.
     *
     * @var array<string, string>
     */
    private const EXPANSIONS = [
        'tu' => 'technical university',
        'lmu' => 'ludwig maximilians university munich',
        'rwth' => 'rwth aachen university',
        'kit' => 'karlsruhe institute technology',
        'tum' => 'technical university munich',
        'hu berlin' => 'humboldt university berlin',
        'fu berlin' => 'free university berlin',
        'freie' => 'free',
        'mit' => 'massachusetts institute technology',
        'cmu' => 'carnegie mellon university',
        'ucla' => 'university california los angeles',
        'nyu' => 'new york university',
        'usc' => 'university southern california',
        'uiuc' => 'university illinois urbana champaign',
        'gatech' => 'georgia institute technology',
        'georgia tech' => 'georgia institute technology',
        'ut austin' => 'university texas austin',
        'uc berkeley' => 'university california berkeley',
        'uc san diego' => 'university california san diego',
        'ucsd' => 'university california san diego',
    ];

    /**
     * @var list<string>
     */
    private const STOPWORDS = ['of', 'the', 'and', 'in', 'for', 'at', 'de', 'der', 'die', 'des', 'fur', 'fuer', 'zu', 'am', 'an', 'la', 'le', 'di', 'y', 'e'];

    /**
     * Degree words removed from program names so "MSc Computer Science" == "Computer Science (M.Sc.)".
     *
     * @var list<string>
     */
    private const DEGREE_WORDS = ['msc', 'ms', 'ma', 'meng', 'mba', 'mres', 'sc', 'eng', 'master', 'masters', 'bsc', 'bs', 'ba', 'beng', 'bachelor', 'bachelors', 'phd', 'degree', 'program', 'programme', 'course', 'emjm', 'erasmus', 'mundus'];

    public static function institutionKey(string $name): string
    {
        $tokens = self::tokens($name, self::STOPWORDS);
        sort($tokens);

        return implode(' ', array_unique($tokens));
    }

    /**
     * Words that describe the kind of institution rather than which one it is.
     *
     * @var list<string>
     */
    public const GENERIC_WORDS = ['university', 'appliedsciences', 'technical', 'institute', 'technology', 'college', 'school', 'graduate', 'state', 'academy', 'faculty'];

    /**
     * The identifying part of an institution key: "hildesheim university" => "hildesheim".
     */
    public static function coreKey(string $institutionKey): string
    {
        return implode(' ', array_values(array_diff(explode(' ', $institutionKey), self::GENERIC_WORDS)));
    }

    public static function programKey(string $name): string
    {
        $name = self::stripLeadingAcronym($name);
        $name = preg_replace('/\b(master|bachelor|doctor)\'?s?\s+(of|in)\s+(science|arts|engineering|philosophy)\b/i', ' ', $name) ?? $name;
        $tokens = array_values(array_filter(
            self::tokens($name, [...self::STOPWORDS, ...self::DEGREE_WORDS], expand: false),
            fn (string $token): bool => strlen($token) > 1,
        ));
        sort($tokens);

        return implode(' ', array_unique($tokens)) ?: Str::lower(Str::ascii(trim($name)));
    }

    /**
     * Acronym typed by the user ("EDISS — …", "TUM") or derived from initials.
     */
    public static function acronym(string $name): ?string
    {
        $acronym = self::detectAcronym(trim($name));

        return $acronym !== null && ! in_array($acronym, ['MSC', 'MS', 'MA', 'MENG', 'MBA', 'MRES', 'BSC', 'BS', 'BA', 'BENG', 'PHD', 'MPHIL', 'LLM'], true) ? $acronym : null;
    }

    private static function detectAcronym(string $name): ?string
    {
        if (preg_match('/^([\p{L}\d][\p{L}\d+.\-]{1,15})\s*[—–\-:|(]\s*\S/u', $name, $match) && self::looksLikeAcronym($match[1])) {
            return self::compactAcronym($match[1]);
        }

        if (preg_match('/(?:\(|\s[—–\-|]\s*)([\p{Lu}\d][\p{L}\d.\-]{1,15})\)?\s*$/u', $name, $match) && self::looksLikeAcronym($match[1])) {
            return self::compactAcronym($match[1]);
        }

        if (! str_contains($name, ' ') && mb_strlen($name) <= 12 && self::looksLikeAcronym($name)) {
            return self::compactAcronym($name);
        }

        return null;
    }

    /**
     * "EDISS", "SE4GD", "CyberMACS" yes; "Heinrich-Heine", "Hildesheim" no.
     */
    private static function looksLikeAcronym(string $token): bool
    {
        $letters = preg_match_all('/\p{L}/u', $token);
        $upper = preg_match_all('/\p{Lu}/u', $token);

        return $upper >= 2 && $letters > 0 && $upper / $letters >= 0.5;
    }

    /**
     * Initials of the significant words: "Technical University of Munich" => "TUM".
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-–—,.\/()]+/u', Str::ascii($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach ($words as $word) {
            if (! in_array(Str::lower($word), self::STOPWORDS, true)) {
                $initials .= Str::upper($word[0]);
            }
        }

        return $initials;
    }

    public static function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return $percent;
    }

    /**
     * @param  list<string>  $drop
     * @return list<string>
     */
    private static function tokens(string $value, array $drop, bool $expand = true): array
    {
        $value = str_replace(['ß', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'], ['ss', 'a', 'o', 'u', 'a', 'o', 'u'], $value);
        $value = Str::lower(Str::ascii($value));
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = ' '.trim(preg_replace('/\s+/', ' ', $value) ?? '').' ';

        if ($expand) {
            foreach (self::EXPANSIONS as $from => $to) {
                $value = str_replace(" {$from} ", " {$to} ", $value);
            }
        }

        $tokens = [];

        foreach (explode(' ', trim($value)) as $token) {
            if ($token === '') {
                continue;
            }

            // German umlauts are often typed as digraphs ("Koeln", "Muenchen"); fold both spellings together.
            $token = strlen($token) > 3 ? str_replace(['ae', 'oe', 'ue'], ['a', 'o', 'u'], $token) : $token;
            $token = self::SYNONYMS[$token] ?? $token;

            foreach (explode(' ', $token) as $part) {
                if (! in_array($part, $drop, true)) {
                    $tokens[] = $part;
                }
            }
        }

        return $tokens;
    }

    private static function stripLeadingAcronym(string $name): string
    {
        $stripped = preg_replace('/^[\p{L}\d][\p{L}\d+.\-]{1,15}\s*[—–:|]\s*/u', '', trim($name));

        return $stripped !== null && trim($stripped) !== '' ? $stripped : $name;
    }

    private static function compactAcronym(string $value): string
    {
        return Str::upper(Str::limit(preg_replace('/[^\p{L}\d]/u', '', Str::ascii($value)) ?? '', 20, ''));
    }
}
