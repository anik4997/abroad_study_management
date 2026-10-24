<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Converts free-typed cell text into the structured catalog attributes used for filtering.
 */
class FieldParser
{
    /**
     * @param  array<string, string>  $values  canonical field => raw cell text
     * @return array<string, mixed> catalog_items attributes
     */
    public static function toCatalogAttributes(array $values): array
    {
        $text = fn (string $field, int $limit = 255): ?string => ($v = trim($values[$field] ?? '')) === '' ? null : Str::limit($v, $limit, '');
        $long = fn (string $field): ?string => ($v = trim($values[$field] ?? '')) === '' ? null : Str::limit($v, 5000, '');

        $requirements = $long('requirements');
        $deadline = $text('deadline');
        $tuition = $text('tuition');

        return [
            'country' => self::country($values['country'] ?? ''),
            'city' => $text('city', 120),
            'degree' => self::degree($values['degree'] ?? ''),
            'language' => self::language($values['language'] ?? ''),
            'nc_status' => self::ncStatus($values['nc_status'] ?? '', $requirements),
            'intake' => self::intake($values['intake'] ?? ''),
            'deadline' => $deadline,
            'deadline_date' => self::deadlineDate((string) $deadline)?->toDateString(),
            'tuition' => $tuition,
            'tuition_amount' => self::amount((string) $tuition),
            'application_fee' => self::applicationFee($values['application_fee'] ?? ''),
            'ielts' => self::ielts($values['ielts'] ?? '') ?? self::ielts($requirements ?? '', requireLabel: true),
            'toefl' => self::toefl($values['toefl'] ?? '') ?? self::toefl($requirements ?? '', requireLabel: true),
            'gre' => self::gre($values['gre'] ?? '', $requirements),
            'portal' => self::portal($values['portal'] ?? '', $values['application_fee'] ?? null, $requirements),
            'entrance' => $text('entrance'),
            'ects' => $text('ects'),
            'gpa' => $text('gpa'),
            'requirements' => $requirements,
            'documents' => $long('documents'),
            'funding' => $long('funding'),
            'website' => self::url($values['website'] ?? ''),
        ];
    }

    public static function country(string $value): ?string
    {
        $v = trim($value);
        $lower = Str::lower(Str::ascii($v));

        return match (true) {
            $v === '' => null,
            in_array($lower, ['germany', 'de', 'deutschland', 'german', 'ger'], true) => 'Germany',
            in_array($lower, ['usa', 'us', 'u s a', 'u.s.a.', 'u.s.', 'united states', 'united states of america', 'america'], true) => 'USA',
            in_array($lower, ['uk', 'u.k.', 'united kingdom', 'england', 'great britain'], true) => 'UK',
            default => Str::limit(Str::title($v), 80, ''),
        };
    }

    public static function degree(string $value): ?string
    {
        $v = Str::lower(Str::ascii(trim($value)));

        return match (true) {
            $v === '' => null,
            (bool) preg_match('/(ph\.?\s?d|doctor|doctoral)/', $v) => 'PhD',
            (bool) preg_match('/\bmba\b/', $v) => 'MBA',
            (bool) preg_match('/(master|\bm\.?\s?(sc|s|a|eng|res)\b\.?)/', $v) => 'Master',
            (bool) preg_match('/(bachelor|\bb\.?\s?(sc|s|a|eng)\b\.?|undergrad)/', $v) => 'Bachelor',
            (bool) preg_match('/^(any|all)/', $v) => 'Any level',
            default => Str::limit(Str::title(trim($value)), 60, ''),
        };
    }

    public static function language(string $value): ?string
    {
        $v = Str::lower(Str::ascii(trim($value)));
        $english = (bool) preg_match('/(english|\ben\b|eng\b)/', $v);
        $german = (bool) preg_match('/(german|deutsch|\bde\b|ger\b)/', $v);

        return match (true) {
            $v === '' => null,
            $english && $german => 'English & German',
            $english => 'English',
            $german => 'German',
            default => Str::limit(Str::title(trim($value)), 60, ''),
        };
    }

