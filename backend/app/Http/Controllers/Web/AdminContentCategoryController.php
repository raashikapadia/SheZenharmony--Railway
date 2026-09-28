<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentCategoryRequest;
use App\Models\ContentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin-managed categories for Personal Guidance (Tips, Quotes, and
 * Affirmations). The same table also backs Intervention taxonomy, but this
 * screen only ever shows and edits categories through the Personal Guidance
 * lens — see {@see \App\Models\ContentCategory}.
 */
class AdminContentCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.personal-guidance.categories.index', [
            'categories' => ContentCategory::query()
                ->withCount('personalGuidance')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.personal-guidance.categories.form', [
            'category' => new ContentCategory(['is_active' => true]),
        ]);
    }

    public function store(ContentCategoryRequest $request): RedirectResponse
    {
        ContentCategory::query()->create($request->validated());

        return redirect()->route('admin.personal-guidance-categories.index')->with('status', 'Category created.');
    }

    public function edit(ContentCategory $contentCategory): View
    {
        return view('admin.personal-guidance.categories.form', ['category' => $contentCategory]);
    }

    public function update(ContentCategoryRequest $request, ContentCategory $contentCategory): RedirectResponse
    {
        $contentCategory->update($request->validated());

        return redirect()->route('admin.personal-guidance-categories.index')->with('status', 'Category updated.');
    }

    /**
     * Always safe: the foreign key on `personal_guidance.content_category_id`
     * is `nullOnDelete()`, so removing a category simply leaves its items
     * uncategorised rather than blocking the delete or removing content.
     */
    public function destroy(ContentCategory $contentCategory): RedirectResponse
    {
        $inUse = $contentCategory->personalGuidance()->count();
        $contentCategory->delete();

        $status = $inUse > 0
            ? "Category deleted. {$inUse} item(s) it was assigned to are now uncategorised."
            : 'Category deleted.';

        return back()->with('status', $status);
    }
}
