<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddyTopic;
use App\Services\ChatBuddyReleaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminChatBuddyReleaseController extends Controller
{
    public function index(ChatBuddyReleaseService $service): View
    {
        return view('admin.chatbuddy.release', ['draft' => $service->ensureDraft(request()->user())->load('topics.phrases'), 'published' => ChatBuddyRelease::query()->published()->with('topics')->first()]);
    }
    public function publish(ChatBuddyRelease $release, ChatBuddyReleaseService $service): RedirectResponse { $service->publish($release, request()->user()); return back()->with('status', 'Chat Buddy draft published.'); }
    public function destroy(ChatBuddyTopic $topic, ChatBuddyReleaseService $service): RedirectResponse { $service->deleteDraftTopic($topic); return back()->with('status', 'Draft topic deleted.'); }
    public function store(Request $request, ChatBuddyReleaseService $service): RedirectResponse
    {
        $data = $request->validate(['title' => ['required','string','max:255'], 'reply' => ['required','string','max:4000'], 'priority' => ['required','integer','min:1','max:9999'], 'phrases' => ['required','array','min:1'], 'phrases.*' => ['required','string','max:255']]);
        $topic = $service->ensureDraft($request->user())->topics()->create(['title' => $data['title'], 'reply' => $data['reply'], 'priority' => $data['priority']]);
        foreach (array_values($this->phrases($data['phrases'])) as $position => $phrase) $topic->phrases()->create(['phrase' => $phrase, 'position' => $position + 1]);
        return back()->with('status', 'Draft topic saved.');
    }
    public function update(Request $request, ChatBuddyTopic $topic): RedirectResponse
    {
        abort_unless($topic->release()->draft()->exists(), 404);
        $data = $request->validate(['title' => ['required','string','max:255'], 'reply' => ['required','string','max:4000'], 'priority' => ['required','integer','min:1','max:9999'], 'phrases' => ['required','array','min:1'], 'phrases.*' => ['required','string','max:255']]);
        $topic->update(['title' => $data['title'], 'reply' => $data['reply'], 'priority' => $data['priority']]); $topic->phrases()->delete(); foreach (array_values($this->phrases($data['phrases'])) as $position => $phrase) $topic->phrases()->create(['phrase' => $phrase, 'position' => $position + 1]);
        return back()->with('status', 'Draft topic updated.');
    }
    private function phrases(array $values): array { return collect($values)->flatMap(fn ($value) => preg_split('/\r\n|\r|\n|,/', (string) $value))->map(fn ($value) => trim($value))->filter()->values()->all(); }
}
