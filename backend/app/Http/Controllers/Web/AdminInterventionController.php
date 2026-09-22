<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\InterventionUsage;
use App\Models\StressScoreBand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminInterventionController extends Controller
{
    public function index(): View
    {
        $configuration = $this->configuration();
        $isGamesSection = $configuration['route'] === 'admin.positive-engagement.games';
        $query = Intervention::query()
            ->whereIn('content_type', array_keys($configuration['contentTypes']))
            ->with(['recommendations' => fn ($query) => $query->where('is_active', true), 'recommendations.scoreBand'])
            ->when($isGamesSection, function ($query): void {
                $query->with(['usages' => fn ($usageQuery) => $usageQuery
                    ->whereNotNull('student_identity_id')
                    ->with('studentIdentity')
                    ->latest('started_at')]);
                $query->withCount('usages')->withCount([
                    'usages as students_played_count' => fn ($usageQuery) => $usageQuery
                        ->whereNotNull('student_identity_id')
                        ->select(DB::raw('count(distinct student_identity_id)')),
                ]);
            })
            ->orderBy('title');

        $stats = $isGamesSection ? [
            'totalGames' => Intervention::query()
                ->where('content_type', 'positive_engagement')
                ->count(),
            'totalStudentsPlayed' => InterventionUsage::query()
                ->whereNotNull('student_identity_id')
                ->whereHas('intervention', fn ($interventionQuery) => $interventionQuery->where('content_type', 'positive_engagement'))
                ->distinct('student_identity_id')
                ->count('student_identity_id'),
        ] : [];

        return view('admin.interventions.index', [
            'interventions' => $query->paginate(20),
            'configuration' => $configuration,
            'stats' => $stats,
            'builtInGames' => $isGamesSection ? [
                'Breathing Challenge',
                'Gratitude Jar',
                'Memory Spark',
                'Mindful Memory',
            ] : [],
        ]);
    }

    public function create(): View
    {
        return view('admin.interventions.form', [
            'intervention' => new Intervention,
            'bands' => $this->bands(),
            'selectedBandIds' => [],
            'configuration' => $this->configuration(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $intervention = Intervention::query()->create($this->validated($request));
            $this->syncRecommendations($intervention, $request);
        });

        $configuration = $this->configuration();

        return redirect()->route($configuration['route'].'.index')
            ->with('status', $configuration['singular'].' created.');
    }

    public function edit(Intervention $intervention): View
    {
        $this->ensureManaged($intervention);

        return view('admin.interventions.form', [
            'intervention' => $intervention,
            'bands' => $this->bands(),
            'selectedBandIds' => $intervention->recommendations()
                ->where('is_active', true)
                ->pluck('stress_score_band_id')
                ->all(),
            'configuration' => $this->configuration(),
        ]);
    }

    public function update(Request $request, Intervention $intervention): RedirectResponse
    {
        $this->ensureManaged($intervention);

        DB::transaction(function () use ($request, $intervention): void {
            $intervention->update($this->validated($request));
            $this->syncRecommendations($intervention, $request);
        });

        $configuration = $this->configuration();

        return redirect()->route($configuration['route'].'.index')
            ->with('status', $configuration['singular'].' updated.');
    }

    public function destroy(Intervention $intervention): RedirectResponse
    {
        $this->ensureManaged($intervention);

        $configuration = $this->configuration();

        if ($intervention->usages()->exists()) {
            $intervention->update(['is_active' => false]);

            return back()->with('status', $configuration['singular'].' has usage history and was deactivated instead of deleted.');
        }

        $intervention->delete();

        return back()->with('status', $configuration['singular'].' deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'content_type' => ['required', 'in:'.implode(',', array_keys($this->configuration()['contentTypes']))],
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

    /** @return array{route: string, title: string, singular: string, heading: string, description: string, empty: string, studentSection: string, contentTypes: array<string, string>} */
    protected function configuration(): array
    {
        return [
            'route' => 'admin.interventions',
            'title' => 'Support Content',
            'singular' => 'Support content',
            'heading' => 'Journaling prompts',
            'description' => 'Manage guided journaling content shown to students. Stress-level targeting can also support future result recommendations.',
            'empty' => 'No journaling content configured.',
            'studentSection' => 'Positive Engagement',
            'contentTypes' => ['journaling' => 'Journaling'],
        ];
    }

    private function ensureManaged(Intervention $intervention): void
    {
        abort_unless(array_key_exists($intervention->content_type, $this->configuration()['contentTypes']), 404);
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
