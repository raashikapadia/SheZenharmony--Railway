<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_students_and_guests_cannot_manage_tips(): void
    {
        $this->get('/admin/wellbeing-tips')->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get('/admin/wellbeing-tips')->assertForbidden();
        $this->actingAs($student)->post('/admin/wellbeing-tips', [
            'title' => 'x', 'content' => 'x',
        ])->assertForbidden();
        Auth::logout();

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_admin_can_create_a_tip_with_a_category_and_suggested_action(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Wellbeing', 'slug' => 'wellbeing', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/wellbeing-tips', [
            'title' => 'Drink some water',
            'content' => 'Staying hydrated helps your focus and your mood.',
            'content_category_id' => $category->id,
            'steps' => 'Fill a bottle and keep it on your desk',
            'is_active' => '1',
        ])->assertRedirect(route('admin.wellbeing-tips.index'));

        $this->assertDatabaseHas('personal_guidance', [
            'type' => 'tip',
            'title' => 'Drink some water',
            'content_category_id' => $category->id,
            'steps' => 'Fill a bottle and keep it on your desk',
            'status' => 'published',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_title_and_content_are_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/wellbeing-tips', ['is_active' => '1'])
            ->assertSessionHasErrors(['title', 'content']);

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_unchecking_published_marks_it_as_draft(): void
    {
        $this->actingAs($this->admin())->post('/admin/wellbeing-tips', [
            'title' => 'Take a short break',
            'content' => 'A five minute pause helps you come back with a clearer head.',
        ])->assertRedirect();

        $this->assertDatabaseHas('personal_guidance', ['title' => 'Take a short break', 'status' => 'unpublished']);
    }

    public function test_admin_can_edit_and_delete(): void
    {
        $admin = $this->admin();
        $tip = PersonalGuidance::query()->create([
            'type' => 'tip', 'title' => 'Take a walk', 'content' => 'A short walk can clear your head.', 'status' => 'published',
        ]);

        $this->actingAs($admin)->get("/admin/wellbeing-tips/{$tip->id}/edit")->assertOk()->assertSee('Take a walk');

        $this->actingAs($admin)->put("/admin/wellbeing-tips/{$tip->id}", [
            'title' => 'Take a ten minute walk',
            'content' => $tip->content,
            'is_active' => '1',
        ])->assertRedirect(route('admin.wellbeing-tips.index'));

        $this->assertSame('Take a ten minute walk', $tip->fresh()->title);

        $this->actingAs($admin)->delete("/admin/wellbeing-tips/{$tip->id}")->assertRedirect();
        $this->assertDatabaseMissing('personal_guidance', ['id' => $tip->id]);
    }

    public function test_editing_a_quote_or_guidance_through_this_screen_is_refused(): void
    {
        $admin = $this->admin();
        $quote = PersonalGuidance::query()->create([
            'type' => 'quote', 'content' => 'A quote.', 'author' => 'Someone', 'status' => 'published',
        ]);

        $this->actingAs($admin)->get("/admin/wellbeing-tips/{$quote->id}/edit")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/wellbeing-tips/{$quote->id}")->assertNotFound();
    }

    public function test_index_can_be_searched_and_filtered_by_category(): void
    {
        $admin = $this->admin();
        $wellbeing = ContentCategory::query()->create(['name' => 'Wellbeing', 'slug' => 'wellbeing', 'is_active' => true]);
        $focus = ContentCategory::query()->create(['name' => 'Focus', 'slug' => 'focus', 'is_active' => true]);

        PersonalGuidance::query()->create(['type' => 'tip', 'title' => 'Drink some water', 'content' => 'Stay hydrated.', 'content_category_id' => $wellbeing->id, 'status' => 'published']);
        PersonalGuidance::query()->create(['type' => 'tip', 'title' => 'Take a screen break', 'content' => 'Look away from the screen.', 'content_category_id' => $focus->id, 'status' => 'published']);

        $this->actingAs($admin)->get('/admin/wellbeing-tips?search=water')
            ->assertOk()->assertSee('Drink some water')->assertDontSee('Take a screen break');

        $this->actingAs($admin)->get("/admin/wellbeing-tips?category_id={$focus->id}")
            ->assertOk()->assertSee('Take a screen break')->assertDontSee('Drink some water');
    }

    public function test_tip_created_through_the_simple_form_is_immediately_visible_to_students(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/wellbeing-tips', [
            'title' => 'Take a short break',
            'content' => 'A five minute pause helps you come back with a clearer head.',
            'is_active' => '1',
        ])->assertRedirect();
        Auth::logout();

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        $this->getJson('/api/v1/personal-guidance?type=tip')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Take a short break']);
    }
}
