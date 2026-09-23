<?php
namespace Tests\Feature;
use App\Models\ChatBuddyRelease;
use App\Services\ShezenChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatBuddyMatchingTest extends TestCase
{
    use RefreshDatabase;
    private function release(): ChatBuddyRelease { return ChatBuddyRelease::query()->create(['status' => 'published', 'current_key' => 'published', 'welcome_message' => 'Welcome', 'fallback_message' => 'Fallback', 'safety_message' => 'Safety', 'suggested_topics' => ['Exam stress']]); }
    public function test_safety_matches_before_normal_priority(): void
    {
        $r = $this->release();
        $normal = $r->topics()->create(['title' => 'Stress', 'priority' => 1, 'reply' => 'Normal']);
        $normal->phrases()->create(['phrase' => 'help']);
        $safety = $r->topics()->create(['title' => 'Safety', 'priority' => 99, 'is_safety' => true, 'reply' => 'Safety']);
        $safety->phrases()->create(['phrase' => 'help me now']);
        $result = app(ShezenChatService::class)->replyForPublished('I need help me now');
        $this->assertSame('Safety', $result['message']);
        $this->assertTrue($result['is_safety']);
    }
    public function test_unmatched_message_uses_published_fallback(): void
    {
        $this->release();
        $result = app(ShezenChatService::class)->replyForPublished('unrecognised words');
        $this->assertSame('Fallback', $result['message']);
        $this->assertTrue($result['is_fallback']);
    }
}
