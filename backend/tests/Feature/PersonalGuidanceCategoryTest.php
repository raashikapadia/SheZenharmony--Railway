<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalGuidanceCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_students_and_guests_cannot_manage_categories(): void
    {
        $this->get('/admin/personal-guidance-categories')->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get('/admin/personal-guidance-categories')->assertForbidden();
        $this->actingAs($student)->post('/admin/personal-guidance-categories', ['name' => 'Anxiety'])->assertForbidden();

        $this->assertDatabaseCount('content_categories', 0);
    }

    public function test_admin_can_create_a_category_with_an_auto_generated_slug(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/personal-guidance-categories', ['name' => 'Anxiety', 'is_active' => '1'])
            ->assertRedirect(route('admin.personal-guidance-categories.index'));

        $this->assertDatabaseHas('content_categories', [
            'name' => 'Anxiety',
            'slug' => 'anxiety',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_rename_a_category_without_changing_its_slug(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Anxiety', 'slug' => 'anxiety', 'is_active' => true]);

        $this->actingAs($admin)
            ->put("/admin/personal-guidance-categories/{$category->id}", ['name' => 'Anxiety relief', 'is_active' => '1'])
            ->assertRedirect(route('admin.personal-guidance-categories.index'));

        $category->refresh();
        $this->assertSame('Anxiety relief', $category->name);
        $this->assertSame('anxiety', $category->slug);
    }

    public function test_two_categories_cannot_share_a_name(): void
    {
        $admin = $this->admin();
        ContentCategory::query()->create(['name' => 'Sleep', 'slug' => 'sleep', 'is_active' => true]);

        $this->actingAs($admin)
            ->post('/admin/personal-guidance-categories', ['name' => 'Sleep', 'is_active' => '1'])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('content_categories', 1);
    }

    /**
     * The FK is nullOnDelete, so deleting a category in use must un-categorise
     * its items rather than blocking the delete or deleting the items too.
     */
    public function test_deleting_a_category_in_use_leaves_its_items_intact_and_uncategorised(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Focus', 'slug' => 'focus', 'is_active' => true]);
        $tip = PersonalGuidance::query()->create([
            'type' => PersonalGuidance::TYPE_GUIDANCE,
            'content' => 'Try a five minute reset.',
            'content_category_id' => $category->id,
            'status' => PersonalGuidance::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)
            ->delete("/admin/personal-guidance-categories/{$category->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('content_categories', ['id' => $category->id]);
        $tip->refresh();
        $this->assertNotNull($tip->id);
        $this->assertNull($tip->content_category_id);
    }

    public function test_a_category_requires_a_name(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/personal-guidance-categories', ['name' => ''])
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('content_categories', 0);
    }
}
