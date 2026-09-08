<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatIntent;
use App\Models\ChatQuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages what Shezen says. Every category, keyword, reply and button is
 * authored here — the app never generates language of its own.
 */
class AdminChatBuddyController extends Controller
{
    public function index(): View
    {
        return view('admin.chatbuddy.index', [
            'intents' => ChatIntent::query()
                ->withCount('responses')
                ->orderByDesc('is_crisis')
                ->orderBy('priority')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.chatbuddy.form', [
            'intent' => new ChatIntent(['priority' => 100, 'is_active' => true]),
            'message' => '',
            'quickReplies' => collect(),
        ] + $this->options());
    }

    public function edit(ChatIntent $chatbuddy): View
    {
        $response = $chatbuddy->responses()->orderBy('priority')->first();

        return view('admin.chatbuddy.form', [
            'intent' => $chatbuddy,
            'message' => $response?->message ?? '',
            'quickReplies' => $response?->quickReplies ?? collect(),
        ] + $this->options($chatbuddy));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request): void {
            $intent = ChatIntent::query()->create($this->intentAttributes($data) + [
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->syncResponse($intent, $data);
        });

        return redirect()->route('admin.chatbuddy.index')->with('status', 'Shezen category created.');
    }

    public function update(Request $request, ChatIntent $chatbuddy): RedirectResponse
    {
        $data = $this->validated($request, $chatbuddy);

        DB::transaction(function () use ($chatbuddy, $data): void {
            $chatbuddy->update($this->intentAttributes($data));
            $this->syncResponse($chatbuddy, $data);
        });

        return redirect()->route('admin.chatbuddy.index')->with('status', 'Shezen category updated.');
    }

    public function destroy(ChatIntent $chatbuddy): RedirectResponse
    {
        $chatbuddy->delete();

        return back()->with('status', 'Shezen category deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ChatIntent $existing = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('chat_intents', 'code')->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:4000'],
            'message' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_crisis' => ['nullable', 'boolean'],
            'is_starter' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'replies' => ['nullable', 'array', 'max:6'],
            'replies.*.label' => ['nullable', 'string', 'max:120'],
            'replies.*.links_to' => ['nullable', Rule::in(array_keys(ChatQuickReply::LINK_TARGETS))],
            'replies.*.next_chat_intent_id' => ['nullable', 'integer', 'exists:chat_intents,id'],
        ], [
            'code.regex' => 'Use lower-case letters, numbers and underscores only.',
        ]);
    }

    /** @return array<string, mixed> */
    private function intentAttributes(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'keywords' => $data['keywords'] ?? null,
            'priority' => $data['priority'],
            'is_crisis' => (bool) ($data['is_crisis'] ?? false),
            'is_starter' => (bool) ($data['is_starter'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    /**
     * Stores the reply and its buttons. One response per category keeps the
     * admin screen simple; the service already supports more if that changes.
     */
    private function syncResponse(ChatIntent $intent, array $data): void
    {
        $response = $intent->responses()->orderBy('priority')->first()
            ?? $intent->responses()->make(['priority' => 100, 'is_active' => true]);

        $response->message = $data['message'];
        $response->is_active = true;
        $intent->responses()->save($response);

        $response->quickReplies()->delete();

        $position = 1;

        foreach ($data['replies'] ?? [] as $reply) {
            $label = trim((string) ($reply['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $response->quickReplies()->create([
                'label' => $label,
                'links_to' => $reply['links_to'] ?: null,
                'next_chat_intent_id' => $reply['next_chat_intent_id'] ?: null,
                'position' => $position++,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function options(?ChatIntent $exclude = null): array
    {
        return [
            'linkTargets' => ChatQuickReply::LINK_TARGETS,
            'intents' => ChatIntent::query()
                ->when($exclude !== null, fn ($query) => $query->whereKeyNot($exclude->id))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
