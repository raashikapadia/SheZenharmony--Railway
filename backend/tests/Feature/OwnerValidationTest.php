<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\ChatSession;
use App\Models\Intervention;
use App\Models\InterventionUsage;
use App\Models\ProgressEntry;
use App\Models\StressAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OwnerValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_requires_exactly_one_owner_only_when_created(): void
    {
        $user = User::factory()->create();
        $assessment = StressAssessment::query()->create(['user_id' => $user->id]);

        $assessment->update(['user_id' => null]);
        $this->assertNull($assessment->fresh()->user_id);

        $this->expectException(ValidationException::class);
        StressAssessment::query()->create();
    }

    public function test_compatibility_models_accept_the_bigint_anonymous_owner(): void
    {
        $anonymous = AnonymousSession::query()->create();
        $intervention = Intervention::query()->create([
            'title' => 'Test activity',
            'content_type' => 'activity',
        ]);

        $assessment = StressAssessment::query()->create(['anonymous_session_fk' => $anonymous->id]);
        $usage = InterventionUsage::query()->create([
            'intervention_id' => $intervention->id,
            'anonymous_session_fk' => $anonymous->id,
        ]);

        $this->assertSame($anonymous->id, $assessment->anonymous_session_fk);
        $this->assertSame($anonymous->id, $usage->anonymous_session_fk);
    }

    public function test_chat_session_rejects_two_owners(): void
    {
        $user = User::factory()->create();
        $anonymous = AnonymousSession::query()->create();

        $this->expectException(ValidationException::class);
        ChatSession::query()->create([
            'user_id' => $user->id,
            'anonymous_session_id' => $anonymous->id,
        ]);
    }

    public function test_progress_entry_requires_an_owner_when_created(): void
    {
        $this->expectException(ValidationException::class);
        ProgressEntry::query()->create([
            'metric_type' => 'stress_score',
            'recorded_at' => now(),
        ]);
    }
}
