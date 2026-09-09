<?php

namespace Tests\Feature;

use App\Models\HelplineResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminHelplineResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'National Crisis Helpline',
            'organisation' => 'Ministry of Health',
            'description' => 'Free, confidential support at any hour.',
            'phone' => '1325',
            'availability' => '24 hours, 7 days',
            'category' => 'Crisis',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_students_and_guests_cannot_manage_resources(): void
    {
        $this->get('/admin/resources')->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)->get('/admin/resources')->assertForbidden();
        $this->actingAs($student)->post('/admin/resources', $this->payload())->assertForbidden();
        Auth::logout();

        $this->assertDatabaseCount('helpline_resources', 0);
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $resource = HelplineResource::query()->create($this->payload(['is_active' => true]));

        $this->actingAs($admin)->get('/admin/resources')->assertOk()->assertSee('National Crisis Helpline');
        $this->actingAs($admin)->get('/admin/resources/create')->assertOk();
        $this->actingAs($admin)->get("/admin/resources/{$resource->id}/edit")->assertOk()->assertSee('1325');
    }

    public function test_admin_can_create_update_and_delete_a_resource(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/resources', $this->payload())
            ->assertRedirect(route('admin.resources.index'));

        $this->assertDatabaseHas('helpline_resources', [
            'name' => 'National Crisis Helpline',
            'phone' => '1325',
            'is_active' => true,
            'is_emergency' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $resource = HelplineResource::query()->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/resources/{$resource->id}", $this->payload([
                'name' => 'National Crisis Line',
                'is_emergency' => '1',
            ]))
            ->assertRedirect(route('admin.resources.index'));

        $this->assertDatabaseHas('helpline_resources', [
            'id' => $resource->id,
            'name' => 'National Crisis Line',
            'is_emergency' => true,
        ]);

        $this->actingAs($admin)->delete("/admin/resources/{$resource->id}")->assertRedirect();
        $this->assertDatabaseCount('helpline_resources', 0);
    }

    public function test_a_resource_requires_a_name_and_phone_number(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/resources', $this->payload(['name' => '', 'phone' => '']))
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertDatabaseCount('helpline_resources', 0);
    }

    public function test_unchecking_active_hides_the_resource_from_students(): void
    {
        $admin = $this->admin();

        // An unchecked checkbox is simply absent from the form payload.
        $payload = $this->payload();
        unset($payload['is_active']);

        $this->actingAs($admin)->post('/admin/resources', $payload)->assertRedirect();

        $this->assertDatabaseHas('helpline_resources', ['name' => 'National Crisis Helpline', 'is_active' => false]);
        $this->getJson('/api/v1/helplines')->assertOk()->assertJsonCount(0, 'data');
    }
}
