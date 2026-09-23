<?php
namespace App\Services;
use App\Models\ChatBuddyPublicationLog;
use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddyTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatBuddyReleaseService
{
    public function ensureDraft(?User $actor = null): ChatBuddyRelease
    {
        return DB::transaction(function () use ($actor): ChatBuddyRelease {
            DB::table('chat_buddy_release_locks')->where('name', 'global')->lockForUpdate()->first();
            $draft = ChatBuddyRelease::query()->draft()->with('topics')->lockForUpdate()->first();
            if ($draft) return $draft;
            $published = ChatBuddyRelease::query()->published()->with('topics')->lockForUpdate()->first();
            if ($published) return $this->copyToDraft($published, $actor);
            return ChatBuddyRelease::query()->create(['status' => 'draft', 'current_key' => 'draft', 'created_by_user_id' => $actor?->id, 'suggested_topics' => []]);
        });
    }

    public function copyToDraft(ChatBuddyRelease $published, ?User $actor = null): ChatBuddyRelease
    {
        $draft = ChatBuddyRelease::query()->create($published->only(['welcome_message','fallback_message','safety_message','suggested_topics','safety_content_approved','safety_content_hash']) + ['status' => 'draft', 'current_key' => 'draft', 'created_by_user_id' => $actor?->id]);
        foreach ($published->topics()->with(['phrases','followUpPrompts','links'])->get() as $topic) {
            $copy = $draft->topics()->create($topic->only(['title','description','priority','is_safety','reply']));
            foreach ($topic->phrases as $item) $copy->phrases()->create($item->only(['phrase','position']));
            foreach ($topic->followUpPrompts as $item) $copy->followUpPrompts()->create($item->only(['prompt','position']));
            foreach ($topic->links as $item) $copy->links()->create($item->only(['link_type','target_id','label','url','target_name','position']));
        }
        return $draft->fresh('topics');
    }

    public function publish(ChatBuddyRelease $draft, User $actor): ChatBuddyRelease
    {
        return DB::transaction(function () use ($draft, $actor): ChatBuddyRelease {
            $draft = ChatBuddyRelease::query()->whereKey($draft)->with(['topics.phrases','topics.links','topics.followUpPrompts'])->lockForUpdate()->firstOrFail();
            if ($draft->current_key !== 'draft') throw ValidationException::withMessages(['release' => 'Only the current draft can be published.']);
            $errors = $this->validateForPublish($draft);
            if ($errors) throw ValidationException::withMessages($errors);
            ChatBuddyRelease::query()->published()->update(['status' => 'archived', 'current_key' => null]);
            $draft->update(['status' => 'published', 'current_key' => 'published', 'published_at' => now(), 'published_by_user_id' => $actor->id]);
            ChatBuddyPublicationLog::query()->create(['chat_buddy_release_id' => $draft->id, 'user_id' => $actor->id, 'action' => 'published']);
            $this->copyToDraft($draft->fresh(['topics.phrases','topics.links','topics.followUpPrompts']), $actor);
            return $draft->fresh();
        });
    }

    public function deleteDraftTopic(ChatBuddyTopic $topic): void
    {
        abort_unless($topic->release()->draft()->exists(), 404);
        $topic->delete();
    }

    /** @return array<string, string> */
    public function validateForPublish(ChatBuddyRelease $draft): array
    {
        $errors = [];
        if (blank($draft->fallback_message)) $errors['fallback_message'] = 'A fallback reply is required.';
        foreach ($draft->topics as $topic) {
            if (blank($topic->title) || blank($topic->reply)) $errors['topics'] = 'Every topic needs a title and approved reply.';
            if ($topic->phrases->filter(fn ($phrase) => filled(trim($phrase->phrase)))->isEmpty()) $errors['phrases'] = 'Every topic needs at least one phrase.';
            if ($topic->is_safety && $topic->links->contains(fn ($link) => $link->link_type === 'external' && blank($link->url))) $errors['safety_links'] = 'Safety links must have approved destinations.';
        }
        $hash = $this->safetyHash($draft);
        if ($draft->topics->contains(fn ($topic) => $topic->is_safety) && (! $draft->safety_content_approved || $draft->safety_content_hash !== $hash)) $errors['safety_content_approved'] = 'Safety content must be approved again after edits.';
        return $errors;
    }
    public function safetyHash(ChatBuddyRelease $release): string { return hash('sha256', $release->safety_message.'|'. $release->topics->where('is_safety', true)->map(fn ($topic) => $topic->reply.'|'.$topic->phrases->pluck('phrase')->join('|').$topic->links->pluck('url')->join('|'))->join('||')); }
}
