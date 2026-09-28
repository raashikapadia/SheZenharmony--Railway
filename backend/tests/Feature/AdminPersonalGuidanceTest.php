<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminPersonalGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_students_and_guests_cannot_manage_guidance(): void
    {
        $this->get('/admin/personal-guidance')->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get('/admin/personal-guidance')->assertForbidden();
        $this->actingAs($student)->post('/admin/personal-guidance', [
            'type' => 'affirmation', 'content' => 'x', 'is_active' => '1',
        ])->assertForbidden();
        Auth::logout();
    }

    public function test_admin_can_create_an_affirmation_and_a_quote(): void
    {
        $admin = $this->admin();
        $category = ContentCategory::query()->create(['name' => 'Confidence', 'slug' => 'confidence', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/personal-guidance', [
            'type' => 'affirmation',
            'content' => 'I am capable of handling whatever today brings.',
            'content_category_id' => $category->id,
            'is_active' => '1',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $this->actingAs($admin)->post('/admin/personal-guidance', [
            'type' => 'quote',
            'content' => 'It always seems impossible until it is done.',
            'author' => 'Nelson Mandela',
            'is_active' => '1',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $this->assertDatabaseCount('personal_guidance', 2);
        $this->assertDatabaseHas('personal_guidance', [
            'type' => 'quote', 'author' => 'Nelson Mandela', 'created_by_user_id' => $admin->id, 'status' => 'published',
        ]);
        $this->assertDatabaseHas('personal_guidance', [
            'type' => 'affirmation', 'content_category_id' => $category->id, 'status' => 'published',
        ]);
    }

    public function test_quote_requires_an_author(): void
    {
        $this->actingAs($this->admin())->post('/admin/personal-guidance', [
            'type' => 'quote',
            'content' => 'A quote with no attribution.',
            'is_active' => '1',
        ])->assertSessionHasErrors('author');

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    /**
     * Coping strategies and wellbeing tips each moved to their own screen;
     * this one only ever manages affirmations and quotes.
     */
    public function test_guidance_and_tip_types_are_rejected_by_this_screen(): void
    {
        $this->actingAs($this->admin())->post('/admin/personal-guidance', [
            'type' => 'guidance',
            'content' => 'One honest step is enough for today.',
        ])->assertSessionHasErrors('type');

        $this->actingAs($this->admin())->post('/admin/personal-guidance', [
            'type' => 'tip',
            'content' => 'Drink some water.',
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_admin_can_edit_attribution_unpublish_and_delete(): void
    {
        $admin = $this->admin();
        $item = PersonalGuidance::query()->create([
            'type' => 'quote',
            'content' => 'Believe you can and you are halfway there.',
            'author' => 'Theodor Roosevelt',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->put("/admin/personal-guidance/{$item->id}", [
            'type' => 'quote',
            'content' => 'Believe you can and you are halfway there.',
            'author' => 'Theodore Roosevelt',
            'is_active' => '1',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $item->refresh();
        $this->assertSame('Theodore Roosevelt', $item->author);
        $this->assertSame('published', $item->status);

        $this->actingAs($admin)->put("/admin/personal-guidance/{$item->id}", [
            'type' => 'quote',
            'content' => $item->content,
            'author' => 'Theodore Roosevelt',
        ]);
        $this->assertSame('unpublished', $item->fresh()->status);

        $this->actingAs($admin)->delete("/admin/personal-guidance/{$item->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('personal_guidance', ['id' => $item->id]);
    }

    public function test_index_filters_by_type_and_status(): void
    {
        $admin = $this->admin();
        PersonalGuidance::query()->create(['type' => 'affirmation', 'content' => 'Affirm one', 'status' => 'published']);
        PersonalGuidance::query()->create(['type' => 'quote', 'content' => 'Quote one', 'author' => 'A. Person', 'status' => 'draft']);

        $this->actingAs($admin)->get('/admin/personal-guidance?type=affirmation')
            ->assertOk()
            ->assertSee('Affirm one')
            ->assertDontSee('Quote one');

        $this->actingAs($admin)->get('/admin/personal-guidance?status=draft')
            ->assertOk()
            ->assertSee('Quote one')
            ->assertDontSee('Affirm one');
    }
}
