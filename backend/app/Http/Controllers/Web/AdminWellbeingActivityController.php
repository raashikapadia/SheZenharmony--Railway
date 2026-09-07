<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\WellbeingActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWellbeingActivityController extends Controller
{
    public function index(): View
    {
        return view('admin.wellbeing_activities.index', [
            'activities' => WellbeingActivity::query()->orderBy('title')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.wellbeing_activities.form', ['activity' => new WellbeingActivity]);
    }

    public function store(Request $request): RedirectResponse
    {
        WellbeingActivity::query()->create($this->validated($request));

        return redirect()->route('admin.wellbeing_activities.index')->with('status', 'Activity created.');
    }

    public function edit(WellbeingActivity $wellbeingActivity): View
    {
        return view('admin.wellbeing_activities.form', ['activity' => $wellbeingActivity]);
    }

    public function update(Request $request, WellbeingActivity $wellbeingActivity): RedirectResponse
    {
        $wellbeingActivity->update($this->validated($request));

        return redirect()->route('admin.wellbeing_activities.index')->with('status', 'Activity updated.');
    }

    public function destroy(WellbeingActivity $wellbeingActivity): RedirectResponse
    {
        $wellbeingActivity->delete();

        return back()->with('status', 'Activity deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'video_url' => ['required', 'url', 'max:2000'],
            'video_type' => ['required', 'in:youtube,tiktok'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
