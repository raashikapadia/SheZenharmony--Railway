<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonalGuidanceRequest;
use App\Models\Intervention;
use App\Models\PersonalGuidance;
use App\Models\QuestionnaireSection;
use App\Models\StressScoreBand;
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
        return view('admin.personal-guidance.form', [
            'guidance' => new PersonalGuidance(['status' => PersonalGuidance::STATUS_DRAFT]),
        ] + $this->matchingOptions());
    }

    public function store(PersonalGuidanceRequest $request): RedirectResponse
    {
        $guidance = PersonalGuidance::query()->create($this->guidanceAttributes($request) + [
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->syncRecommendations($guidance, $request);

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance created.');
    }

    public function edit(PersonalGuidance $personalGuidance): View
    {
        return view('admin.personal-guidance.form', [
            'guidance' => $personalGuidance->load('recommendations'),
        ] + $this->matchingOptions());
    }

    public function update(PersonalGuidanceRequest $request, PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->update($this->guidanceAttributes($request));

        $this->syncRecommendations($personalGuidance, $request);

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance updated.');
    }

    /** The guidance columns, without the matching-rule inputs. */
    private function guidanceAttributes(PersonalGuidanceRequest $request): array
    {
        return collect($request->validated())->except(['band_ids', 'section_ids'])->all();
    }

    /**
     * Replaces this item's matching rules with the selected bands and sections.
     * Selecting none means "applies to everyone", matching how interventions
     * already behave.
     */
    private function syncRecommendations(PersonalGuidance $guidance, PersonalGuidanceRequest $request): void
    {
        $guidance->recommendations()->delete();

        foreach ((array) $request->input('band_ids', []) as $bandId) {
            $guidance->recommendations()->create([
                'stress_score_band_id' => (int) $bandId,
                'is_active' => true,
            ]);
        }

        foreach ((array) $request->input('section_ids', []) as $sectionId) {
            $guidance->recommendations()->create([
                'questionnaire_section_id' => (int) $sectionId,
                'is_active' => true,
            ]);
        }
    }

    /** Bands and sections an admin can match guidance against. */
    private function matchingOptions(): array
    {
        return [
            'bands' => StressScoreBand::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->get(['id', 'label', 'code']),
            'sections' => QuestionnaireSection::query()
                ->orderBy('title')
                ->get(['id', 'title']),
            'activities' => Intervention::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'title']),
        ];
    }

    public function destroy(PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->delete();

        return back()->with('status', 'Guidance deleted.');
    }
}
