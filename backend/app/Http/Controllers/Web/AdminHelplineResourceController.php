<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HelplineResourceRequest;
use App\Models\HelplineResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminHelplineResourceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'category' => $request->query('category'),
        ];

        $resources = HelplineResource::query()
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['category'], fn ($q) => $q->where('category', $filters['category']))
            ->inDisplayOrder()
            ->paginate(20)
            ->withQueryString();

        return view('admin.resources.index', [
            'resources' => $resources,
            'filters' => $filters,
            'categories' => HelplineResource::query()
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.resources.form', [
            'resource' => new HelplineResource(['is_active' => true]),
        ]);
    }

    public function store(HelplineResourceRequest $request): RedirectResponse
    {
        HelplineResource::query()->create($request->validated() + [
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('admin.resources.index')->with('status', 'Helpline resource created.');
    }

    public function edit(HelplineResource $resource): View
    {
        return view('admin.resources.form', ['resource' => $resource]);
    }

    public function update(HelplineResourceRequest $request, HelplineResource $resource): RedirectResponse
    {
        $resource->update($request->validated());

        return redirect()->route('admin.resources.index')->with('status', 'Helpline resource updated.');
    }

    public function destroy(HelplineResource $resource): RedirectResponse
    {
        $resource->delete();

        return back()->with('status', 'Helpline resource deleted.');
    }
}
