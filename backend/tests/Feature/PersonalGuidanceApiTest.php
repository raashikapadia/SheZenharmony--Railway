<?php

namespace Tests\Feature;

use App\Models\PersonalGuidance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalGuidanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStudent(): User
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        return $student;
    }

    private function guidance(array $overrides = []): PersonalGuidance
    {
        return PersonalGuidance::query()->create(array_merge([
            'type' => PersonalGuidance::TYPE_AFFIRMATION,
            'content' => 'I can take this one step at a time.',
            'author' => null,
            'category' => 'Confidence',
            'status' => PersonalGuidance::STATUS_PUBLISHED,
            'publish_at' => null,
            'expires_at' => null,
        ], $overrides));
    }

    public function test_current_returns_a_published_item(): void
    {
        $this->actingAsStudent();
        $item = $this->guidance(['content' => 'Steady progress still counts.']);

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.type', 'affirmation')
            ->assertJsonPath('data.content', 'Steady progress still counts.')
            ->assertJsonPath('data.author', null)
            ->assertJsonPath('data.is_favourite', false);
    }

    public function test_draft_and_unpublished_items_are_never_returned(): void
    {
        $this->actingAsStudent();
        $this->guidance(['status' => PersonalGuidance::STATUS_DRAFT]);
        $this->guidance(['status' => PersonalGuidance::STATUS_UNPUBLISHED]);

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_expired_and_future_items_are_never_returned(): void
    {
        $this->actingAsStudent();
        $this->guidance(['expires_at' => now()->subDay()]);
        $this->guidance(['publish_at' => now()->addWeek()]);

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_current_returns_null_data_when_nothing_is_published(): void
    {
        $this->actingAsStudent();

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_another_excludes_the_current_item_when_more_are_available(): void
    {
        $this->actingAsStudent();
        $first = $this->guidance(['content' => 'First']);
        $second = $this->guidance(['content' => 'Second']);

        $response = $this->getJson('/api/v1/personal-guidance/another?exclude='.$first->id)->assertOk();

        $this->assertSame($second->id, $response->json('data.id'));
    }

    public function test_quote_returns_its_author(): void
    {
        $this->actingAsStudent();
        $this->guidance([
            'type' => PersonalGuidance::TYPE_QUOTE,
            'content' => 'It always seems impossible until it is done.',
            'author' => 'Nelson Mandela',
        ]);

        $this->getJson('/api/v1/personal-guidance/current')
            ->assertOk()
            ->assertJsonPath('data.type', 'quote')
            ->assertJsonPath('data.author', 'Nelson Mandela');
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->guidance();

        $this->getJson('/api/v1/personal-guidance/current')->assertUnauthorized();
        $this->getJson('/api/v1/personal-guidance/another')->assertUnauthorized();
        $this->getJson('/api/v1/personal-guidance/favourites')->assertUnauthorized();
    }
}
