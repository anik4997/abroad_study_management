<?php

namespace Tests\Unit;

use App\Support\NameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function sameInstitutions(): array
    {
        return [
            'case and accents' => ['Technische Universität München', 'technische universitat munchen'],
            'english vs german' => ['Technical University of Munich', 'Technische Universität München'],
            'TU short form' => ['TU München', 'Technical University of Munich'],
            'TUM acronym' => ['TUM', 'Technical University Munich'],
            'word order' => ['University of Bonn', 'Bonn University'],
            'uni short form' => ['Uni Stuttgart', 'University of Stuttgart'],
            'umlaut spelled out' => ['Universitaet Koeln', 'Universität zu Köln'],
            'RWTH' => ['RWTH', 'RWTH Aachen University'],
            'punctuation' => ['FAU Erlangen-Nürnberg', 'FAU Erlangen Nuremberg'],
            'US acronym' => ['MIT', 'Massachusetts Institute of Technology'],
        ];
    }

    #[DataProvider('sameInstitutions')]
    public function test_spelling_variants_of_an_institution_share_a_key(string $a, string $b): void
    {
        $this->assertSame(NameNormalizer::institutionKey($a), NameNormalizer::institutionKey($b));
    }

    public function test_different_institutions_get_different_keys(): void
    {
        $this->assertNotSame(
            NameNormalizer::institutionKey('Kiel University'),
            NameNormalizer::institutionKey('Kiel University of Applied Sciences'),
        );
        $this->assertNotSame(
            NameNormalizer::institutionKey('TU Berlin'),
            NameNormalizer::institutionKey('FU Berlin'),
        );
    }

    public function test_program_keys_ignore_degree_wording(): void
    {
        $this->assertSame(
            NameNormalizer::programKey('M.Sc. Computer Science'),
            NameNormalizer::programKey('Computer Science (MSc)'),
        );
        $this->assertSame(
            NameNormalizer::programKey('Master of Science in Data Science'),
            NameNormalizer::programKey('Data Science'),
        );
        $this->assertNotSame(
            NameNormalizer::programKey('Data Science'),
            NameNormalizer::programKey('Data Engineering'),
        );
    }

    public function test_erasmus_acronym_prefix_is_detected_and_stripped(): void
    {
        $name = 'EDISS — Engineering of Data-intensive Intelligent Software Systems';

        $this->assertSame('EDISS', NameNormalizer::acronym($name));
        $this->assertSame(
            NameNormalizer::programKey('Engineering of Data-intensive Intelligent Software Systems'),
            NameNormalizer::programKey($name),
        );
        $this->assertSame('TUM', NameNormalizer::initials('Technical University of Munich'));
        $this->assertNull(NameNormalizer::acronym('Computer Science'));
    }

    public function test_core_key_drops_generic_words_and_degree_acronyms_are_ignored(): void
    {
        $this->assertSame('hildesheim', NameNormalizer::coreKey(NameNormalizer::institutionKey('University of Hildesheim')));
        $this->assertSame('munster', NameNormalizer::coreKey(NameNormalizer::institutionKey('Universität Münster')));
        $this->assertSame('bauhaus weimar', NameNormalizer::coreKey(NameNormalizer::institutionKey('Bauhaus-Universität Weimar')));
        $this->assertSame('THWS', NameNormalizer::acronym('Technical University of Applied Sciences Würzburg-Schweinfurt — THWS'));
        $this->assertNull(NameNormalizer::acronym('Computer Science (MSc)'));
        $this->assertNull(NameNormalizer::acronym('M.Sc.'));
        $this->assertNull(NameNormalizer::acronym('Heinrich-Heine-Universität Düsseldorf'));
        $this->assertNull(NameNormalizer::acronym('Hildesheim'));
        $this->assertSame('CYBERMACS', NameNormalizer::acronym('CyberMACS — Applied Cybersecurity'));
    }
}
