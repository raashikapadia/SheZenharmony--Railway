<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SheZenSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_schema_tables_and_compatibility_columns_exist(): void
    {
        foreach ([
            'user_profiles', 'roles', 'user_roles', 'user_mfa_methods', 'email_otp_challenges', 'anonymous_sessions',
            'stress_score_bands', 'intervention_recommendations', 'content_categories',
            'intervention_content_categories', 'tags', 'intervention_tags', 'chat_sessions',
            'chat_messages', 'crisis_reports', 'progress_entries',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
        }

        $this->assertTrue(Schema::hasColumns('users', ['role', 'pseudonymous_uuid', 'account_status', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('question_options', ['label', 'value', 'score', 'position', 'is_active']));
        $this->assertTrue(Schema::hasColumns('stress_assessments', ['anonymous_session_id', 'anonymous_session_fk', 'stress_level']));
        $this->assertTrue(Schema::hasColumns('interventions', ['stress_level', 'external_url', 'slug']));
    }

    public function test_normalized_roles_and_legacy_role_remain_compatible(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->assertTrue($user->isStudent());

        $adminRole = Role::query()->where('slug', User::ROLE_ADMIN)->firstOrFail();
        $user->roles()->attach($adminRole);

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertSame(User::ROLE_STUDENT, $user->fresh()->role);
    }
}
