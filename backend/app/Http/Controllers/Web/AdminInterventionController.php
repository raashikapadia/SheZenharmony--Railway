<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminInterventionController extends Controller
{
    public function index(): View
    {
        return view('admin.interventions.index', [
            'interventions' => Intervention::query()->orderBy('title')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.interventions.form', ['intervention' => new Intervention]);
    }

    public function store(Request $request): RedirectResponse
    {
        Intervention::query()->create($this->validated($request));

        return redirect()->route('admin.interventions.index')->with('status', 'Support content created.');
    }

    public function edit(Intervention $intervention): View
    {
        return view('admin.interventions.form', compact('intervention'));
    }

    public function update(Request $request, Intervention $intervention): RedirectResponse
    {
        $intervention->update($this->validated($request));

        return redirect()->route('admin.interventions.index')->with('status', 'Support content updated.');
    }

    public function destroy(Intervention $intervention): RedirectResponse
    {
        if ($intervention->usages()->exists()) {
            $intervention->update(['is_active' => false]);

            return back()->with('status', 'Support content has usage history and was deactivated instead of deleted.');
        }

        $intervention->delete();

        return back()->with('status', 'Support content deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'content_type' => ['required', 'in:breathing,grounding,mindfulness,relaxation,activity,resource,journaling,affirmation,quiz,motivation,positive_engagement'],
            'stress_level' => ['nullable', 'string', 'max:100'],
            'external_url' => ['nullable', 'url', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
