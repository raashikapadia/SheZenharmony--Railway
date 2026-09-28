<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\PersonalGuidance;
use App\Models\PersonalGuidanceRecommendation;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
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

    public function test_browse_lists_only_published_items_of_the_requested_type_in_display_order(): void
    {
        $this->actingAsStudent();
        $second = $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content' => 'Second tip', 'position' => 2]);
        $first = $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content' => 'First tip', 'position' => 1]);
        $this->guidance(['type' => PersonalGuidance::TYPE_QUOTE, 'content' => 'A quote', 'author' => 'Someone']);
        $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'status' => PersonalGuidance::STATUS_DRAFT]);

        $this->getJson('/api/v1/personal-guidance?type=guidance')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_browse_requires_a_recognised_type(): void
    {
        $this->actingAsStudent();

        $this->getJson('/api/v1/personal-guidance?type=not-a-real-type')->assertStatus(422);
        $this->getJson('/api/v1/personal-guidance')->assertStatus(422);
    }

    public function test_browse_supports_affirmations_and_tips_too(): void
    {
        $this->actingAsStudent();
        $this->guidance(['type' => PersonalGuidance::TYPE_AFFIRMATION, 'content' => 'You are enough.']);
        $this->guidance(['type' => PersonalGuidance::TYPE_TIP, 'content' => 'Drink some water.']);

        $this->getJson('/api/v1/personal-guidance?type=affirmation')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.content', 'You are enough.');

        $this->getJson('/api/v1/personal-guidance?type=tip')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.content', 'Drink some water.');
    }

    public function test_browse_can_be_narrowed_to_one_category(): void
    {
        $this->actingAsStudent();
        $sleep = ContentCategory::query()->create(['name' => 'Sleep', 'slug' => 'sleep', 'is_active' => true]);
        $focus = ContentCategory::query()->create(['name' => 'Focus', 'slug' => 'focus', 'is_active' => true]);
        $sleepTip = $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content_category_id' => $sleep->id]);
        $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content_category_id' => $focus->id]);

        $this->getJson("/api/v1/personal-guidance?type=guidance&category_id={$sleep->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sleepTip->id)
            ->assertJsonPath('data.0.category', 'Sleep');
    }

    public function test_categories_endpoint_only_returns_categories_with_a_visible_item_of_that_type(): void
    {
        $this->actingAsStudent();
        $used = ContentCategory::query()->create(['name' => 'Sleep', 'slug' => 'sleep', 'is_active' => true]);
        $emptyForTips = ContentCategory::query()->create(['name' => 'Grief', 'slug' => 'grief', 'is_active' => true]);
        $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content_category_id' => $used->id]);
        $this->guidance(['type' => PersonalGuidance::TYPE_QUOTE, 'content_category_id' => $emptyForTips->id, 'author' => 'Someone']);

        $names = $this->getJson('/api/v1/personal-guidance/categories?type=guidance')
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Sleep'], $names);
    }

    public function test_for_you_can_surface_a_matched_quote_alongside_a_matched_tip(): void
    {
        $student = $this->actingAsStudent();

        $questionnaire = Questionnaire::query()->create([
            'title' => 'Guidance fixture', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
        $band = $questionnaire->scoreBands()->create([
            'code' => 'elevated', 'label' => 'Elevated', 'min_score' => 0, 'max_score' => 100,
            'position' => 1, 'is_active' => true,
        ]);
        StressAssessment::query()->create([
            'questionnaire_id' => $questionnaire->id,
            'student_identity_id' => $student->studentIdentity->id,
            'stress_score_band_id' => $band->id,
            'assessment_status' => 'completed',
            'total_score' => 50,
            'completed_at' => now(),
        ]);

        $tip = $this->guidance(['type' => PersonalGuidance::TYPE_GUIDANCE, 'content' => 'Try box breathing.']);
        $quote = $this->guidance(['type' => PersonalGuidance::TYPE_QUOTE, 'content' => 'This too shall pass.', 'author' => 'Anon']);
        PersonalGuidanceRecommendation::query()->create(['personal_guidance_id' => $tip->id, 'stress_score_band_id' => $band->id, 'is_active' => true]);
        PersonalGuidanceRecommendation::query()->create(['personal_guidance_id' => $quote->id, 'stress_score_band_id' => $band->id, 'is_active' => true]);

        $types = $this->getJson('/api/v1/personal-guidance/for-you')
            ->assertOk()
            ->json('data.*.type');

        $this->assertContains('guidance', $types);
        $this->assertContains('quote', $types);
    }
}
