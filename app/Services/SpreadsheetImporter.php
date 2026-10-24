<?php

namespace App\Services;

use App\Models\ListRow;
use App\Models\StudyList;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

/**
 * Reads .xlsx / .xls / .ods / .csv files into header + row arrays and writes them into a list.
 */
class SpreadsheetImporter
{
    public function __construct(private CatalogSync $catalog) {}

    /**
     * @return array{sheets: list<string>, headers: list<string>, rows: list<list<string>>}
     */
    public function read(string $path, string $extension, int $sheetIndex = 0): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (Throwable) {
            $reader = $extension === 'csv' ? new Csv : throw new RuntimeException('Unsupported spreadsheet file.');
        }

        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
        }

        $sheetNames = method_exists($reader, 'listWorksheetNames') ? $reader->listWorksheetNames($path) : ['Sheet1'];
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(min($sheetIndex, $spreadsheet->getSheetCount() - 1));

        $rows = [];
        $maxRows = (int) config('studyhub.import.max_rows') + 50;

        foreach ($sheet->getRowIterator() as $index => $row) {
            if ($index > $maxRows) {
                break;
            }

            $cells = [];

            foreach ($row->getCellIterator() as $cell) {
                try {
                    $value = $cell->getCalculatedValue();
                } catch (Throwable) {
                    $value = $cell->getValue();
                }

                if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } elseif (is_float($value)) {
                    $value = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
                } elseif (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (! is_scalar($value) && $value !== null) {
                    $value = method_exists($value, '__toString') ? (string) $value : '';
                }

                $cells[] = trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '');
            }

            $rows[] = $cells;
        }

        $spreadsheet->disconnectWorksheets();

        // Header = first row that has at least two non-empty cells.
        while ($rows && count(array_filter($rows[0], fn ($v) => $v !== '')) < 2) {
            array_shift($rows);
        }

        $headers = array_shift($rows) ?? [];
        $width = count($headers);

        while ($width > 0 && trim($headers[$width - 1]) === '' && ! $this->columnHasData($rows, $width - 1)) {
            $width--;
        }

        $headers = array_slice($headers, 0, $width);

        foreach ($headers as $i => $header) {
            $headers[$i] = $header !== '' ? Str::limit($header, 120, '') : 'Column '.($i + 1);
        }

        $rows = array_values(array_filter(
            array_map(fn (array $row) => array_pad(array_slice($row, 0, $width), $width, ''), $rows),
            fn (array $row) => implode('', $row) !== '',
        ));

        return [
            'sheets' => array_values($sheetNames),
            'headers' => $headers,
            'rows' => array_slice($rows, 0, (int) config('studyhub.import.max_rows')),
        ];
    }

    /**
     * Best guess of the canonical field for a spreadsheet header.
     */
    public function guessField(string $header): ?string
    {
        $normalized = $this->normalizeHeader($header);

        if (in_array($normalized, config('studyhub.private_aliases'), true)) {
            return null;
        }

        foreach (config('studyhub.fields') as $field => $definition) {
            if (in_array($normalized, $definition['aliases'], true)) {
                return $field;
            }
        }

        foreach (config('studyhub.fields') as $field => $definition) {
            foreach ($definition['aliases'] as $alias) {
                if (strlen($alias) >= 4 && str_contains($normalized, $alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $samples
     */
    public function guessType(string $header, array $samples): string
    {
        $normalized = $this->normalizeHeader($header);
        $samples = array_values(array_filter($samples, fn ($v) => $v !== ''));

        if (str_contains($normalized, 'link') || str_contains($normalized, 'url') || str_contains($normalized, 'website')) {
            return 'url';
        }

        if ($samples === []) {
            return 'text';
        }

        $all = fn (callable $test): bool => count(array_filter($samples, $test)) === count($samples);

        return match (true) {
            $all(fn ($v) => (bool) preg_match('#^https?://#i', $v)) => 'url',
            $all(fn ($v) => (bool) preg_match('/^[⭐★☆\x{FE0F}\s]+$/u', $v)) => 'rating',
            $all(fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) => 'date',
            $all(fn ($v) => is_numeric(str_replace(',', '.', $v))) => 'number',
            max(array_map('mb_strlen', $samples)) > 80 => 'longtext',
            default => 'text',
        };
    }

    /**
     * Append rows to a list. $mapping maps spreadsheet column index => list column key.
     *
     * @param  list<list<string>>  $rows
     * @param  array<int, string>  $mapping
     */
    public function importRows(StudyList $list, array $rows, array $mapping): int
    {
        $list->load('columns');
        $types = $list->columns->pluck('type', 'key');
        $position = (int) $list->rows()->max('position');
        $count = 0;

        ListRow::withoutEvents(function () use ($list, $rows, $mapping, $types, &$position, &$count): void {
            foreach ($rows as $cells) {
                $data = [];

                foreach ($mapping as $index => $key) {
                    $value = trim((string) ($cells[$index] ?? ''));

                    if ($value !== '' && $types->has($key)) {
                        $data[$key] = $this->castCell($value, $types[$key]);
                    }
                }

                if ($data !== []) {
                    $list->rows()->create(['data' => $data, 'position' => ++$position]);
                    $count++;
                }
            }
        });

        if ($list->isPublic()) {
            $this->catalog->syncList($list);
        }

        return $count;
    }

    private function castCell(string $value, string $type): string
    {
        $value = Str::limit($value, 5000, '');

        return match ($type) {
            'rating' => preg_match('/^[⭐★☆\x{FE0F}\s]+$/u', $value)
                ? (string) preg_match_all('/[⭐★]/u', $value)
                : (string) max(0, min(5, (int) $value)),
            'checkbox' => in_array(Str::lower($value), ['1', 'yes', 'true', 'y', 'x', '✓', '✔'], true) ? '1' : '',
            default => $value,
        };
    }

    private function normalizeHeader(string $header): string
    {
        $value = Str::lower(Str::ascii($header));
        $value = preg_replace('/[^a-z0-9\/]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function columnHasData(array $rows, int $index): bool
    {
        foreach ($rows as $row) {
            if (($row[$index] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }
}
