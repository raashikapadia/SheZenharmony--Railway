<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_view_profile_and_logout(): void
    {
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ]);

        $token = $login->assertOk()
            ->assertJsonPath('user.role', User::ROLE_STUDENT)
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_use_the_student_mobile_login(): void
    {
        $admin = User::factory()->create([
            'password' => 'admin-password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'admin-password',
            'device_name' => 'test device',
        ])->assertUnprocessable();
    }
}
