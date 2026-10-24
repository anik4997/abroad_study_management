<?php

namespace Tests\Feature;

use App\Livewire\ListSettings;
use App\Livewire\ListTable;
use App\Models\StudyList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_list_is_hidden_from_guests_and_other_users(): void
    {
        $list = StudyList::factory()->create();

        $this->get(route('lists.show', $list))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('lists.show', $list))->assertForbidden();
        $this->actingAs($list->user)->get(route('lists.show', $list))->assertOk();
    }

    public function test_public_list_is_visible_to_guests_but_not_editable(): void
    {
        $list = StudyList::factory()->public()->create();
        $list->addColumn('University', 'text', 'university');

        $this->get(route('lists.show', $list))->assertOk();

        Livewire::actingAs(User::factory()->create())
            ->test(ListTable::class, ['list' => $list])
            ->call('addRow')
            ->assertForbidden();
    }

    public function test_owner_can_share_a_private_list_with_view_or_edit_rights(): void
    {
        $list = StudyList::factory()->create();
        $column = $list->addColumn('University', 'text', 'university');
        $friend = User::factory()->create(['username' => 'friend']);

        Livewire::actingAs($list->user)
            ->test(ListSettings::class, ['list' => $list])
            ->set('shareUsername', 'FRIEND')
            ->call('share')
            ->assertHasNoErrors();

        $this->actingAs($friend)->get(route('lists.show', $list))->assertOk();

        Livewire::actingAs($friend)->test(ListTable::class, ['list' => $list])->call('addRow')->assertForbidden();

        $list->sharedWith()->updateExistingPivot($friend->id, ['can_edit' => true]);
        Livewire::actingAs($friend)->test(ListTable::class, ['list' => $list])->call('addRow')->assertOk();

        $row = $list->rows()->first();
        Livewire::actingAs($friend)->test(ListTable::class, ['list' => $list])
            ->call('updateCell', $row->id, $column->key, 'TU Berlin');
        $this->assertSame('TU Berlin', $row->fresh()->data[$column->key]);
    }

    public function test_only_owner_can_open_settings(): void
    {
        $list = StudyList::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(ListSettings::class, ['list' => $list])
            ->assertForbidden();
    }

    public function test_columns_can_be_added_renamed_and_removed(): void
    {
        $list = StudyList::factory()->create();
        $row = $list->rows()->create(['data' => []]);

        $component = Livewire::actingAs($list->user)->test(ListTable::class, ['list' => $list])
            ->call('openColumnForm')
            ->set('columnForm.label', 'Scholarship amount')
            ->set('columnForm.type', 'text')
            ->call('saveColumn')
            ->assertHasNoErrors();

        $column = $list->columns()->firstWhere('label', 'Scholarship amount');
        $this->assertNotNull($column);

        $component->call('updateCell', $row->id, $column->key, '€1,200/month')
            ->call('openColumnForm', $column->id)
            ->set('columnForm.label', 'Stipend')
            ->call('saveColumn');
        $this->assertSame('Stipend', $column->fresh()->label);

        $component->call('deleteColumn', $column->id);
        $this->assertNull($column->fresh());
        $this->assertArrayNotHasKey($column->key, $row->fresh()->data);
    }

    public function test_invalid_cell_values_are_rejected_by_column_type(): void
    {
        $list = StudyList::factory()->create();
        $column = $list->addColumn('IELTS', 'number', 'ielts');
        $row = $list->rows()->create(['data' => []]);

        Livewire::actingAs($list->user)->test(ListTable::class, ['list' => $list])
            ->call('updateCell', $row->id, $column->key, 'abc')
            ->assertHasErrors('cell');
    }
}