    public static function intake(string $value): ?string
    {
        $v = Str::lower(Str::ascii(trim($value)));
        $winter = (bool) preg_match('/(winter|\bws\b|wise)/', $v);
        $summer = (bool) preg_match('/(summer|\bss\b|sose)/', $v);
        $fall = (bool) preg_match('/(fall|autumn)/', $v);
        $spring = str_contains($v, 'spring');

        return match (true) {
            $v === '' => null,
            $winter && $summer => 'Winter & Summer',
            $fall && $spring => 'Fall & Spring',
            $winter => 'Winter',
            $summer => 'Summer',
            $fall => 'Fall',
            $spring => 'Spring',
            default => Str::limit(Str::title(trim($value)), 60, ''),
        };
    }

    public static function ncStatus(string $value, ?string $requirements = null): string
    {
        $v = Str::lower(Str::ascii(trim($value)));

        if ($v !== '') {
            // "Not simply NC-free; strict eligibility" is neither clearly open nor NC.
            if (preg_match('/not\s+(simply\s+|really\s+|fully\s+)?(nc[\s\-_]*free|open)/', $v)) {
                return 'unknown';
            }

            if (preg_match('/(nc[\s\-_]*free|no[\s\-_]*nc\b|zulassungsfrei|open\s*admission|^open|admission[\s\-]*free|free\s+admission|unrestricted|not\s+restricted|non[\s\-]*restricted|no\s+(admission\s+)?restriction|^none$|^no$|^false$|^0$|without\s+(nc|restriction))/', $v)) {
                return 'nc_free';
            }

            if (preg_match('/(^nc\b|orts[\s\-]*nc|numerus|restrict|zulassungsbeschrankt|^yes$|^true$|^1$|limited)/', $v)) {
                return 'nc';
            }
        }

        $r = Str::lower(Str::ascii((string) $requirements));

        if ($r !== '' && preg_match('/(nc[\s\-]*free|zulassungsfrei)/', $r)) {
            return 'nc_free';
        }

        return 'unknown';
    }

