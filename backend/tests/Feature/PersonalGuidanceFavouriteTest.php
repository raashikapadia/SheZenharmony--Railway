<?php

namespace Tests\Feature;

use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalGuidanceFavouriteTest extends TestCase
{
    use RefreshDatabase;

    private function guidance(array $overrides = []): PersonalGuidance
    {
        return PersonalGuidance::query()->create(array_merge([
            'type' => PersonalGuidance::TYPE_AFFIRMATION,
            'content' => 'I am doing my best, and that is enough for today.',
            'status' => PersonalGuidance::STATUS_PUBLISHED,
        ], $overrides));
    }

    public function test_student_can_favourite_and_unfavourite_guidance(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);
        $item = $this->guidance();

        $this->postJson("/api/v1/personal-guidance/{$item->id}/favourite")
            ->assertCreated()
            ->assertJsonPath('data.is_favourite', true);

        $this->getJson('/api/v1/personal-guidance/favourites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item->id);

        $this->deleteJson("/api/v1/personal-guidance/{$item->id}/favourite")->assertNoContent();

        $this->getJson('/api/v1/personal-guidance/favourites')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_favourites_are_scoped_to_the_requesting_student(): void
    {
        $item = $this->guidance();

        $owner = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($owner, ['student']);
        $this->postJson("/api/v1/personal-guidance/{$item->id}/favourite")->assertCreated();

        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($other, ['student']);

        $this->getJson('/api/v1/personal-guidance/favourites')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertJsonPath('data.is_favourite', false);
    }

    public function test_cannot_favourite_a_non_visible_item(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);
        $draft = $this->guidance(['status' => PersonalGuidance::STATUS_DRAFT]);

        $this->postJson("/api/v1/personal-guidance/{$draft->id}/favourite")->assertNotFound();
    }

    public function test_saved_item_disappears_when_admin_unpublishes_it(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);
        $item = $this->guidance();

        $this->postJson("/api/v1/personal-guidance/{$item->id}/favourite")->assertCreated();
        $item->update(['status' => PersonalGuidance::STATUS_UNPUBLISHED]);

        $this->getJson('/api/v1/personal-guidance/favourites')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('personal_guidance_favourites', [
            'student_identity_id' => $student->studentIdentity->id,
            'personal_guidance_id' => $item->id,
        ]);
    }
}
