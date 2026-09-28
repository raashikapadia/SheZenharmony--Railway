<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonalGuidanceRequest;
use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daily Affirmations — the two Personal Guidance content types shown
 * together as "✨ Daily Affirmations" in the student app: affirmations and
 * motivational quotes. Coping strategies and advice ("guidance" rows) have
 * their own screen ({@see AdminGuidanceController}); wellbeing tips have
 * theirs ({@see AdminTipController}).
 */
class AdminPersonalGuidanceController extends Controller
{
    /** The two types this screen manages; the others have their own screens. */
    private const TYPES = [PersonalGuidance::TYPE_AFFIRMATION, PersonalGuidance::TYPE_QUOTE];

    public function index(Request $request): View
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
        ];

        $items = PersonalGuidance::query()
            ->whereIn('type', self::TYPES)
            ->when(in_array($filters['type'], self::TYPES, true), fn ($q) => $q->where('type', $filters['type']))
            ->when(in_array($filters['status'], PersonalGuidance::STATUSES, true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['category_id'], fn ($q) => $q->where('content_category_id', (int) $filters['category_id']))
            ->with('contentCategory')
            ->inDisplayOrder()
            ->paginate(20)
            ->withQueryString();

        return view('admin.personal-guidance.index', [
            'items' => $items,
            'filters' => $filters,
            'categories' => ContentCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.personal-guidance.form', [
            'guidance' => new PersonalGuidance(['status' => PersonalGuidance::STATUS_PUBLISHED]),
        ] + $this->matchingOptions());
    }

    public function store(PersonalGuidanceRequest $request): RedirectResponse
    {
        PersonalGuidance::query()->create($this->guidanceAttributes($request) + [
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance created.');
    }

    public function edit(PersonalGuidance $personalGuidance): View
    {
        return view('admin.personal-guidance.form', [
            'guidance' => $personalGuidance,
        ] + $this->matchingOptions());
    }

    public function update(PersonalGuidanceRequest $request, PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->update($this->guidanceAttributes($request));

        return redirect()->route('admin.personal-guidance.index')->with('status', 'Guidance updated.');
    }

    /** The guidance columns, translating the simple published/draft checkbox into a status. */
    private function guidanceAttributes(PersonalGuidanceRequest $request): array
    {
        $data = collect($request->validated())->except('is_active')->all();
        $data['status'] = $request->boolean('is_active') ? PersonalGuidance::STATUS_PUBLISHED : PersonalGuidance::STATUS_UNPUBLISHED;

        return $data;
    }

    /** Categories the form's select offers. */
    private function matchingOptions(): array
    {
        return [
            'categories' => ContentCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function destroy(PersonalGuidance $personalGuidance): RedirectResponse
    {
        $personalGuidance->delete();

        return back()->with('status', 'Guidance deleted.');
    }

    /** A rough approximation of how this item will look in the student app. */
    public function preview(PersonalGuidance $personalGuidance): View
    {
        return view('admin.personal-guidance.preview', [
            'guidance' => $personalGuidance->load('contentCategory'),
        ]);
    }
}