    public static function ielts(string $value, bool $requireLabel = false): ?float
    {
        $pattern = $requireLabel ? '/ielts[^0-9]{0,15}([4-9](?:[.,][05])?)/i' : '/(?<![\d.])([4-9](?:[.,][05])?)(?![\d])/';

        if (preg_match($pattern, $value, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return null;
    }

    public static function toefl(string $value, bool $requireLabel = false): ?int
    {
        $pattern = $requireLabel ? '/toefl[^0-9]{0,15}(\d{2,3})/i' : '/(?<!\d)(\d{2,3})(?!\d)/';

        if (preg_match($pattern, $value, $match) && (int) $match[1] <= 120 && (int) $match[1] >= 40) {
            return (int) $match[1];
        }

        return null;
    }

    public static function gre(string $value, ?string $requirements = null): string
    {
        $v = Str::lower(trim($value));

        if ($v !== '') {
            return match (true) {
                (bool) preg_match('/(not required|no\b|^none|waived|not needed|^false$|^0$)/', $v) => 'no',
                (bool) preg_match('/(optional|recommended|preferred)/', $v) => 'optional',
                (bool) preg_match('/(required|mandatory|yes|^true$|^1$|\d{3})/', $v) => 'required',
                default => 'unknown',
            };
        }

        $r = Str::lower((string) $requirements);

        return match (true) {
            (bool) preg_match('/(gre|gmat)[^.\n]{0,20}(not required|optional)/', $r) => str_contains($r, 'optional') ? 'optional' : 'no',
            (bool) preg_match('/(gre|gmat)[^.\n]{0,20}required/', $r) => 'required',
            default => 'unknown',
        };
    }

    /**
     * Read the application route from the portal column, then from columns people often
     * misuse for it ("Application fee: Uni Assist"), then weakly from requirement text.
     */
    public static function portal(string $value, ?string $applicationFee = null, ?string $requirements = null): ?string
    {
        foreach ([$value, (string) $applicationFee] as $candidate) {
            $v = Str::lower(trim($candidate));

            $match = match (true) {
                $v === '' => null,
                str_contains($v, 'vpd') => 'vpd',
                (bool) preg_match('/uni[\s\-]?assist/', $v) => 'uni-assist',
                (bool) preg_match('/(direct|po?r?tal|online|university|website|site)/', $v) => 'direct',
                default => null,
            };

            if ($match) {
                return $match;
            }
        }

        $r = Str::lower((string) $requirements);

        return match (true) {
            str_contains($r, 'vpd') => 'vpd',
            (bool) preg_match('/uni[\s\-]?assist/', $r) => 'uni-assist',
            default => null,
        };
    }

    /**
     * Keep only text that actually describes a fee (an amount or "free"), not e.g. "Uni Assist".
     */
    public static function applicationFee(string $value): ?string
    {
        $v = trim($value);

        return $v !== '' && preg_match('/(\d|free|no\s+fee|none|€|\$|eur|usd)/i', $v) ? Str::limit($v, 255, '') : null;
    }

    public static function amount(string $value): ?float
    {
        $v = Str::lower(trim($value));

        if ($v === '') {
            return null;
        }

        if (preg_match('/^(free|none|no|nil|0|no tuition|tuition[- ]free)\b/', $v) || str_contains($v, 'no tuition')) {
            return 0.0;
        }

        if (preg_match('/(\d[\d.,\s]*)/', $v, $match)) {
            $number = trim($match[1], ' .,');
            $number = preg_match('/^\d{1,3}(\.\d{3})+$/', $number) ? str_replace('.', '', $number) : $number;
            $number = preg_replace('/[\s,]/', '', $number) ?? '';

            return is_numeric($number) ? (float) $number : null;
        }

        return null;
    }

    /**
     * Picks the last recognisable date in the text, so "16 Nov 2026 – 12 Jan 2027" yields 12 Jan 2027.
     * Dates written without a year ("19 sep - 15 dec", "1Nov - 31march") recur yearly, so they
     * resolve to their next occurrence from $today.
     */
    public static function deadlineDate(string $value, ?CarbonImmutable $today = null): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $month = '(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?';
        $patterns = [
            'iso' => '/\b(\d{4})-(\d{2})-(\d{2})\b/',
            'dmy' => '/\b(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})\b/',
            'd_month' => '/\b(\d{1,2})(?:st|nd|rd|th)?\s*'.$month.'(?:,?\s*(\d{4}))?/i',
            'month_d' => '/\b'.$month.'\s+(\d{1,2})(?!\d)(?:st|nd|rd|th)?(?:,?\s*(\d{4}))?/i',
        ];
        $found = [];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $value, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $found[$match[0][1]] ??= [$type, array_map(fn ($group) => $group[0], $match)];
                }
            }
        }

        krsort($found);
        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

        foreach ($found as [$type, $groups]) {
            [$year, $monthNumber, $day] = match ($type) {
                'iso' => [(int) $groups[1], (int) $groups[2], (int) $groups[3]],
                'dmy' => [(int) $groups[3], (int) $groups[2], (int) $groups[1]],
                'd_month' => [(int) ($groups[3] ?? 0), $months[Str::lower($groups[2])], (int) $groups[1]],
                'month_d' => [(int) ($groups[3] ?? 0), $months[Str::lower($groups[1])], (int) $groups[2]],
            };

            if (! checkdate($monthNumber, $day, $year ?: 2000)) {
                continue;
            }

            if ($year) {
                return CarbonImmutable::create($year, $monthNumber, $day);
            }

            $date = CarbonImmutable::create($today->year, $monthNumber, $day);

            return $date->lt($today) ? $date->addYear() : $date;
        }

        return null;
    }

    public static function url(string $value): ?string
    {
        $v = trim($value);

        if ($v === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $v) && preg_match('#^[\w.-]+\.[a-z]{2,}(/.*)?$#i', $v)) {
            $v = 'https://'.$v;
        }

        return filter_var($v, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $v) ? Str::limit($v, 500, '') : null;
    }
}
