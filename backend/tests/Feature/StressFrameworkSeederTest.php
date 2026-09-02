<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use Database\Seeders\StressFrameworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StressFrameworkSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_runnable_questionnaire_with_the_canonical_bands_idempotently(): void
    {
        $this->seed(StressFrameworkSeeder::class);
        $this->seed(StressFrameworkSeeder::class);

        $questionnaire = Questionnaire::query()
            ->where('title', StressFrameworkSeeder::QUESTIONNAIRE_TITLE)
            ->with(['questions.options', 'scoreBands'])
            ->sole();

        $this->assertSame('published', $questionnaire->status);
        $this->assertTrue($questionnaire->is_active);
        $this->assertCount(10, $questionnaire->questions);
        $this->assertTrue($questionnaire->questions->every(fn ($q) => $q->options->count() === 4));

        $bands = $questionnaire->scoreBands->sortBy('position')->values();
        $this->assertSame(
            [[0, 29], [30, 59], [60, 79], [80, 100]],
            $bands->map(fn ($b) => [$b->min_score, $b->max_score])->all(),
        );
        $this->assertSame(['low', 'moderate', 'high', 'very_high'], $bands->pluck('code')->all());

        // The placeholder scale can actually span 0..100.
        $maxTotal = $questionnaire->questions->sum(fn ($q) => $q->options->max('score'));
        $this->assertSame(100, $maxTotal);
    }

    public function test_it_never_overrides_an_existing_published_stress_questionnaire(): void
    {
        $existing = Questionnaire::query()->create([
            'title' => 'Approved framework', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);

        $this->seed(StressFrameworkSeeder::class);

        $this->assertDatabaseMissing('questionnaires', ['title' => StressFrameworkSeeder::QUESTIONNAIRE_TITLE]);
        $this->assertSame(0, StressScoreBand::query()->count());
        $this->assertSame(1, Questionnaire::query()->where('type', 'stress')->count());
        $this->assertTrue($existing->fresh()->is_active);
    }
}
