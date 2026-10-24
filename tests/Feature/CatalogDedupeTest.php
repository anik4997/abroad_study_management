<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Institution;
use App\Models\StudyList;
use App\Models\User;
use App\Services\CatalogSync;
use App\Services\SpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDedupeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $values  column label => value
     */
    private function publicGermanList(array $values, string $visibility = StudyList::VISIBILITY_PUBLIC): StudyList
    {
        $list = StudyList::createWithTemplate(User::factory()->create(), [
            'title' => 'Shortlist',
            'category' => 'german_uni',
            'visibility' => $visibility,
        ]);

        $columns = $list->columns->keyBy('label');
        $data = [];

        foreach ($values as $label => $value) {
            $data[$columns[$label]->key] = $value;
        }

        $list->rows()->create(['data' => $data]);

        return $list;
    }

    public function test_same_program_written_differently_by_two_users_becomes_one_entry(): void
    {
        $this->publicGermanList(['University' => 'TUM', 'Program' => 'M.Sc. Informatics', 'IELTS' => '6.5', 'NC status' => 'NC-free']);
        $this->publicGermanList(['University' => 'Technical University of Munich', 'Program' => 'Informatics (MSc)', 'Deadline' => '31.05.2027']);

        $this->assertSame(1, Institution::count());
        $this->assertSame(1, CatalogItem::count());

        $item = CatalogItem::first();
        $this->assertSame(2, $item->sources_count);
        $this->assertSame(6.5, $item->ielts);
        $this->assertSame('nc_free', $item->nc_status);
        $this->assertSame('2027-05-31', $item->deadline_date->toDateString());
        $this->assertSame('Germany', $item->country);
    }

    public function test_private_lists_never_reach_the_directory(): void
    {
        $this->publicGermanList(['University' => 'RWTH Aachen', 'Program' => 'Data Science'], StudyList::VISIBILITY_PRIVATE);

        $this->assertSame(0, CatalogItem::published()->count());
        $this->get(route('explore'))->assertOk()->assertDontSee('Data Science');
    }

    public function test_making_a_list_private_or_deleting_it_removes_its_contribution(): void
    {
        $first = $this->publicGermanList(['University' => 'KIT', 'Program' => 'Computer Science']);
        $second = $this->publicGermanList(['University' => 'Karlsruhe Institute of Technology', 'Program' => 'Computer Science']);

        $item = CatalogItem::firstOrFail();
        $this->assertSame(2, $item->sources_count);

        $first->update(['visibility' => StudyList::VISIBILITY_PRIVATE]);
        $this->assertSame(1, $item->fresh()->sources_count);

        app(CatalogSync::class)->deleteList($second);
        $this->assertSame(0, $item->fresh()->sources_count);
        $this->get(route('catalog.item', $item))->assertNotFound();
    }

    public function test_different_universities_in_the_same_city_stay_separate(): void
    {
        $this->publicGermanList(['University' => 'Kiel University', 'Program' => 'Computer Science']);
        $this->publicGermanList(['University' => 'Kiel University of Applied Sciences', 'Program' => 'Computer Science']);
        $this->publicGermanList(['University' => 'TH Köln', 'Program' => 'Computer Science']);
        $this->publicGermanList(['University' => 'TH Owl', 'Program' => 'Computer Science']);

        $this->assertSame(4, Institution::count());
        $this->assertSame(4, CatalogItem::count());
    }

    public function test_spreadsheet_import_creates_rows_and_feeds_the_directory(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, "Programme,Fit ⭐,2027 application window,Requirements\n"
            ."\"EDISS — Engineering of Data-intensive Intelligent Software Systems\",⭐⭐⭐⭐⭐,16 Nov 2026 – 12 Jan 2027,\"Bsc in CSE, IELTS 6.5(6)\"\n"
            ."EDISS,⭐⭐⭐⭐,,\n");

        $importer = app(SpreadsheetImporter::class);
        $data = $importer->read($path, 'csv');
        unlink($path);

        $list = StudyList::createWithTemplate(User::factory()->create(), [
            'title' => 'Erasmus', 'category' => 'erasmus', 'visibility' => StudyList::VISIBILITY_PUBLIC,
        ], withTemplate: false);

        $mapping = [];
        foreach ($data['headers'] as $i => $header) {
            $mapping[$i] = $list->addColumn($header, $importer->guessType($header, array_column($data['rows'], $i)), $importer->guessField($header))->key;
        }

        $this->assertSame(2, $importer->importRows($list, $data['rows'], $mapping));
        $this->assertSame('5', $list->rows()->orderBy('id')->first()->data[$mapping[1]]);

        $item = CatalogItem::published()->sole();
        $this->assertSame(2, $item->sources_count, 'Acronym-only row merges with the full name.');
        $this->assertSame(6.5, $item->ielts);
        $this->assertSame('2027-01-12', $item->deadline_date->toDateString());
    }

    public function test_bare_city_names_match_the_full_university_name(): void
    {
        $this->publicGermanList(['University' => 'Hildesheim', 'Program' => 'Data Analytics']);
        $this->publicGermanList(['University' => 'University of Hildesheim', 'Program' => 'Data Analytics']);
        $this->publicGermanList(['University' => 'munster', 'Program' => 'Data Science']);
        $this->publicGermanList(['University' => 'Universität Münster', 'Program' => 'Data Science']);

        $this->assertSame(2, Institution::count());
        $this->assertEqualsCanonicalizing(['University of Hildesheim', 'Universität Münster'], Institution::pluck('name')->all());
        $this->assertSame(2, CatalogItem::count());
    }

    public function test_generic_name_does_not_swallow_a_different_university_in_the_same_city(): void
    {
        $this->publicGermanList(['University' => 'Technical University of Munich', 'Program' => 'Informatics']);
        $this->publicGermanList(['University' => 'University of Munich', 'Program' => 'Informatics']);

        $this->assertSame(2, Institution::count());
    }
}
