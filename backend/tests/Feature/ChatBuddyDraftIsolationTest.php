<?php

namespace Tests\Feature;

use App\Models\ChatBuddyRelease;
use App\Services\ShezenChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatBuddyDraftIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_payload_ignores_draft_placeholder_content(): void
    {
        ChatBuddyRelease::query()->create([
            'status' => 'draft',
            'current_key' => 'draft',
            'welcome_message' => 'PLACEHOLDER DRAFT ONLY',
            'fallback_message' => 'PLACEHOLDER FALLBACK',
            'suggested_topics' => ['PLACEHOLDER TOPIC'],
        ]);

        $payload = app(ShezenChatService::class)->publishedPayload();

        $this->assertFalse($payload['available']);
        $this->assertNull($payload['welcome_message']);
        $this->assertSame([], $payload['suggested_topics']);
    }
}
