<?php

namespace Tests\Feature;

use App\Models\Diary;
use App\Models\DiaryLock;
use App\Models\DiaryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiarySyncApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStudent(): User
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function diaryPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'diary-1',
            'title' => 'Quiet thoughts',
            'cover_index' => 3,
            'created_at' => '2026-09-01T09:00:00.000Z',
            'updated_at' => '2026-09-01T09:00:00.000Z',
            'pages' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 'page-1',
            'title' => 'First evening',
            'body' => 'Long day, but it passed.',
            'created_at' => '2026-09-01T09:00:00.000Z',
            'updated_at' => '2026-09-01T09:00:00.000Z',
        ], $overrides);
    }

    public function test_a_diary_pushed_from_a_device_comes_back_on_the_next_pull(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk();

        // A reinstalled app asks for everything before it has anything to push.
        $this->getJson('/api/v1/diary')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Quiet thoughts')
            ->assertJsonPath('data.0.cover_index', 3)
            ->assertJsonPath('data.0.pages.0.body', 'Long day, but it passed.');
    }

    public function test_the_written_content_is_encrypted_in_the_database(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk();

        // Read around Eloquent so the cast cannot do the decrypting for us.
        $storedDiary = DB::table('diaries')->first();
        $storedPage = DB::table('diary_pages')->first();

        $this->assertNotSame('Quiet thoughts', $storedDiary->title);
        $this->assertNotSame('First evening', $storedPage->title);
        $this->assertNotSame('Long day, but it passed.', $storedPage->body);
        $this->assertStringNotContainsString('Long day', $storedPage->body);

        // And it is genuinely recoverable, not merely mangled.
        $this->assertSame('Long day, but it passed.', DiaryPage::query()->first()->body);
    }

    public function test_a_student_never_sees_another_students_diary(): void
    {
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($other, ['student']);
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['title' => 'Somebody else\'s'])],
        ])->assertOk();

        $this->actingAsStudent();

        $this->getJson('/api/v1/diary')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_diary_requires_authentication(): void
    {
        $this->getJson('/api/v1/diary')->assertUnauthorized();
        $this->postJson('/api/v1/diary/sync', ['diaries' => []])->assertUnauthorized();
    }

    public function test_no_admin_route_exposes_any_diary(): void
    {
        // The guarantee is structural: if this ever fails, an admin-reachable
        // path to a student's diary has been added.
        $adminRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'admin'))
            ->filter(fn ($route): bool => str_contains(strtolower($route->uri()), 'diary'));

        $this->assertCount(0, $adminRoutes);

        $controllers = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getActionName())
            ->filter(fn (string $action): bool => str_contains($action, 'Diary'));

        // Every diary action lives on the student controller, none on an admin one.
        $this->assertTrue($controllers->every(
            fn (string $action): bool => str_contains($action, 'Api\DiaryController'),
        ));
    }

    public function test_the_newer_edit_wins_when_two_devices_disagree(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk();

        // A second device syncs an older copy of the same page.
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload([
                'pages' => [$this->pagePayload([
                    'body' => 'stale text from a device that was offline',
                    'updated_at' => '2026-08-01T09:00:00.000Z',
                ])],
            ])],
        ])->assertOk()
            ->assertJsonPath('data.0.pages.0.body', 'Long day, but it passed.');

        // Then a genuinely newer edit, which should win.
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload([
                'pages' => [$this->pagePayload([
                    'body' => 'written later, on the other phone',
                    'updated_at' => '2026-09-05T09:00:00.000Z',
                ])],
            ])],
        ])->assertOk()
            ->assertJsonPath('data.0.pages.0.body', 'written later, on the other phone');
    }

    public function test_a_page_added_on_one_device_does_not_erase_one_added_on_another(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk();

        $response = $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload([
                'pages' => [$this->pagePayload([
                    'client_id' => 'page-2',
                    'title' => 'Second evening',
                    'body' => 'written on the other phone',
                ])],
            ])],
        ])->assertOk();

        $response->assertJsonCount(2, 'data.0.pages');
    }

    public function test_a_deleted_diary_stays_deleted_after_another_device_syncs(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload([
                'deleted' => true,
                'updated_at' => '2026-09-10T09:00:00.000Z',
            ])],
        ])->assertOk()->assertJsonCount(0, 'data');

        // The device that still holds the old copy must not resurrect it.
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['pages' => [$this->pagePayload()]])],
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_locked_diary_is_still_locked_after_reinstalling(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload(['is_locked' => true])],
            'lock' => [
                'salt' => 'c2FsdA==',
                'hash' => 'aGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk();

        $this->getJson('/api/v1/diary')
            ->assertOk()
            ->assertJsonPath('data.0.is_locked', true)
            ->assertJsonPath('lock.salt', 'c2FsdA==')
            ->assertJsonPath('lock.hash', 'aGFzaA==');
    }

    public function test_a_student_with_no_pin_reports_no_lock(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', ['diaries' => [$this->diaryPayload()]])
            ->assertOk()
            ->assertJsonPath('data.0.is_locked', false)
            ->assertJsonPath('lock', null);
    }

    public function test_one_pin_covers_every_diary_the_student_locks(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [
                $this->diaryPayload(['client_id' => 'diary-1', 'is_locked' => true]),
                $this->diaryPayload(['client_id' => 'diary-2', 'is_locked' => true]),
                $this->diaryPayload(['client_id' => 'diary-3']),
            ],
            'lock' => [
                'salt' => 'c2FsdA==',
                'hash' => 'aGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk();

        // One PIN is stored, however many diaries use it.
        $this->assertSame(1, DiaryLock::query()->count());

        $response = $this->getJson('/api/v1/diary')->assertOk();
        $locked = collect($response->json('data'))
            ->filter(fn (array $diary): bool => $diary['is_locked'])
            ->pluck('client_id');

        $this->assertEqualsCanonicalizing(['diary-1', 'diary-2'], $locked->all());
    }

    public function test_changing_the_pin_replaces_the_one_the_student_had(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'b2xk',
                'hash' => 'b2xkaGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'bmV3',
                'hash' => 'bmV3aGFzaA==',
                'updated_at' => '2026-09-05T09:00:00.000Z',
            ],
        ])->assertOk()
            ->assertJsonPath('lock.hash', 'bmV3aGFzaA==');

        $this->assertSame(1, DiaryLock::query()->count());
    }

    public function test_a_device_that_has_not_caught_up_cannot_undo_a_new_pin(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'bmV3',
                'hash' => 'bmV3aGFzaA==',
                'updated_at' => '2026-09-05T09:00:00.000Z',
            ],
        ])->assertOk();

        // An older device still offering the PIN it knew about.
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'b2xk',
                'hash' => 'b2xkaGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk()
            ->assertJsonPath('lock.hash', 'bmV3aGFzaA==');
    }

    public function test_a_device_with_no_pin_does_not_erase_one_set_elsewhere(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'c2FsdA==',
                'hash' => 'aGFzaA==',
                'updated_at' => '2026-09-05T09:00:00.000Z',
            ],
        ])->assertOk();

        // A phone that has never synced sends no lock at all. Silence is not
        // an instruction to unlock everything.
        $this->postJson('/api/v1/diary/sync', ['diaries' => []])
            ->assertOk()
            ->assertJsonPath('lock.hash', 'aGFzaA==');
    }

    public function test_clearing_the_pin_removes_it_for_good(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'c2FsdA==',
                'hash' => 'aGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'cleared' => true,
                'salt' => null,
                'hash' => null,
                'updated_at' => '2026-09-05T09:00:00.000Z',
            ],
        ])->assertOk()
            ->assertJsonPath('lock', null);

        $this->assertSame(0, DiaryLock::query()->count());
    }

    public function test_one_students_pin_is_never_visible_to_another(): void
    {
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($other, ['student']);
        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [],
            'lock' => [
                'salt' => 'c2FsdA==',
                'hash' => 'aGFzaA==',
                'updated_at' => '2026-09-01T09:00:00.000Z',
            ],
        ])->assertOk();

        $this->actingAsStudent();

        $this->getJson('/api/v1/diary')
            ->assertOk()
            ->assertJsonPath('lock', null);
    }

    public function test_a_malformed_diary_is_rejected_before_anything_is_written(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [['title' => 'no client id, no dates']],
        ])->assertStatus(422);

        $this->assertSame(0, Diary::query()->count());
    }

    public function test_an_empty_sync_is_a_plain_pull(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/v1/diary/sync', [
            'diaries' => [$this->diaryPayload()],
        ])->assertOk();

        $this->postJson('/api/v1/diary/sync', ['diaries' => []])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Quiet thoughts');
    }
}
