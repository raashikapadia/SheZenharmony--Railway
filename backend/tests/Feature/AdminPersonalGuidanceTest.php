<?php

namespace Tests\Feature;

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
            'type' => 'affirmation', 'content' => 'x', 'status' => 'published',
        ])->assertForbidden();
        Auth::logout();
    }

    public function test_admin_can_create_each_content_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/personal-guidance', [
            'type' => 'affirmation',
            'content' => 'I am capable of handling whatever today brings.',
            'category' => 'Confidence',
            'status' => 'published',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $this->actingAs($admin)->post('/admin/personal-guidance', [
            'type' => 'quote',
            'content' => 'It always seems impossible until it is done.',
            'author' => 'Nelson Mandela',
            'status' => 'published',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $this->actingAs($admin)->post('/admin/personal-guidance', [
            'type' => 'guidance',
            'content' => 'One honest step is enough for today.',
            'status' => 'draft',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $this->assertDatabaseCount('personal_guidance', 3);
        $this->assertDatabaseHas('personal_guidance', [
            'type' => 'quote', 'author' => 'Nelson Mandela', 'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_quote_requires_an_author(): void
    {
        $this->actingAs($this->admin())->post('/admin/personal-guidance', [
            'type' => 'quote',
            'content' => 'A quote with no attribution.',
            'status' => 'published',
        ])->assertSessionHasErrors('author');

        $this->assertDatabaseCount('personal_guidance', 0);
    }

    public function test_expiry_must_be_after_publish_date(): void
    {
        $this->actingAs($this->admin())->post('/admin/personal-guidance', [
            'type' => 'affirmation',
            'content' => 'Timing matters.',
            'status' => 'published',
            'publish_at' => '2026-09-10T09:00',
            'expires_at' => '2026-09-01T09:00',
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_admin_can_edit_attribution_publish_unpublish_and_delete(): void
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
            'status' => 'published',
        ])->assertRedirect(route('admin.personal-guidance.index'));

        $item->refresh();
        $this->assertSame('Theodore Roosevelt', $item->author);
        $this->assertSame('published', $item->status);

        $this->actingAs($admin)->put("/admin/personal-guidance/{$item->id}", [
            'type' => 'quote',
            'content' => $item->content,
            'author' => 'Theodore Roosevelt',
            'status' => 'unpublished',
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
