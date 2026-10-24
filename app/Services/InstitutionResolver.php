<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\InstitutionAlias;
use App\Support\NameNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Maps a free-typed university name to one canonical Institution, creating it only
 * when no exact key, alias, acronym or close fuzzy match exists.
 */
class InstitutionResolver
{
    private const FUZZY_THRESHOLD = 90.0;

    /**
     * @var array<string, Institution>
     */
    private array $memo = [];

    public function resolve(string $rawName, ?string $country = null, ?string $city = null): ?Institution
    {
        $name = trim(preg_replace('/\s+/u', ' ', $rawName) ?? '');

        if ($name === '' || mb_strlen($name) > 200) {
            return null;
        }

        $key = NameNormalizer::institutionKey($name);

        if ($key === '') {
            return null;
        }

        $memoKey = $key.'|'.Str::lower((string) $country);

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $institution = $this->findExisting($name, $key, $country);

        if ($institution) {
            $this->upgradeName($institution, $name, $key);
        } else {
            $institution = $this->create($name, $key, $country, $city);
        }

        $this->rememberAlias($institution, $name, $key);

        if ($city && ! $institution->city) {
            $institution->update(['city' => Str::limit($city, 120, '')]);
        }

        return $this->memo[$memoKey] = $institution;
    }

    private function findExisting(string $name, string $key, ?string $country): ?Institution
    {
        $alias = InstitutionAlias::with('institution')->where('key', $key)->first();

        if ($alias?->institution) {
            return $alias->institution;
        }

        if ($match = Institution::where('key', $key)->first()) {
            return $match;
        }

        $candidates = Institution::query()
            ->when($country, fn ($query) => $query->where(fn ($q) => $q->where('country', $country)->orWhereNull('country')))
            ->get(['id', 'name', 'key', 'acronym', 'country', 'city', 'slug', 'website']);

        $acronym = NameNormalizer::acronym($name);

        if ($acronym && strlen($acronym) >= 3) {
            $byAcronym = $candidates->first(fn (Institution $i) => $i->acronym === $acronym);

            if ($byAcronym) {
                return $byAcronym;
            }
        }

        if ($byCore = $this->matchByCoreName($key, $candidates)) {
            return $byCore;
        }

        $best = null;
        $bestScore = 0.0;

        // Compare only the identifying part, so "TH Owl" and "TH Köln" don't look alike because
        // both expand to "technical university of applied sciences".
        $core = NameNormalizer::coreKey($key);

        if (strlen($core) < 4) {
            return null;
        }

        $kind = array_values(array_intersect(explode(' ', $key), NameNormalizer::GENERIC_WORDS));

        foreach ($candidates as $candidate) {
            // "Technical University of Munich" and "University of Munich" are different places.
            if (array_values(array_intersect(explode(' ', $candidate->key), NameNormalizer::GENERIC_WORDS)) !== $kind) {
                continue;
            }

            $score = NameNormalizer::similarity($core, NameNormalizer::coreKey($candidate->key));

            if ($score > $bestScore) {
                [$best, $bestScore] = [$candidate, $score];
            }
        }

        return $bestScore >= self::FUZZY_THRESHOLD ? $best : null;
    }

    /**
     * "Hildesheim" / "munster" / "Bauhaus Weimar" match "University of Hildesheim" etc. When several
     * institutions share the core (Kiel University vs Kiel UAS) only a plain "University" wins.
     *
     * @param  Collection<int, Institution>  $candidates
     */
    private function matchByCoreName(string $key, Collection $candidates): ?Institution
    {
        $core = NameNormalizer::coreKey($key);

        if ($core === '') {
            return null;
        }

        $inputIsBare = $core === $key;

        // Only a bare name ("Hildesheim") may match by core, so "University of Munich" never lands on TUM.
        $matches = $candidates->filter(fn (Institution $i) => NameNormalizer::coreKey($i->key) === $core
            && ($inputIsBare || NameNormalizer::coreKey($i->key) === $i->key))->values();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $plain = $matches->filter(fn (Institution $i) => in_array($i->key, [$core, "{$core} university", "university {$core}"], true));

        return $plain->count() === 1 ? $plain->first() : null;
    }

    /**
     * A bare name like "Hildesheim" is replaced by the fuller "University of Hildesheim" once seen.
     */
    private function upgradeName(Institution $institution, string $name, string $key): void
    {
        $isBare = NameNormalizer::coreKey($institution->key) === $institution->key;

        if ($isBare && $key !== $institution->key && NameNormalizer::coreKey($key) === $institution->key) {
            $institution->update(['name' => Str::limit($name, 255, ''), 'key' => $key]);
        }
    }

    private function create(string $name, string $key, ?string $country, ?string $city): Institution
    {
        $acronym = NameNormalizer::acronym($name);
        $initials = NameNormalizer::initials($name);

        return Institution::create([
            'name' => Str::limit($name, 255, ''),
            'slug' => $this->uniqueSlug($name),
            'key' => $key,
            'acronym' => $acronym ?? (strlen($initials) >= 3 && strlen($initials) <= 8 ? $initials : null),
            'country' => $country ? Str::limit($country, 80, '') : null,
            'city' => $city ? Str::limit($city, 120, '') : null,
        ]);
    }

    private function rememberAlias(Institution $institution, string $name, string $key): void
    {
        InstitutionAlias::firstOrCreate(['key' => $key], [
            'institution_id' => $institution->id,
            'alias' => Str::limit($name, 255, ''),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 80, '') ?: 'institution';
        $slug = $base;

        for ($i = 2; Institution::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
