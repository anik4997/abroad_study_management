<?php

namespace Tests\Unit;

use App\Support\FieldParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FieldParserTest extends TestCase
{
    public function test_ielts_is_read_from_a_score_or_from_requirement_text(): void
    {
        $this->assertSame(6.5, FieldParser::ielts('6.5'));
        $this->assertSame(6.5, FieldParser::ielts('IELTS 6.5(6)'));
        $this->assertSame(7.0, FieldParser::ielts('Bsc in cse (180ects), IELTS 7', requireLabel: true));
        $this->assertNull(FieldParser::ielts('Bsc in cse (180ects)', requireLabel: true));
    }

    public function test_nc_status_variants(): void
    {
        $this->assertSame('nc_free', FieldParser::ncStatus('NC free'));
        $this->assertSame('nc_free', FieldParser::ncStatus('nc-free'));
        $this->assertSame('nc_free', FieldParser::ncStatus('zulassungsfrei'));
        $this->assertSame('nc', FieldParser::ncStatus('NC'));
        $this->assertSame('nc', FieldParser::ncStatus('Yes'));
        $this->assertSame('unknown', FieldParser::ncStatus(''));
    }

    public function test_deadline_takes_the_last_date_of_a_window(): void
    {
        $this->assertSame('2027-01-12', FieldParser::deadlineDate('16 Nov 2026 – 12 Jan 2027')?->toDateString());
        $this->assertSame('2027-07-15', FieldParser::deadlineDate('15.07.2027')?->toDateString());
        $this->assertSame('2027-03-01', FieldParser::deadlineDate('2027-03-01')?->toDateString());
        $this->assertNull(FieldParser::deadlineDate('2028 window - Not declared yet'));
    }

    public function test_tuition_amounts(): void
    {
        $this->assertSame(0.0, FieldParser::amount('Free'));
        $this->assertSame(0.0, FieldParser::amount('No tuition fee'));
        $this->assertSame(1500.0, FieldParser::amount('€1,500 per semester'));
        $this->assertSame(1500.0, FieldParser::amount('1.500 €'));
        $this->assertNull(FieldParser::amount(''));
    }

    public function test_gre_portal_and_normalised_labels(): void
    {
        $this->assertSame('no', FieldParser::gre('Not required'));
        $this->assertSame('optional', FieldParser::gre('optional'));
        $this->assertSame('required', FieldParser::gre('GRE required'));
        $this->assertSame('vpd', FieldParser::portal('VPD via uni-assist'));
        $this->assertSame('uni-assist', FieldParser::portal('Uni Assist'));
        $this->assertSame('Master', FieldParser::degree('M.Sc.'));
        $this->assertSame('English & German', FieldParser::language('english/german'));
        $this->assertSame('Winter & Summer', FieldParser::intake('WS & SS'));
        $this->assertSame('Germany', FieldParser::country('deutschland'));
        $this->assertSame('https://example.org', FieldParser::url('example.org'));
        $this->assertNull(FieldParser::url('javascript:alert(1)'));
    }

    public function test_nc_phrases_from_real_sheets(): void
    {
        foreach (['Open admission, aptitude assessment', 'No NC (aptitude/eligibility requirements)', 'No admission restriction',
            'Non restricted', 'Generally admission-free, subject-specific', 'No NC indicated, case-by-case', 'NC-free, Restricted'] as $text) {
            $this->assertSame('nc_free', FieldParser::ncStatus($text), $text);
        }

        foreach (['Admission restricted', 'Restricted (Orts-NC)', 'Locally restricted', 'Restricted/selection'] as $text) {
            $this->assertSame('nc', FieldParser::ncStatus($text), $text);
        }

        $this->assertSame('unknown', FieldParser::ncStatus('Not simply NC-free; strict eligibility'));
        $this->assertSame('unknown', FieldParser::ncStatus('Depending on capacity'));
    }

    public function test_yearless_deadlines_resolve_to_the_next_occurrence(): void
    {
        $today = CarbonImmutable::create(2026, 10, 24);

        $this->assertSame('2026-12-15', FieldParser::deadlineDate('19 sep - 15 dec', $today)?->toDateString());
        $this->assertSame('2026-12-01', FieldParser::deadlineDate('10sep - 1dec', $today)?->toDateString());
        $this->assertSame('2027-01-15', FieldParser::deadlineDate('1st Nov - 15 Jan', $today)?->toDateString());
        $this->assertSame('2027-03-31', FieldParser::deadlineDate('1Nov - 31march', $today)?->toDateString());
        $this->assertSame('2027-10-15', FieldParser::deadlineDate('15sep - 15 oct', $today)?->toDateString(), 'Passed this year, so next year.');
        $this->assertSame('2026-11-30', FieldParser::deadlineDate('2026-11-30', $today)?->toDateString());
        $this->assertSame('2026-11-16', FieldParser::deadlineDate('16 Nov 2026', $today)?->toDateString());
        $this->assertSame('2027-01-05', FieldParser::deadlineDate('Jan 5, 2027', $today)?->toDateString());
        $this->assertNull(FieldParser::deadlineDate('Only winter', $today));
    }

    public function test_portal_is_read_from_a_misused_application_fee_column(): void
    {
        $this->assertSame('direct', FieldParser::portal('', 'Direct potal application'));
        $this->assertSame('uni-assist', FieldParser::portal('', 'Uni Assist + site'));
        $this->assertSame('vpd', FieldParser::portal('', 'HHU application; VPD may be needed'));
        $this->assertNull(FieldParser::applicationFee('Direct potal application'));
        $this->assertSame('portal (60 euro fee)', FieldParser::applicationFee('portal (60 euro fee)'));
        $this->assertNull(FieldParser::ielts('c1'));
    }
}
