<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuidanceRequest;
use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A deliberately narrow admin screen for coping strategies and practical
 * advice — the `guidance` content type within {@see PersonalGuidance}
 * ("🌿 Advice & Coping Strategies" in the student app).
 *
 * An admin picks a title, writes the advice, optionally tags it with a
 * category, an optional "try this" action, and an optional external resource
 * link, then saves. There is no matching rule to configure: the student app
 * simply shows every published item, optionally filtered by category.
 *
 * Quotes and affirmations stay on their own screen ({@see AdminPersonalGuidanceController}),
 * and wellbeing tips on theirs ({@see AdminTipController}); this controller
 * only ever touches `type = 'guidance'` rows.
 */
class AdminGuidanceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $items = PersonalGuidance::query()
            ->where('type', PersonalGuidance::TYPE_GUIDANCE)
            ->with('contentCategory')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($inner) => $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%"),
            ))
            ->when($categoryId, fn ($q) => $q->where('content_category_id', (int) $categoryId))
            ->inDisplayOrder()
            ->paginate(20)
            ->withQueryString();

        return view('admin.guidance.index', [
            'items' => $items,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => $this->availableCategories(),
        ]);
    }

    public function create(): View
    {
        return view('admin.guidance.form', [
            'guidance' => new PersonalGuidance([
                'type' => PersonalGuidance::TYPE_GUIDANCE,
                'status' => PersonalGuidance::STATUS_PUBLISHED,
            ]),
            'categories' => $this->availableCategories(),
        ]);
    }

    public function store(GuidanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        PersonalGuidance::query()->create([
            'type' => PersonalGuidance::TYPE_GUIDANCE,
            'title' => $data['title'],
            'content' => $data['content'],
            'content_category_id' => $data['content_category_id'] ?? null,
            'steps' => $data['steps'] ?? null,
            'resource_url' => $data['resource_url'] ?? null,
            'status' => $data['is_active'] ? PersonalGuidance::STATUS_PUBLISHED : PersonalGuidance::STATUS_UNPUBLISHED,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.guidance.index')->with('status', 'Guidance added.');
    }

    public function edit(PersonalGuidance $guidance): View
    {
        abort_unless($guidance->type === PersonalGuidance::TYPE_GUIDANCE, 404);

        return view('admin.guidance.form', [
            'guidance' => $guidance,
            'categories' => $this->availableCategories(),
        ]);
    }

    public function update(GuidanceRequest $request, PersonalGuidance $guidance): RedirectResponse
    {
        abort_unless($guidance->type === PersonalGuidance::TYPE_GUIDANCE, 404);

        $data = $request->validated();

        $guidance->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'content_category_id' => $data['content_category_id'] ?? null,
            'steps' => $data['steps'] ?? null,
            'resource_url' => $data['resource_url'] ?? null,
            'status' => $data['is_active'] ? PersonalGuidance::STATUS_PUBLISHED : PersonalGuidance::STATUS_UNPUBLISHED,
        ]);

        return redirect()->route('admin.guidance.index')->with('status', 'Guidance updated.');
    }

    public function destroy(PersonalGuidance $guidance): RedirectResponse
    {
        abort_unless($guidance->type === PersonalGuidance::TYPE_GUIDANCE, 404);

        $guidance->delete();

        return back()->with('status', 'Guidance deleted.');
    }

    /** @return Collection<int, ContentCategory> */
    private function availableCategories(): Collection
    {
        return ContentCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
