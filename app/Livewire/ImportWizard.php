<?php

namespace App\Livewire;

use App\Models\StudyList;
use App\Services\SpreadsheetImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Two-step import: (1) upload a file or paste a public Google Sheets link, (2) map each
 * spreadsheet column to a new column, an existing column, or skip. Works for a new list
 * (from the dashboard) or to append rows to an existing one.
 */
class ImportWizard extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $listId = null;

    /** @var TemporaryUploadedFile|null */
    public $upload;

    public string $googleUrl = '';

    #[Locked]
    public ?string $storedPath = null;

    #[Locked]
    public string $extension = 'xlsx';

    /** @var list<string> */
    #[Locked]
    public array $sheets = [];

    public int $sheetIndex = 0;

    /** @var list<string> */
    #[Locked]
    public array $headers = [];

    /** @var list<list<string>> */
    #[Locked]
    public array $samples = [];

    #[Locked]
    public int $rowCount = 0;

    /** @var array<int, array{target: string, label: string, type: string, field: string}> */
    public array $mapping = [];

    public string $title = '';

    public string $category = 'german_uni';

    public string $visibility = StudyList::VISIBILITY_PRIVATE;

    public function mount(?StudyList $list = null): void
    {
        if ($list?->exists) {
            Gate::authorize('edit', $list);
            $this->listId = $list->id;
            $this->category = $list->category;
        }
    }

    public function updatedUpload(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'max:'.config('studyhub.import.max_kb'), 'mimes:xlsx,xls,csv,ods,txt'],
        ]);

        $extension = Str::lower($this->upload->getClientOriginalExtension()) ?: 'xlsx';
        $this->title = $this->title ?: Str::limit(pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME), 120, '');
        $this->storeSource($this->upload->get(), $extension === 'txt' ? 'csv' : $extension);
        $this->upload = null;
    }

    public function fetchGoogleSheet(): void
    {
        $this->validate(['googleUrl' => ['required', 'string', 'max:500']]);

        if (! preg_match('#^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]{20,80})#', trim($this->googleUrl), $match)) {
            throw ValidationException::withMessages(['googleUrl' => 'Paste a link like https://docs.google.com/spreadsheets/d/…']);
        }

        $gid = preg_match('/[#&?]gid=(\d+)/', $this->googleUrl, $gidMatch) ? '&gid='.$gidMatch[1] : '';

        try {
            $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 5]])
                ->get("https://docs.google.com/spreadsheets/d/{$match[1]}/export?format=xlsx{$gid}");
        } catch (Throwable) {
            throw ValidationException::withMessages(['googleUrl' => 'Could not reach Google Sheets. Try again or upload the file.']);
        }

        $isSpreadsheet = str_contains((string) $response->header('Content-Type'), 'spreadsheetml');

        if (! $response->successful() || ! $isSpreadsheet) {
            throw ValidationException::withMessages(['googleUrl' => 'This sheet is not public. In Google Sheets choose Share → "Anyone with the link can view", or download it as .xlsx and upload.']);
        }

        if (strlen($response->body()) > config('studyhub.import.max_kb') * 1024) {
            throw ValidationException::withMessages(['googleUrl' => 'The sheet is too large to import.']);
        }

        $this->storeSource($response->body(), 'xlsx');
    }

    public function updatedSheetIndex(): void
    {
        if ($this->storedPath) {
            $this->parse();
        }
    }

    public function startOver(): void
    {
        $this->discardSource();
        $this->reset('headers', 'samples', 'rowCount', 'mapping', 'sheets', 'sheetIndex', 'googleUrl');
    }

    public function import(SpreadsheetImporter $importer): void
    {
        abort_unless($this->storedPath, 422);

        $rules = [
            'mapping.*.target' => ['required', 'string'],
            'mapping.*.label' => ['nullable', 'string', 'max:120'],
            'mapping.*.type' => ['required', Rule::in(array_keys(config('studyhub.column_types')))],
            'mapping.*.field' => ['nullable', Rule::in(array_keys(config('studyhub.fields')))],
        ];

        if (! $this->listId) {
            $rules += [
                'title' => ['required', 'string', 'max:120'],
                'category' => ['required', Rule::in(array_keys(config('studyhub.categories')))],
                'visibility' => ['required', Rule::in([StudyList::VISIBILITY_PRIVATE, StudyList::VISIBILITY_PUBLIC])],
            ];
        }

        $this->validate($rules);

        $list = $this->listId
            ? StudyList::findOrFail($this->listId)
            : StudyList::createWithTemplate(auth()->user(), [
                'title' => $this->title,
                'category' => $this->category,
                'visibility' => $this->visibility,
            ], withTemplate: false);

        Gate::authorize('edit', $list);

        $existingKeys = $list->columns()->pluck('key')->all();
        $usedFields = $list->columns()->whereNotNull('field')->pluck('field')->all();
        $columnMap = [];

        foreach ($this->mapping as $index => $map) {
            if ($map['target'] === 'skip') {
                continue;
            }

            if ($map['target'] === 'new') {
                $field = $map['field'] && ! in_array($map['field'], $usedFields, true) ? $map['field'] : null;
                $usedFields[] = $field;
                $columnMap[$index] = $list->addColumn($map['label'] ?: $this->headers[$index], $map['type'], $field)->key;
            } elseif (in_array($map['target'], $existingKeys, true)) {
                $columnMap[$index] = $map['target'];
            }
        }

        $data = $importer->read($this->absolutePath(), $this->extension, $this->sheetIndex);
        $count = $importer->importRows($list, $data['rows'], $columnMap);

        $this->discardSource();

        if ($this->listId) {
            $this->startOver();
            $this->dispatch('list-imported');
            session()->flash('import_status', "Imported {$count} rows.");

            return;
        }

        session()->flash('status', "Imported {$count} rows into “{$list->title}”.");
        $this->redirectRoute('lists.show', $list);
    }

    public function render(): View
    {
        $list = $this->listId ? StudyList::with('columns')->find($this->listId) : null;

        return view('livewire.import-wizard', ['list' => $list]);
    }

    private function storeSource(string $contents, string $extension): void
    {
        $this->discardSource();
        $this->extension = in_array($extension, ['xlsx', 'xls', 'csv', 'ods'], true) ? $extension : 'xlsx';
        $this->storedPath = 'imports/'.Str::uuid().'.'.$this->extension;
        Storage::disk('local')->put($this->storedPath, $contents);
        $this->sheetIndex = 0;
        $this->parse();
    }

    private function parse(): void
    {
        try {
            $data = app(SpreadsheetImporter::class)->read($this->absolutePath(), $this->extension, $this->sheetIndex);
        } catch (Throwable) {
            $this->discardSource();
            throw ValidationException::withMessages(['upload' => 'Could not read this file. Save it as .xlsx or .csv and try again.']);
        }

        if ($data['headers'] === []) {
            $this->discardSource();
            throw ValidationException::withMessages(['upload' => 'The sheet looks empty — the first row should contain column names.']);
        }

        $importer = app(SpreadsheetImporter::class);
        $existing = $this->listId ? StudyList::with('columns')->find($this->listId)?->columns : collect();

        $this->sheets = $data['sheets'];
        $this->headers = $data['headers'];
        $this->samples = array_slice($data['rows'], 0, 3);
        $this->rowCount = count($data['rows']);
        $this->mapping = [];
        $guessedFields = [];

        foreach ($this->headers as $index => $header) {
            $samples = array_column(array_slice($data['rows'], 0, 30), $index);
            $field = $importer->guessField($header);
            $field = in_array($field, $guessedFields, true) ? null : $field;
            $guessedFields[] = $field;
            $match = $existing->first(fn ($c) => Str::lower($c->label) === Str::lower($header) || ($field && $c->field === $field));

            $type = $importer->guessType($header, $samples);
            $type = in_array($field, ['university', 'program', 'country', 'city'], true) && $type === 'longtext' ? 'text' : $type;

            $this->mapping[$index] = [
                'target' => $match?->key ?? 'new',
                'label' => $header,
                'type' => $type,
                'field' => (string) $field,
            ];
        }
    }

    private function absolutePath(): string
    {
        return Storage::disk('local')->path((string) $this->storedPath);
    }

    private function discardSource(): void
    {
        if ($this->storedPath) {
            Storage::disk('local')->delete($this->storedPath);
            $this->storedPath = null;
        }
    }
}
