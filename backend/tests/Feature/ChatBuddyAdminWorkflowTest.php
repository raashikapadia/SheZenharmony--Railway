<?php

namespace Tests\Feature;

use App\Models\ChatBuddyRelease;
use App\Models\ChatBuddyTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatBuddyAdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_manager_and_preview_the_current_draft(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draft = ChatBuddyRelease::query()->create(['status' => 'draft', 'current_key' => 'draft', 'fallback_message' => 'Fallback']);
        $topic = $draft->topics()->create(['title' => 'Exam stress', 'reply' => 'Take one small step.', 'priority' => 10]);
        $topic->phrases()->create(['phrase' => 'exam stress', 'position' => 1]);

        $this->actingAs($admin)->get(route('admin.chatbuddy.releases.index'))->assertOk()->assertSee('Exam stress')->assertSee('Overview')->assertSee('Create Rule')->assertSee('Edit &amp; Delete Rules', false)->assertSee('Preview');
        $this->actingAs($admin)->get(route('admin.chatbuddy.releases.index', ['section' => 'create']))->assertOk()->assertSee('Create a draft rule')->assertDontSee('Edit topic / Exam stress');
        $this->actingAs($admin)->get(route('admin.chatbuddy.releases.index', ['section' => 'preview']))->assertOk()->assertSee('Preview a student message')->assertDontSee('Create a draft rule');
        $this->actingAs($admin)->get(route('admin.chatbuddy.releases.index', ['topic_id' => $topic->id]))->assertOk()->assertSee('Edit topic / Exam stress');
        $this->actingAs($admin)->post(route('admin.chatbuddy.releases.preview'), ['release_id' => $draft->id, 'message' => 'I have exam stress'])->assertOk()->assertSee('Take one small step.');
    }

    public function test_student_cannot_open_the_admin_manager(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get(route('admin.chatbuddy.releases.index'))->assertForbidden();
    }

    public function test_admin_settings_and_topic_forms_persist_all_fields(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draft = ChatBuddyRelease::query()->create(['status' => 'draft', 'current_key' => 'draft', 'fallback_message' => 'Old fallback']);

        $this->actingAs($admin)->patch(route('admin.chatbuddy.releases.settings'), [
            'welcome_message' => 'Welcome text',
            'fallback_message' => 'Try one of these topics.',
            'safety_message' => 'Please seek approved support.',
            'suggested_topics' => ["Exam stress\nStudy breaks"],
        ])->assertRedirect();

        $this->assertDatabaseHas('chat_buddy_releases', [
            'id' => $draft->id,
            'welcome_message' => 'Welcome text',
            'fallback_message' => 'Try one of these topics.',
        ]);
        $this->assertSame(['Exam stress', 'Study breaks'], $draft->fresh()->suggested_topics);

        $this->actingAs($admin)->post(route('admin.chatbuddy.releases.topics.store'), [
            'title' => 'Exam stress',
            'priority' => 10,
            'reply' => 'Take one small step.',
            'phrases' => ["exam stress\nworried about exams"],
            'follow_up_prompts_text' => "Try a study break\nTry a breathing activity",
            'links_text' => '',
            'typed_links' => [],
        ])->assertRedirect();

        $topic = ChatBuddyTopic::query()->where('title', 'Exam stress')->firstOrFail();
        $this->assertCount(2, $topic->phrases()->get());
        $this->assertCount(2, $topic->followUpPrompts()->get());
    }

    public function test_admin_can_edit_and_delete_a_draft_rule(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draft = ChatBuddyRelease::query()->create(['status' => 'draft', 'current_key' => 'draft', 'fallback_message' => 'Fallback']);
        $topic = $draft->topics()->create(['title' => 'Seeded example', 'reply' => 'Old reply', 'priority' => 50]);
        $topic->phrases()->create(['phrase' => 'old phrase', 'position' => 1]);

        $this->actingAs($admin)->put(route('admin.chatbuddy.releases.topics.update', $topic), [
            'title' => 'Edited example', 'description' => 'Updated description', 'priority' => 5,
            'reply' => 'New reply', 'phrases' => ['new phrase'], 'follow_up_prompts_text' => '', 'links_text' => '',
        ])->assertRedirect();
        $this->assertDatabaseHas('chat_buddy_topics', ['id' => $topic->id, 'title' => 'Edited example', 'description' => 'Updated description', 'priority' => 5]);

        $this->actingAs($admin)->delete(route('admin.chatbuddy.releases.topics.destroy', $topic))->assertRedirect();
        $this->assertDatabaseMissing('chat_buddy_topics', ['id' => $topic->id]);
    }
}
