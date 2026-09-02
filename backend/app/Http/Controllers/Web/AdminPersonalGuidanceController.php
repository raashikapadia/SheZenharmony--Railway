<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonalGuidanceRequest;
use App\Models\PersonalGuidance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPersonalGuidanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'category' => $request->query('category'),
        ];

        $items = PersonalGuidance::query()
            ->when(in_array($filters['type'], PersonalGuidance::TYPES, true), fn ($q) => $q->where('type', $filters['type']))
            ->when(in_array($filters['status'], PersonalGuidance::STATUSES, true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['category'], fn ($q) => $q->where('category', $filters['category']))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.personal-guidance.index', [
            'items' => $items,
            'filters' => $filters,
            'categories' => PersonalGuidance::query()
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.personal-guidance.form', ['guidance' => new PersonalGuidance(['status' => PersonalGuidance::STATUS_DRAFT])]);
    }

    public function store(PersonalGuidanceRequest $request): RedirectResponse
    {
        PersonalGuidance::query()->create($request->validated() + [
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance created.');
    }

    public function edit(PersonalGuidance $personalGuidance): View
    {
        return view('admin.personal-guidance.form', ['guidance' => $personalGuidance]);
    }

    public function update(PersonalGuidanceRequest $request, PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->update($request->validated());

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance updated.');
    }

    public function destroy(PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->delete();

        return back()->with('status', 'Guidance deleted.');
    }
}
