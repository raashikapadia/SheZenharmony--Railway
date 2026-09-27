<?php

namespace Tests\Feature;

use App\Models\HelplineResource;
use Database\Seeders\HelplineResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HelplineResourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_inactive_placeholder_helplines_for_admin_review(): void
    {
        $this->seed(HelplineResourceSeeder::class);

        $resources = HelplineResource::query()->inDisplayOrder()->get();

        $this->assertSame(
            ['USP Student Counselling', 'National Crisis Helpline'],
            $resources->pluck('name')->all(),
        );
        $this->assertTrue($resources->every(fn (HelplineResource $resource): bool => ! $resource->is_active));
    }

    public function test_seeded_placeholder_helplines_do_not_reach_the_student_app(): void
    {
        $this->seed(HelplineResourceSeeder::class);

        $this->getJson('/api/v1/helplines')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_seeder_never_rewrites_an_existing_contact(): void
    {
        $this->seed(HelplineResourceSeeder::class);

        $reviewedContact = HelplineResource::query()
            ->where('name', 'National Crisis Helpline')
            ->where('phone', '1325')
            ->firstOrFail();
        DB::table('helpline_resources')->where('id', $reviewedContact->id)->update([
            'is_active' => true,
            'created_at' => '2026-09-24 00:00:00',
            'updated_at' => '2026-09-24 00:00:00',
        ]);

        $this->seed(HelplineResourceSeeder::class);

        $this->assertTrue($reviewedContact->fresh()->is_active);
    }

    public function test_migration_remediates_previously_seeded_active_placeholders(): void
    {
        $this->seed(HelplineResourceSeeder::class);
        DB::table('helpline_resources')->update(['is_active' => true]);
        $adminContact = HelplineResource::query()->create([
            'name' => 'National Crisis Helpline',
            'phone' => '+679 555 0100',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_26_000100_deactivate_unverified_helpline_placeholders.php');
        $migration->up();

        $this->assertSame(0, HelplineResource::query()
            ->whereIn('phone', ['+679 323 1000', '1325'])
            ->where('is_active', true)
            ->count());
        $this->assertTrue($adminContact->fresh()->is_active);
    }

    public function test_seeder_is_a_noop_when_an_admin_already_published_contacts(): void
    {
        HelplineResource::query()->create([
            'name' => 'Admin owns this now',
            'phone' => '000',
            'is_active' => false,
        ]);

        $this->seed(HelplineResourceSeeder::class);

        $this->assertDatabaseCount('helpline_resources', 1);
    }
}
