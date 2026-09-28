<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TipRequest;
use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A deliberately narrow admin screen for everyday wellbeing suggestions —
 * the `tip` content type within {@see PersonalGuidance} ("☀️ Daily
 * Wellbeing Tips" in the student app).
 *
 * Same shape as {@see AdminGuidanceController} minus the external resource
 * link, which this content type has no use for. No matching rule to
 * configure: the student app shows every published tip, optionally filtered
 * by category.
 */
class AdminTipController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $items = PersonalGuidance::query()
            ->where('type', PersonalGuidance::TYPE_TIP)
            ->with('contentCategory')
            ->when($search !== '', fn ($q) => $q->where(
                fn ($inner) => $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%"),
            ))
            ->when($categoryId, fn ($q) => $q->where('content_category_id', (int) $categoryId))
            ->inDisplayOrder()
            ->paginate(20)
            ->withQueryString();

        return view('admin.tips.index', [
            'items' => $items,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => $this->availableCategories(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tips.form', [
            'tip' => new PersonalGuidance([
                'type' => PersonalGuidance::TYPE_TIP,
                'status' => PersonalGuidance::STATUS_PUBLISHED,
            ]),
            'categories' => $this->availableCategories(),
        ]);
    }

    public function store(TipRequest $request): RedirectResponse
    {
        $data = $request->validated();

        PersonalGuidance::query()->create([
            'type' => PersonalGuidance::TYPE_TIP,
            'title' => $data['title'],
            'content' => $data['content'],
            'content_category_id' => $data['content_category_id'] ?? null,
            'steps' => $data['steps'] ?? null,
            'status' => $data['is_active'] ? PersonalGuidance::STATUS_PUBLISHED : PersonalGuidance::STATUS_UNPUBLISHED,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.wellbeing-tips.index')->with('status', 'Tip added.');
    }

    public function edit(PersonalGuidance $wellbeingTip): View
    {
        abort_unless($wellbeingTip->type === PersonalGuidance::TYPE_TIP, 404);

        return view('admin.tips.form', [
            'tip' => $wellbeingTip,
            'categories' => $this->availableCategories(),
        ]);
    }

    public function update(TipRequest $request, PersonalGuidance $wellbeingTip): RedirectResponse
    {
        abort_unless($wellbeingTip->type === PersonalGuidance::TYPE_TIP, 404);

        $data = $request->validated();

        $wellbeingTip->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'content_category_id' => $data['content_category_id'] ?? null,
            'steps' => $data['steps'] ?? null,
            'status' => $data['is_active'] ? PersonalGuidance::STATUS_PUBLISHED : PersonalGuidance::STATUS_UNPUBLISHED,
        ]);

        return redirect()->route('admin.wellbeing-tips.index')->with('status', 'Tip updated.');
    }

    public function destroy(PersonalGuidance $wellbeingTip): RedirectResponse
    {
        abort_unless($wellbeingTip->type === PersonalGuidance::TYPE_TIP, 404);

        $wellbeingTip->delete();

        return back()->with('status', 'Tip deleted.');
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
