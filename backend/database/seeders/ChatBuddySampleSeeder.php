<?php

namespace Database\Seeders;

use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddySeedMarker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatBuddySampleSeeder extends Seeder
{
    public const FIXTURE_KEY = 'chat-buddy-placeholder-v1';

    public function run(): void
    {
        if (ChatBuddySeedMarker::query()->where('fixture_key', self::FIXTURE_KEY)->exists()) return;
        DB::transaction(function (): void {
            $release = ChatBuddyRelease::query()->draft()->first() ?? ChatBuddyRelease::query()->create([
                'status' => 'draft', 'current_key' => 'draft', 'welcome_message' => 'PLACEHOLDER FOR CLIENT REVIEW: Welcome to Chat Buddy.', 'fallback_message' => 'PLACEHOLDER FOR CLIENT REVIEW: I do not have a matching suggestion yet.', 'safety_message' => null, 'suggested_topics' => ['Exam stress (placeholder)', 'Feeling overwhelmed (placeholder)', 'Breathing activity (placeholder)'], 'created_by_user_id' => null,
            ]);
            $topics = [
                ['Exam stress (placeholder)', ['exam stress', 'tests', 'assessment pressure'], 'PLACEHOLDER FOR CLIENT REVIEW: Exams can feel heavy. A small next step may help.', 20],
                ['Feeling overwhelmed (placeholder)', ['overwhelmed', 'too much', 'cannot cope'], 'PLACEHOLDER FOR CLIENT REVIEW: You do not need to solve everything at once. Pick one small piece.', 30],
                ['Breathing activity (placeholder)', ['breathing', 'breathe', 'calm down'], 'PLACEHOLDER FOR CLIENT REVIEW: Try a gentle breathing activity for a few comfortable rounds.', 40],
                ['Study breaks (placeholder)', ['study break', 'break from studying', 'rest'], 'PLACEHOLDER FOR CLIENT REVIEW: A short break can help your attention reset.', 50],
                ['Finding support (placeholder)', ['find support', 'talk to someone', 'help'], 'PLACEHOLDER FOR CLIENT REVIEW: Consider reaching out to someone you trust or an approved support service.', 60],
            ];
            foreach ($topics as [$title, $phrases, $reply, $priority]) {
                $topic = $release->topics()->create(['title' => $title, 'priority' => $priority, 'reply' => $reply]);
                foreach ($phrases as $position => $phrase) $topic->phrases()->create(['phrase' => $phrase, 'position' => $position + 1]);
            }
            ChatBuddySeedMarker::query()->create(['fixture_key' => self::FIXTURE_KEY]);
        });
    }
}
