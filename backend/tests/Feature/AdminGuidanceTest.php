<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_students_and_guests_cannot_manage_guidance(): void
    {
        $this->get('/admin/guidance')->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get('/admin/guidance')->assertForbidden();
        $this->actingAs($student)->post('/admin/guidance', [
            'title' => 'x', 'content' => 'x',
        ])->assertForbidden();
        Auth::logout();

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_admin_can_create_guidance_with_a_category_and_optional_fields(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Stress', 'slug' => 'stress', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/guidance', [
            'title' => 'Take a short break',
            'content' => "If you're feeling overwhelmed, step away for a few minutes, take some slow breaths, and give yourself time to reset.",
            'content_category_id' => $category->id,
            'steps' => 'Take three slow breaths before your next class',
            'resource_url' => 'https://example.org/breathing',
            'is_active' => '1',
        ])->assertRedirect(route('admin.guidance.index'));

        $this->assertDatabaseHas('personal_guidance', [
            'type' => 'guidance',
            'title' => 'Take a short break',
            'content_category_id' => $category->id,
            'steps' => 'Take three slow breaths before your next class',
            'resource_url' => 'https://example.org/breathing',
            'status' => 'published',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_create_guidance_with_nothing_but_a_title_and_advice(): void
    {
        $this->actingAs($this->admin())->post('/admin/guidance', [
            'title' => 'Reach out to someone',
            'content' => 'Send a message or talk to someone who makes you feel safe.',
            'is_active' => '1',
        ])->assertRedirect(route('admin.guidance.index'));

        $this->assertDatabaseHas('personal_guidance', [
            'title' => 'Reach out to someone',
            'content_category_id' => null,
            'steps' => null,
            'resource_url' => null,
        ]);
    }

    public function test_title_and_content_are_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/guidance', ['is_active' => '1'])
            ->assertSessionHasErrors(['title', 'content']);

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_resource_url_must_be_a_valid_web_address(): void
    {
        $this->actingAs($this->admin())->post('/admin/guidance', [
            'title' => 'Talk to someone',
            'content' => 'Reach out when you need to.',
            'resource_url' => 'not-a-link',
            'is_active' => '1',
        ])->assertSessionHasErrors(['resource_url']);

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_unchecking_published_marks_it_as_draft(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/guidance', [
            'title' => 'Reach out to someone',
            'content' => 'Send a message or talk to someone who makes you feel safe.',
        ])->assertRedirect();

        $this->assertDatabaseHas('personal_guidance', ['title' => 'Reach out to someone', 'status' => 'unpublished']);
    }

    public function test_admin_can_edit_the_category_and_delete(): void
    {
        $admin = $this->admin();
        $stress = ContentCategory::query()->create(['name' => 'Stress', 'slug' => 'stress', 'is_active' => true]);
        $sleep = ContentCategory::query()->create(['name' => 'Sleep', 'slug' => 'sleep', 'is_active' => true]);

        $guidance = PersonalGuidance::query()->create([
            'type' => 'guidance', 'title' => 'Wind down', 'content' => 'Try a calming routine before bed.',
            'content_category_id' => $stress->id, 'status' => 'published',
        ]);

        $this->actingAs($admin)->get("/admin/guidance/{$guidance->id}/edit")
            ->assertOk()
            ->assertSee('Wind down');

        $this->actingAs($admin)->put("/admin/guidance/{$guidance->id}", [
            'title' => 'Wind down before bed',
            'content' => $guidance->content,
            'content_category_id' => $sleep->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.guidance.index'));

        $guidance->refresh();
        $this->assertSame('Wind down before bed', $guidance->title);
        $this->assertSame($sleep->id, $guidance->content_category_id);

        $this->actingAs($admin)->delete("/admin/guidance/{$guidance->id}")->assertRedirect();
        $this->assertDatabaseMissing('personal_guidance', ['id' => $guidance->id]);
    }

    public function test_editing_a_quote_or_affirmation_through_this_screen_is_refused(): void
    {
        $admin = $this->admin();
        $quote = PersonalGuidance::query()->create([
            'type' => 'quote', 'content' => 'A quote.', 'author' => 'Someone', 'status' => 'published',
        ]);

        $this->actingAs($admin)->get("/admin/guidance/{$quote->id}/edit")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/guidance/{$quote->id}")->assertNotFound();
        $this->assertDatabaseHas('personal_guidance', ['id' => $quote->id]);
    }

    public function test_index_can_be_searched_and_filtered_by_category(): void
    {
        $admin = $this->admin();
        $stress = ContentCategory::query()->create(['name' => 'Stress', 'slug' => 'stress', 'is_active' => true]);
        $sleep = ContentCategory::query()->create(['name' => 'Sleep', 'slug' => 'sleep', 'is_active' => true]);

        PersonalGuidance::query()->create(['type' => 'guidance', 'title' => 'Take a moment to breathe', 'content' => 'Pause and breathe.', 'content_category_id' => $stress->id, 'status' => 'published']);
        PersonalGuidance::query()->create(['type' => 'guidance', 'title' => 'Create a bedtime routine', 'content' => 'Wind down early.', 'content_category_id' => $sleep->id, 'status' => 'published']);

        $this->actingAs($admin)->get('/admin/guidance?search=breathe')
            ->assertOk()->assertSee('Take a moment to breathe')->assertDontSee('Create a bedtime routine');

        $this->actingAs($admin)->get("/admin/guidance?category_id={$sleep->id}")
            ->assertOk()->assertSee('Create a bedtime routine')->assertDontSee('Take a moment to breathe');
    }

    /**
     * The whole point of the redesign: an admin fills in a simple form, and
     * it is immediately visible to every student through the exact same
     * browse endpoint the mobile app already calls — nothing hard-coded and
     * no rule to configure in between.
     */
    public function test_guidance_created_through_the_simple_form_is_immediately_visible_to_students(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Stress', 'slug' => 'stress', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/guidance', [
            'title' => 'Take a short break',
            'content' => 'Step away for a few minutes and take some slow breaths.',
            'content_category_id' => $category->id,
            'is_active' => '1',
        ])->assertRedirect();
        Auth::logout();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        $this->getJson('/api/v1/personal-guidance?type=guidance')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Take a short break', 'category' => 'Stress']);
    }
}
