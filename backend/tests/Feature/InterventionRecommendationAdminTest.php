<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterventionRecommendationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_target_and_retarget_intervention_stress_levels(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$low, $high] = $this->bands();

        $this->actingAs($admin)->post('/admin/interventions', [
            'title' => 'Progressive relaxation',
            'content_type' => 'relaxation',
            'instructions' => 'Tense and release each muscle group.',
            'is_active' => '1',
            'recommended_band_ids' => [$high->id],
        ])->assertRedirect(route('admin.interventions.index'));

        $intervention = Intervention::query()->where('title', 'Progressive relaxation')->sole();
        $this->assertSame([$high->id], $intervention->recommendations()->where('is_active', true)->pluck('stress_score_band_id')->all());

        // Retarget to the low band only — the high-band row is deactivated, not deleted.
        $this->actingAs($admin)->put("/admin/interventions/{$intervention->id}", [
            'title' => 'Progressive relaxation',
            'content_type' => 'relaxation',
            'instructions' => 'Tense and release each muscle group.',
            'is_active' => '1',
            'recommended_band_ids' => [$low->id],
        ])->assertRedirect(route('admin.interventions.index'));

        $this->assertSame([$low->id], $intervention->recommendations()->where('is_active', true)->pluck('stress_score_band_id')->all());
        $this->assertDatabaseHas('intervention_recommendations', [
            'intervention_id' => $intervention->id, 'stress_score_band_id' => $high->id, 'is_active' => false,
        ]);

        // "All levels" clears every specific band.
        $this->actingAs($admin)->put("/admin/interventions/{$intervention->id}", [
            'title' => 'Progressive relaxation',
            'content_type' => 'relaxation',
            'instructions' => 'Tense and release each muscle group.',
            'is_active' => '1',
            'all_levels' => '1',
            'recommended_band_ids' => [$low->id],
        ])->assertRedirect(route('admin.interventions.index'));

        $this->assertSame(0, $intervention->recommendations()->where('is_active', true)->count());
    }

    public function test_recommended_band_ids_must_exist(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post('/admin/interventions', [
            'title' => 'Broken', 'content_type' => 'resource', 'is_active' => '1',
            'recommended_band_ids' => [999999],
        ])->assertSessionHasErrors('recommended_band_ids.0');

        $this->assertDatabaseMissing('interventions', ['title' => 'Broken']);
    }

    /** @return array{0: StressScoreBand, 1: StressScoreBand} */
    private function bands(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Stress', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);

        return [
            $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 29, 'position' => 1, 'is_active' => true]),
            $questionnaire->scoreBands()->create(['code' => 'high', 'label' => 'High', 'min_score' => 60, 'max_score' => 79, 'position' => 3, 'is_active' => true]),
        ];
    }
}
