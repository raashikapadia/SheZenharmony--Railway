<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\StressScoreBand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminInterventionController extends Controller
{
    public function index(): View
    {
        return view('admin.interventions.index', [
            'interventions' => Intervention::query()
                ->with(['recommendations' => fn ($query) => $query->where('is_active', true), 'recommendations.scoreBand'])
                ->orderBy('title')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.interventions.form', [
            'intervention' => new Intervention,
            'bands' => $this->bands(),
            'selectedBandIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $intervention = Intervention::query()->create($this->validated($request));
            $this->syncRecommendations($intervention, $request);
        });

        return redirect()->route('admin.interventions.index')->with('status', 'Support content created.');
    }

    public function edit(Intervention $intervention): View
    {
        return view('admin.interventions.form', [
            'intervention' => $intervention,
            'bands' => $this->bands(),
            'selectedBandIds' => $intervention->recommendations()
                ->where('is_active', true)
                ->pluck('stress_score_band_id')
                ->all(),
        ]);
    }

    public function update(Request $request, Intervention $intervention): RedirectResponse
    {
        DB::transaction(function () use ($request, $intervention): void {
            $intervention->update($this->validated($request));
            $this->syncRecommendations($intervention, $request);
        });

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
            'all_levels' => ['nullable', 'boolean'],
            'recommended_band_ids' => ['nullable', 'array'],
            'recommended_band_ids.*' => ['integer', 'exists:stress_score_bands,id'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return collect($data)->except(['all_levels', 'recommended_band_ids'])->all();
    }

    private function syncRecommendations(Intervention $intervention, Request $request): void
    {
        $bandIds = $request->boolean('all_levels')
            ? collect()
            : collect($request->input('recommended_band_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

        $intervention->recommendations()
            ->when($bandIds->isNotEmpty(), fn ($query) => $query->whereNotIn('stress_score_band_id', $bandIds->all()))
            ->update(['is_active' => false]);

        foreach ($bandIds as $bandId) {
            $intervention->recommendations()->updateOrCreate(
                ['stress_score_band_id' => $bandId],
                ['is_active' => true, 'priority' => 0],
            );
        }
    }

    /** Active score bands, labelled with their questionnaire for context. */
    private function bands()
    {
        return StressScoreBand::query()
            ->where('is_active', true)
            ->with('questionnaire:id,title')
            ->orderBy('questionnaire_id')
            ->orderBy('position')
            ->get();
    }
}
