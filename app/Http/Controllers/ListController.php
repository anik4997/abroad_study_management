<?php

namespace App\Http\Controllers;

use App\Models\StudyList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListController extends Controller
{
    public function index(Request $request): View
    {
        $lists = StudyList::query()
            ->where('visibility', StudyList::VISIBILITY_PUBLIC)
            ->with('user:id,name,username')
            ->withCount('rows')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.addcslashes((string) $request->string('q'), '%_\\').'%'))
            ->latest('updated_at')
            ->paginate(24)
            ->withQueryString();

        return view('lists.index', ['lists' => $lists]);
    }

    public function show(StudyList $list): View
    {
        Gate::authorize('view', $list);

        return view('lists.show', ['list' => $list->load('user')]);
    }

    public function export(StudyList $list, string $format): StreamedResponse
    {
        Gate::authorize('view', $list);
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $list->load('columns');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', '', $list->title) ?: 'List', 31, ''));

        $sheet->fromArray([$list->columns->pluck('label')->all()], null, 'A1');
        $line = 2;

        $list->rows()->orderBy('position')->orderBy('id')->chunk(500, function ($rows) use ($list, $sheet, &$line): void {
            foreach ($rows as $row) {
                $values = $list->columns->map(fn ($column) => $this->safeCell($row->value($column->key)))->all();
                $sheet->fromArray([$values], null, 'A'.$line++, true);
            }
        });

        $filename = (Str::slug($list->title) ?: 'list').'.'.$format;
        $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);

        if ($writer instanceof Csv) {
            $writer->setUseBOM(true);
        }

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Prevent spreadsheet formula injection when other users open the export.
     */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
