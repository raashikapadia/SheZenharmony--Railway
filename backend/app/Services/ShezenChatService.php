<?php

namespace App\Services;

use App\Models\ChatIntent;
use App\Models\ChatQuickReply;
use App\Models\ChatResponse;
use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddyTopic;
use App\Models\ChatBuddyTopicLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Shezen's rule engine.
 *
 * Given a student's message this resolves one administrator-authored reply and
 * the quick replies that follow it. The pipeline is:
 *
 *   message -> crisis check -> keyword match -> intent -> response -> replies
 *
 * It is deliberately deterministic and offline. There is no model, no API call
 * and no generated language anywhere: every word Shezen says was typed by an
 * administrator in the Web Admin. Crisis intents are evaluated before anything
 * else and short-circuit normal matching, so a concerning message can never
 * fall through to a cheerful reply.
 *
 * The service is intentionally free of HTTP and UI concerns so it can be unit
 * tested now and wired to a live chat screen later without changes.
 */
class ShezenChatService
{
    public function publishedPayload(): array
    {
        $release = ChatBuddyRelease::query()->published()->with($this->contentRelations())->first();
        if ($release === null) {
            return ['available' => false, 'welcome_message' => null, 'suggested_topics' => []];
        }
        return $this->payloadForRelease($release);
    }

    public function payloadForRelease(ChatBuddyRelease $release): array
    {
        return [
            'available' => true,
            'welcome_message' => $release->welcome_message,
            'suggested_topics' => array_values($release->suggested_topics ?? []),
        ];
    }

    public function replyForPublished(string $message): array
    {
        $release = ChatBuddyRelease::query()->published()->with($this->contentRelations())->first();
        return $release === null ? ['available' => false, 'message' => null, 'is_safety' => false, 'is_fallback' => true, 'links' => [], 'follow_up_prompts' => []] : $this->replyForRelease($release, $message);
    }

    public function replyForRelease(ChatBuddyRelease $release, string $message): array
    {
        $message = trim(mb_strtolower($message));
        if ($message === '') {
            throw new \InvalidArgumentException('Message is required.');
        }
        $topics = $release->relationLoaded('topics') ? $release->topics : $release->topics()->with(['phrases', 'followUpPrompts', 'links'])->get();
        $match = $topics->filter(fn (ChatBuddyTopic $topic) => $topic->is_safety)->sortBy(fn ($t) => [$t->priority, $t->id])->first(fn ($t) => $this->topicMatches($t, $message));
        $match ??= $topics->reject(fn (ChatBuddyTopic $topic) => $topic->is_safety)->sortBy(fn ($t) => [$t->priority, $t->id])->first(fn ($t) => $this->topicMatches($t, $message));
        if ($match === null) {
            return ['available' => true, 'message' => $release->fallback_message, 'is_safety' => false, 'is_fallback' => true, 'links' => [], 'follow_up_prompts' => array_values($release->suggested_topics ?? [])];
        }
        return ['available' => true, 'message' => $match->is_safety && $release->safety_message ? $release->safety_message : $match->reply, 'is_safety' => (bool) $match->is_safety, 'is_fallback' => false, 'links' => $match->links->filter(fn (ChatBuddyTopicLink $link) => $this->linkAvailable($link))->map(fn ($link) => ['label' => $link->label, 'type' => $link->link_type, 'url' => $link->url])->values()->all(), 'follow_up_prompts' => $match->followUpPrompts->pluck('prompt')->values()->all()];
    }

    private function contentRelations(): array { return ['topics.phrases', 'topics.followUpPrompts', 'topics.links']; }
    private function topicMatches(ChatBuddyTopic $topic, string $message): bool
    {
        foreach ($topic->phrases as $phrase) {
            $needle = trim(mb_strtolower($phrase->phrase));
            if ($needle !== '' && preg_match('/(?<![\pL\pN])'.preg_quote($needle, '/').'(?![\pL\pN])/u', $message)) return true;
        }
        return false;
    }
    private function linkAvailable(ChatBuddyTopicLink $link): bool
    {
        if ($link->link_type === 'external') return filled($link->url);
        if (! $link->target_id) return false;
        return match ($link->link_type) {
            'wellbeing_activity' => DB::table('wellbeing_activities')->where('id', $link->target_id)->where('is_active', true)->exists(),
            'personal_guidance' => DB::table('personal_guidance')->where('id', $link->target_id)->where('status', 'published')->exists(),
            'resource' => DB::table('helpline_resources')->where('id', $link->target_id)->where('is_active', true)->exists(),
            default => false,
        };
    }
    /**
     * Resolve a reply for a free-text message.
     *
     * @return array{intent: ?ChatIntent, response: ?ChatResponse, is_crisis: bool, message: string, quick_replies: array<int, array<string, mixed>>}
     */
    public function reply(string $message): array
    {
        $intent = $this->match($message);

        return $this->replyForIntent($intent);
    }

    /**
     * Resolve a reply for an intent the student reached by tapping a button.
     *
     * @return array{intent: ?ChatIntent, response: ?ChatResponse, is_crisis: bool, message: string, quick_replies: array<int, array<string, mixed>>}
     */
    public function replyForIntent(?ChatIntent $intent): array
    {
        $response = $intent === null ? null : $this->responseFor($intent);

        return [
            'intent' => $intent,
            'response' => $response,
            'is_crisis' => (bool) $intent?->is_crisis,
            'message' => $response?->message ?? $this->fallbackMessage(),
            'quick_replies' => $this->quickReplies($response),
        ];
    }

    /**
     * The intent a message belongs to, or null when nothing matches.
     *
     * Crisis intents are checked first regardless of priority. Within each
     * group the lowest `priority` number wins, then the oldest intent, so the
     * outcome is stable for the same input.
     */
    public function match(string $message): ?ChatIntent
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        $intents = ChatIntent::query()
            ->active()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $crisis = $intents->firstWhere(fn (ChatIntent $intent): bool => $intent->is_crisis && $intent->matches($message));

        if ($crisis !== null) {
            return $crisis;
        }

        return $intents->first(fn (ChatIntent $intent): bool => ! $intent->is_crisis && $intent->matches($message));
    }

    /** The conversation starters shown when a chat opens. */
    public function starters(): Collection
    {
        return ChatIntent::query()
            ->active()
            ->where('is_starter', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /** The highest-priority active response for an intent. */
    private function responseFor(ChatIntent $intent): ?ChatResponse
    {
        return ChatResponse::query()
            ->where('chat_intent_id', $intent->id)
            ->where('is_active', true)
            ->with('quickReplies.nextIntent')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * The buttons under a reply, flattened for the client.
     *
     * @return array<int, array<string, mixed>>
     */
    private function quickReplies(?ChatResponse $response): array
    {
        if ($response === null) {
            return [];
        }

        return $response->quickReplies
            ->map(fn (ChatQuickReply $reply): array => [
                'label' => $reply->label,
                'next_intent_code' => $reply->nextIntent?->code,
                'links_to' => $reply->links_to,
            ])
            ->values()
            ->all();
    }

    /**
     * Shown when nothing matches. Kept deliberately plain and honest: Shezen
     * never guesses at meaning it was not given a rule for.
     */
    private function fallbackMessage(): string
    {
        return "I'm not sure I follow, but I'm here. Would you like to pick one of the options below?";
    }
}
