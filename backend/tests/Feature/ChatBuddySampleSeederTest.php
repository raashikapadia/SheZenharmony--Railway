<?php
namespace Tests\Feature;
use Database\Seeders\ChatBuddySampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatBuddySampleSeederTest extends TestCase
{
    use RefreshDatabase;
    public function test_sample_seed_is_draft_only_and_never_restores_deleted_topic(): void
    {
        $this->seed(ChatBuddySampleSeeder::class);
        $release = \App\Models\ChatBuddyRelease::query()->draft()->firstOrFail();
        $topic = $release->topics()->firstOrFail();
        $topic->delete();
        $this->seed(ChatBuddySampleSeeder::class);
        $this->assertDatabaseMissing('chat_buddy_topics', ['id' => $topic->id]);
        $this->assertDatabaseHas('chat_buddy_seed_markers', ['fixture_key' => ChatBuddySampleSeeder::FIXTURE_KEY]);
        $this->assertSame('draft', $release->fresh()->status);
    }
}
