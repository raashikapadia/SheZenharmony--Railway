<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddyTopic;
use App\Services\ChatBuddyReleaseService;
use App\Services\ShezenChatService;
use App\Models\WellbeingActivity;
use App\Models\PersonalGuidance;
use App\Models\HelplineResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminChatBuddyReleaseController extends Controller
{
    public function index(ChatBuddyReleaseService $service): View
    {
        $draft = $service->ensureDraft(request()->user())->load(['topics.phrases', 'topics.followUpPrompts', 'topics.links']);
        $selectedTopic = $draft->topics->firstWhere('id', request()->integer('topic_id')) ?? $draft->topics->first();
        return view('admin.chatbuddy.release', ['draft' => $draft, 'selectedTopic' => $selectedTopic, 'published' => ChatBuddyRelease::query()->published()->with('topics')->first(), 'linkOptions' => ['wellbeing_activity' => WellbeingActivity::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']), 'personal_guidance' => PersonalGuidance::query()->visible()->orderBy('title')->get(['id', 'title']), 'resource' => HelplineResource::query()->visible()->orderBy('name')->get(['id', 'name'])]]);
    }
    public function history(): View
    {
        return view('admin.chatbuddy.history', ['logs' => \App\Models\ChatBuddyPublicationLog::query()->with(['release', 'user'])->latest()->get()]);
    }
    public function preview(Request $request, ShezenChatService $chat): View
    {
        $data = $request->validate(['release_id' => ['required', 'integer', 'exists:chat_buddy_releases,id'], 'message' => ['required', 'string', 'max:2000']]);
        $release = ChatBuddyRelease::query()->whereKey($data['release_id'])->where('current_key', 'draft')->with(['topics.phrases', 'topics.links', 'topics.followUpPrompts'])->firstOrFail();
        return view('admin.chatbuddy.preview', ['release' => $release, 'sample' => $data['message'], 'result' => $chat->replyForRelease($release, $data['message'])]);
    }
    public function previewForm(ChatBuddyReleaseService $service): View
    {
        return view('admin.chatbuddy.preview', ['release' => $service->ensureDraft(request()->user())->load(['topics.phrases', 'topics.links', 'topics.followUpPrompts']), 'sample' => '', 'result' => null]);
    }
    public function updateSettings(Request $request, ChatBuddyReleaseService $service): RedirectResponse
    {
        $data = $request->validate(['welcome_message' => ['nullable', 'string', 'max:4000'], 'fallback_message' => ['required', 'string', 'max:4000'], 'safety_message' => ['nullable', 'string', 'max:4000'], 'suggested_topics' => ['nullable', 'array', 'max:10'], 'suggested_topics.*' => ['string', 'max:255']]);
        $data['suggested_topics'] = $this->phrases($data['suggested_topics'] ?? []);
        $service->ensureDraft($request->user())->update($data);
        return back()->with('status', 'Draft settings saved.');
    }
    public function approveSafety(Request $request, ChatBuddyRelease $release, ChatBuddyReleaseService $service): RedirectResponse
    {
        abort_unless($release->current_key === 'draft', 404);
        $release->load(['topics.phrases', 'topics.links']);
        $release->update(['safety_content_hash' => $service->safetyHash($release), 'safety_content_approved' => true]);
        return back()->with('status', 'Safety content approved for this exact draft.');
    }
    public function publish(ChatBuddyRelease $release, ChatBuddyReleaseService $service): RedirectResponse { $service->publish($release, request()->user()); return back()->with('status', 'Chat Buddy draft published.'); }
    public function destroy(ChatBuddyTopic $topic, ChatBuddyReleaseService $service): RedirectResponse { $service->deleteDraftTopic($topic); return back()->with('status', 'Draft topic deleted.'); }
    public function store(Request $request, ChatBuddyReleaseService $service): RedirectResponse
    {
        $data = $request->validate(['title' => ['required','string','max:255'], 'description' => ['nullable','string','max:4000'], 'reply' => ['required','string','max:4000'], 'priority' => ['required','integer','min:1','max:9999'], 'is_safety' => ['nullable','boolean'], 'phrases' => ['required','array','min:1'], 'phrases.*' => ['required','string','max:255'], 'follow_up_prompts' => ['nullable','array'], 'follow_up_prompts.*' => ['string','max:255'], 'follow_up_prompts_text' => ['nullable','string','max:4000'], 'links' => ['nullable','array'], 'links.*' => ['string','max:2200'], 'links_text' => ['nullable','string','max:4000'], 'typed_links' => ['nullable','array'], 'typed_links.*' => ['string','max:255']]);
        $topic = $service->ensureDraft($request->user())->topics()->create(['title' => $data['title'], 'description' => $data['description'] ?? null, 'reply' => $data['reply'], 'priority' => $data['priority'], 'is_safety' => (bool) ($data['is_safety'] ?? false)]);
        foreach (array_values($this->phrases($data['phrases'])) as $position => $phrase) $topic->phrases()->create(['phrase' => $phrase, 'position' => $position + 1]);
        foreach (array_values($this->phrases($data['follow_up_prompts_text'] ?? $data['follow_up_prompts'] ?? [])) as $position => $prompt) $topic->followUpPrompts()->create(['prompt' => $prompt, 'position' => $position + 1]);
        foreach (array_values($this->links($data['links_text'] ?? $data['links'] ?? [])) as $position => $link) $topic->links()->create(['link_type' => 'external', 'label' => $link['label'], 'url' => $link['url'], 'position' => $position + 1]);
        foreach (array_values($this->typedLinks($data['typed_links'] ?? [])) as $position => $link) $topic->links()->create($link + ['position' => $position + 1]);
        return back()->with('status', 'Draft topic saved.');
    }
    public function update(Request $request, ChatBuddyTopic $topic): RedirectResponse
    {
        abort_unless($topic->release()->draft()->exists(), 404);
        $data = $request->validate(['title' => ['required','string','max:255'], 'description' => ['nullable','string','max:4000'], 'reply' => ['required','string','max:4000'], 'priority' => ['required','integer','min:1','max:9999'], 'is_safety' => ['nullable','boolean'], 'phrases' => ['required','array','min:1'], 'phrases.*' => ['required','string','max:255'], 'follow_up_prompts' => ['nullable','array'], 'follow_up_prompts.*' => ['string','max:255'], 'follow_up_prompts_text' => ['nullable','string','max:4000'], 'links' => ['nullable','array'], 'links.*' => ['string','max:2200'], 'links_text' => ['nullable','string','max:4000'], 'typed_links' => ['nullable','array'], 'typed_links.*' => ['string','max:255']]);
        $wasSafety = (bool) $topic->is_safety;
        $isSafety = (bool) ($data['is_safety'] ?? false);
        $topic->update(['title' => $data['title'], 'description' => $data['description'] ?? null, 'reply' => $data['reply'], 'priority' => $data['priority'], 'is_safety' => $isSafety]); $topic->phrases()->delete(); foreach (array_values($this->phrases($data['phrases'])) as $position => $phrase) $topic->phrases()->create(['phrase' => $phrase, 'position' => $position + 1]); $topic->followUpPrompts()->delete(); foreach (array_values($this->phrases($data['follow_up_prompts_text'] ?? $data['follow_up_prompts'] ?? [])) as $position => $prompt) $topic->followUpPrompts()->create(['prompt' => $prompt, 'position' => $position + 1]); $topic->links()->delete(); foreach (array_values($this->links($data['links_text'] ?? $data['links'] ?? [])) as $position => $link) $topic->links()->create(['link_type' => 'external', 'label' => $link['label'], 'url' => $link['url'], 'position' => $position + 1]);
        if ($wasSafety || $isSafety) $topic->release()->update(['safety_content_approved' => false, 'safety_content_hash' => null]);
        foreach (array_values($this->typedLinks($data['typed_links'] ?? [])) as $position => $link) $topic->links()->create($link + ['position' => $position + 1]);
        return back()->with('status', 'Draft topic updated.');
    }
    private function phrases(array|string $values): array { $values = is_array($values) ? $values : [$values]; return collect($values)->flatMap(fn ($value) => preg_split('/\r\n|\r|\n|,/', (string) $value))->map(fn ($value) => trim($value))->filter()->values()->all(); }
    private function links(array|string $values): array { $values = is_array($values) ? $values : [$values]; return collect($values)->flatMap(fn ($value) => preg_split('/\r\n|\r|\n/', (string) $value))->map(fn ($value) => array_map('trim', explode('|', $value, 2)))->filter(fn ($parts) => count($parts) === 2 && filled($parts[0]) && filter_var($parts[1], FILTER_VALIDATE_URL))->map(fn ($parts) => ['label' => $parts[0], 'url' => $parts[1]])->values()->all(); }
    private function typedLinks(array $values): array { return collect($values)->map(fn ($value) => array_map('trim', explode('|', (string) $value, 3)))->filter(fn ($parts) => count($parts) === 3 && in_array($parts[0], ['wellbeing_activity', 'personal_guidance', 'resource'], true) && ctype_digit($parts[1]) && filled($parts[2]))->filter(function ($parts): bool { $id = (int) $parts[1]; return match ($parts[0]) { 'wellbeing_activity' => WellbeingActivity::query()->whereKey($id)->where('is_active', true)->exists(), 'personal_guidance' => PersonalGuidance::query()->visible()->whereKey($id)->exists(), 'resource' => HelplineResource::query()->visible()->whereKey($id)->exists(), default => false, }; })->map(fn ($parts) => ['link_type' => $parts[0], 'target_id' => (int) $parts[1], 'label' => $parts[2], 'url' => null])->values()->all(); }
}
