<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_games_that_ship_with_the_app_are_published_rows_admins_can_see(): void
    {
        $catalogue = Intervention::query()->where('content_type', 'game')->pluck('title', 'slug');

        $this->assertSame([
            'breathing-challenge' => 'Breathing Challenge',
            'gratitude-jar' => 'Gratitude Jar',
            'memory-spark' => 'Memory Spark',
            'mindful-memory' => 'Mindful Memory',
        ], $catalogue->sortKeys()->all());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.positive-engagement.games.index'))
            ->assertOk()
            ->assertSee('Breathing Challenge')
            ->assertSee('Mindful Memory')
            ->assertDontSee('No games configured.');
    }

    public function test_archiving_a_game_removes_it_from_the_student_app_and_publishing_restores_it(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $game = Intervention::query()->where('slug', 'gratitude-jar')->sole();

        $this->getJson('/api/v1/interventions?content_type=game')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'gratitude-jar']);

        // A published game offers Archive; an archived one offers Publish back.
        $this->actingAs($admin)->get(route('admin.positive-engagement.games.index'))
            ->assertOk()
            ->assertSee('>Archive</button>', false)
            ->assertSee('Published');

        $this->actingAs($admin)
            ->patch(route('admin.positive-engagement.games.visibility', $game), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', ['id' => $game->id, 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.positive-engagement.games.index'))
            ->assertOk()
            ->assertSee('>Publish</button>', false)
            ->assertSee('Archived');

        $this->getJson('/api/v1/interventions?content_type=game')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'gratitude-jar']);

        $this->actingAs($admin)
            ->patch(route('admin.positive-engagement.games.visibility', $game), ['is_active' => '1'])
            ->assertRedirect();

        $this->getJson('/api/v1/interventions?content_type=game')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'gratitude-jar']);
    }

    public function test_a_play_is_counted_once_per_student_and_shown_to_the_admin(): void
    {
        $game = Intervention::query()->where('slug', 'memory-spark')->sole();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        Sanctum::actingAs($student, ['student']);
        $this->postJson("/api/v1/positive-engagement/games/{$game->id}/play")->assertCreated();
        $this->postJson("/api/v1/positive-engagement/games/{$game->id}/play")->assertCreated();

        $this->assertDatabaseCount('intervention_usages', 2);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get(route('admin.positive-engagement.games.index'))->assertOk();

        // One student, two plays: the distinct-student count must not double.
        $response->assertSee('Students Played');
        $this->assertSame(
            1,
            $response->viewData('stats')['totalStudentsPlayed'],
            'A student who plays twice should still count once.',
        );
        $this->assertSame(2, $response->viewData('stats')['totalPlays']);

        $listed = $response->viewData('interventions')->firstWhere('slug', 'memory-spark');
        $this->assertSame(1, $listed->students_played_count);
        $this->assertSame(2, $listed->plays_count);
    }

    public function test_an_archived_game_cannot_be_played(): void
    {
        $game = Intervention::query()->where('slug', 'mindful-memory')->sole();
        $game->update(['is_active' => false]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]), ['student']);

        $this->postJson("/api/v1/positive-engagement/games/{$game->id}/play")->assertNotFound();
        $this->assertDatabaseCount('intervention_usages', 0);
    }

    public function test_a_game_that_ships_with_the_app_cannot_be_deleted_or_added_to(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $game = Intervention::query()->where('slug', 'breathing-challenge')->sole();

        $this->actingAs($admin)
            ->delete(route('admin.positive-engagement.games.destroy', $game))
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', ['id' => $game->id, 'content_type' => 'game']);

        // A new row would have no screen in the app to open.
        $this->assertFalse(app('router')->has('admin.positive-engagement.games.create'));
        $this->assertFalse(app('router')->has('admin.positive-engagement.games.store'));
    }

    public function test_the_edit_form_submits_a_content_type_the_games_section_accepts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $game = Intervention::query()->where('slug', 'gratitude-jar')->sole();

        // The form carries the type in a hidden field. If it disagrees with
        // what the section manages, saving fails validation on every edit.
        $this->actingAs($admin)->get(route('admin.positive-engagement.games.edit', $game))
            ->assertOk()
            ->assertSee('name="content_type" value="game"', false);

        $this->actingAs($admin)->put(route('admin.positive-engagement.games.update', $game), [
            'title' => 'Gratitude Jar',
            'description' => 'Edited through the form.',
            'content_type' => 'game',
            'instructions' => '',
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'id' => $game->id,
            'description' => 'Edited through the form.',
        ]);
    }

    public function test_renaming_a_game_keeps_the_slug_the_app_dispatches_on(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $game = Intervention::query()->where('slug', 'memory-spark')->sole();

        $this->actingAs($admin)->put(route('admin.positive-engagement.games.update', $game), [
            'title' => 'Spark Moments',
            'description' => 'A renamed game.',
            'content_type' => 'game',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'id' => $game->id,
            'title' => 'Spark Moments',
            'slug' => 'memory-spark',
        ]);

        $this->getJson('/api/v1/interventions?content_type=game')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'memory-spark', 'title' => 'Spark Moments']);
    }

    public function test_game_admin_routes_remain_protected(): void
    {
        $game = Intervention::query()->where('slug', 'gratitude-jar')->sole();

        $this->get(route('admin.positive-engagement.games.index'))
            ->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)
            ->get(route('admin.positive-engagement.games.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->patch(route('admin.positive-engagement.games.visibility', $game), ['is_active' => '0'])
            ->assertForbidden();

        $this->assertDatabaseHas('interventions', ['id' => $game->id, 'is_active' => true]);
    }
}
