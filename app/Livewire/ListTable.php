<?php

namespace App\Livewire;

use App\Models\ListColumn;
use App\Models\ListRow;
use App\Models\StudyList;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Spreadsheet-like view/editor for one list: inline cell editing, rows, and column management.
 */
class ListTable extends Component
{
    use WithPagination;

    #[Locked]
    public int $listId;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortKey = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** @var array<string, string> column key => exact value */
    #[Url(as: 'f', except: [])]
    public array $filters = [];

    public bool $showColumnForm = false;

    #[Locked]
    public ?int $editingColumnId = null;

    /** @var array{label: string, type: string, field: string, options: string} */
    public array $columnForm = ['label' => '', 'type' => 'text', 'field' => '', 'options' => ''];

    public function mount(StudyList $list): void
    {
        Gate::authorize('view', $list);
        $this->listId = $list->id;
    }

    #[Computed]
    public function list(): StudyList
    {
        return StudyList::with('columns')->findOrFail($this->listId);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return (bool) auth()->user()?->can('edit', $this->list);
    }

    #[On('list-imported')]
    public function refreshList(): void
    {
        unset($this->list);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->filters = array_filter($this->filters, fn ($v) => $v !== '' && $v !== null);
        $this->resetPage();
    }

    public function sortBy(string $key): void
    {
        if (! $this->list->columns->contains('key', $key)) {
            return;
        }

        if ($this->sortKey === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            [$this->sortKey, $this->sortDir] = [$key, 'asc'];
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filters', 'sortKey', 'sortDir');
        $this->resetPage();
    }

    public function updateCell(int $rowId, string $key, mixed $value): void
    {
        $this->authorizeEdit();

        $column = $this->list->columns->firstWhere('key', $key);
        abort_unless($column instanceof ListColumn, 404);

        $row = $this->findRow($rowId);
        $data = $row->data;
        $clean = $this->cleanValue($column, is_scalar($value) ? (string) $value : '');

        if ($clean === '') {
            unset($data[$key]);
        } else {
            $data[$key] = $clean;
        }

        $row->update(['data' => $data]);
        $this->resetErrorBag();
    }

    public function addRow(): void
    {
        $this->authorizeEdit();

        $this->list->rows()->create([
            'data' => [],
            'position' => (int) $this->list->rows()->min('position') - 1,
        ]);

        $this->reset('search', 'filters', 'sortKey');
        $this->resetPage();
    }

    public function duplicateRow(int $rowId): void
    {
        $this->authorizeEdit();
        $row = $this->findRow($rowId);

        $this->list->rows()->create(['data' => $row->data, 'position' => $row->position]);
    }

    public function deleteRow(int $rowId): void
    {
        $this->authorizeEdit();
        $this->findRow($rowId)->delete();
    }

    public function openColumnForm(?int $columnId = null): void
    {
        $this->authorizeEdit();
        $this->resetErrorBag();
        $column = $columnId ? $this->findColumn($columnId) : null;

        $this->editingColumnId = $column?->id;
        $this->columnForm = [
            'label' => $column->label ?? '',
            'type' => $column->type ?? 'text',
            'field' => $column->field ?? '',
            'options' => implode(', ', $column->options ?? []),
        ];
        $this->showColumnForm = true;
    }

    public function saveColumn(): void
    {
        $this->authorizeEdit();

        $data = $this->validate([
            'columnForm.label' => ['required', 'string', 'max:120'],
            'columnForm.type' => ['required', Rule::in(array_keys(config('studyhub.column_types')))],
            'columnForm.field' => ['nullable', Rule::in(array_keys(config('studyhub.fields')))],
            'columnForm.options' => ['nullable', 'string', 'max:2000'],
        ])['columnForm'];

        $options = $data['type'] === 'select'
            ? array_values(array_unique(array_filter(array_map(fn ($o) => Str::limit(trim($o), 80, ''), explode(',', (string) $data['options'])))))
            : null;

        if ($data['field'] && $this->list->columns->where('field', $data['field'])->where('id', '!=', $this->editingColumnId)->isNotEmpty()) {
            throw ValidationException::withMessages(['columnForm.field' => 'Another column already uses this meaning.']);
        }

        if ($this->editingColumnId) {
            $this->findColumn($this->editingColumnId)->update([
                'label' => trim($data['label']),
                'type' => $data['type'],
                'field' => $data['field'] ?: null,
                'options' => $options,
            ]);
        } else {
            abort_if($this->list->columns->count() >= 60, 422, 'A list can have at most 60 columns.');
            $this->list->addColumn($data['label'], $data['type'], $data['field'] ?: null, $options);
        }

        $this->showColumnForm = false;
        $this->editingColumnId = null;
        unset($this->list);
    }

    public function deleteColumn(int $columnId): void
    {
        $this->authorizeEdit();
        $column = $this->findColumn($columnId);
        $key = $column->key;

        $column->delete();

        ListRow::withoutEvents(function () use ($key): void {
            $this->list->rows()->whereNotNull("data->{$key}")->eachById(function (ListRow $row) use ($key): void {
                $data = $row->data;
                unset($data[$key]);
                $row->update(['data' => $data]);
            });
        });

        unset($this->filters[$key]);
        $this->sortKey = $this->sortKey === $key ? '' : $this->sortKey;
        $this->showColumnForm = false;
        unset($this->list);
    }

    public function moveColumn(int $columnId, int $direction): void
    {
        $this->authorizeEdit();
        $columns = $this->list->columns->values();
        $index = $columns->search(fn (ListColumn $c) => $c->id === $columnId);
        $swapWith = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($swapWith === null || ! isset($columns[$swapWith])) {
            return;
        }

        foreach ($columns as $position => $column) {
            $column->position = $position;
        }

        [$columns[$index]->position, $columns[$swapWith]->position] = [$swapWith, $index];

        foreach ($columns as $column) {
            if ($column->isDirty('position')) {
                $column->saveQuietly();
            }
        }

        unset($this->list);
    }

    public function render(): View
    {
        $list = $this->list;
        $keys = $list->columns->pluck('key');

        $rows = $list->rows()
            ->when(trim($this->search) !== '', function (Builder $query) use ($keys): void {
                $term = '%'.addcslashes(trim($this->search), '%_\\').'%';
                $query->where(function (Builder $query) use ($keys, $term): void {
                    foreach ($keys as $key) {
                        $query->orWhere("data->{$key}", 'like', $term);
                    }
                });
            })
            ->when($this->filters, function (Builder $query) use ($keys): void {
                foreach ($this->filters as $key => $value) {
                    if ($keys->contains($key)) {
                        $query->where("data->{$key}", (string) $value);
                    }
                }
            })
            ->when($this->sortKey !== '' && $keys->contains($this->sortKey), function (Builder $query): void {
                $column = $this->list->columns->firstWhere('key', $this->sortKey);
                $path = "data->{$this->sortKey}";
                $direction = $this->sortDir === 'desc' ? 'desc' : 'asc';
                $query->orderByRaw("{$this->jsonExpression($path)} IS NULL")
                    ->orderBy(in_array($column?->type, ['number', 'rating'], true) ? $this->numericSort($path) : $path, $direction);
            })
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(50);

        return view('livewire.list-table', [
            'list' => $list,
            'rows' => $rows,
            'filterableColumns' => $list->columns->filter(fn (ListColumn $c) => in_array($c->type, ['select', 'checkbox', 'rating'], true)),
        ]);
    }

    private function cleanValue(ListColumn $column, string $value): string
    {
        $value = trim(Str::limit($value, 5000, ''));

        if ($value === '') {
            return '';
        }

        $error = fn (string $message) => throw ValidationException::withMessages(['cell' => "{$column->label}: {$message}"]);

        return match ($column->type) {
            'number' => is_numeric(str_replace(',', '.', $value)) ? str_replace(',', '.', $value) : $error('enter a number.'),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $error('enter a valid date.'),
            'url' => filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? $value : $error('enter a link starting with http:// or https://'),
            'rating' => (string) max(0, min(5, (int) $value)),
            'checkbox' => in_array($value, ['1', 'true', 'on'], true) ? '1' : '',
            default => $value,
        };
    }

    private function jsonExpression(string $path): string
    {
        return $this->list->rows()->getQuery()->getGrammar()->wrap($path);
    }

    private function numericSort(string $path): Expression
    {
        return DB::raw("CAST({$this->jsonExpression($path)} AS DECIMAL(12,2))");
    }

    private function findRow(int $rowId): ListRow
    {
        return $this->list->rows()->findOrFail($rowId);
    }

    private function findColumn(int $columnId): ListColumn
    {
        return $this->list->columns()->findOrFail($columnId);
    }

    private function authorizeEdit(): void
    {
        abort_unless($this->canEdit, 403);
    }
}
