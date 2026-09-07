<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountHoldNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminStudentAccountHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_place_student_on_hold_with_a_required_reason(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;

        $this->actingAs($admin)
            ->patch(route('admin.students.hold', $identity), [
                'reason' => 'Repeated violation of the community rules.',
            ])
            ->assertRedirect(route('admin.students.show', $identity));

        $student->refresh();
        $this->assertSame('suspended', $student->account_status);
        $this->assertSame('Repeated violation of the community rules.', $student->account_hold_reason);
        $this->assertNotNull($student->account_held_at);
        $this->assertSame($admin->id, $student->account_held_by_user_id);
        Notification::assertSentTo($student, AccountHoldNotification::class);
    }

    public function test_hold_reason_is_validated_without_changing_the_account(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($admin)
            ->from(route('admin.students.show', $student->studentIdentity))
            ->patch(route('admin.students.hold', $student->studentIdentity), ['reason' => 'Too short'])
            ->assertRedirect(route('admin.students.show', $student->studentIdentity))
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $student->fresh()->account_status);
        Notification::assertNothingSent();
    }

    public function test_held_student_sees_reason_and_existing_sessions_are_blocked(): void
    {
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'account_status' => 'suspended',
            'account_hold_reason' => 'Repeated violation of the community rules.',
        ]);
        $token = $student->createToken('existing app session', ['student'])->plainTextToken;

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'test device',
        ])->assertStatus(423)
            ->assertJsonPath('code', 'account_on_hold')
            ->assertJsonPath(
                'message',
                'Your SheZen Harmony account is currently on hold. Reason: Repeated violation of the community rules. Please contact SheZen Harmony support if you believe this is a mistake.'
            );

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(423)
            ->assertJsonPath('code', 'account_on_hold')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Repeated violation'));

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_admin_can_reactivate_a_held_student(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'account_status' => 'suspended',
            'account_hold_reason' => 'Repeated violation of the community rules.',
            'account_held_at' => now(),
            'account_held_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.students.reactivate', $student->studentIdentity))
            ->assertRedirect(route('admin.students.show', $student->studentIdentity));

        $student->refresh();
        $this->assertSame('active', $student->account_status);
        $this->assertNull($student->account_hold_reason);
        $this->assertNull($student->account_held_at);
        $this->assertNull($student->account_held_by_user_id);
    }
}
