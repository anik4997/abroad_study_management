<?php

namespace App\Http\Controllers;

use App\Models\StudyList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'ownLists' => $user->studyLists()->withCount('rows')->latest('updated_at')->get(),
            'sharedLists' => $user->sharedLists()->with('user')->withCount('rows')->latest('updated_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(config('studyhub.categories')))],
            'visibility' => ['required', Rule::in([StudyList::VISIBILITY_PRIVATE, StudyList::VISIBILITY_PUBLIC])],
            'template' => ['nullable', 'boolean'],
        ]);

        $list = StudyList::createWithTemplate(
            $request->user(),
            collect($data)->only(['title', 'description', 'category', 'visibility'])->all(),
            $request->boolean('template', true),
        );

        return redirect()->route('lists.show', $list)->with('status', 'List created. Add rows, or import from Excel.');
    }
}
