<?php

namespace Database\Seeders;

use App\Models\HelplineResource;
use Illuminate\Database\Seeder;

/**
 * A small inactive starter set for administrators to review after `migrate` +
 * `db:seed`. Guarded: does nothing once any helpline exists, so it never fights
 * admin-managed content.
 *
 * !! DEVELOPMENT PLACEHOLDER DATA !!
 *
 * The phone numbers below have NOT been verified. They exist so the feature
 * can be reviewed end to end, not so a student can dial them. They remain
 * inactive until an administrator verifies and deliberately publishes them.
 */
class HelplineResourceSeeder extends Seeder
{
    public function run(): void
    {
        if (HelplineResource::query()->exists()) {
            return;
        }

        $resources = [
            [
                'name' => 'USP Student Counselling',
                'organisation' => 'The University of the South Pacific',
                'description' => 'Talk with a campus counsellor about stress, study pressure, or anything on your mind.',
                'phone' => '+679 323 1000',          // UNVERIFIED placeholder
                'email' => 'counselling@usp.ac.fj',  // UNVERIFIED placeholder
                'website_url' => 'https://www.usp.ac.fj',
                'availability' => 'Mon-Fri, 8am-4pm',
                'category' => 'Counselling',
                'is_emergency' => false,
                'position' => 0,
            ],
            [
                'name' => 'National Crisis Helpline',
                'organisation' => 'Ministry of Health',
                'description' => 'Free, confidential support at any hour, for any level of distress.',
                'phone' => '1325',                   // UNVERIFIED placeholder
                'availability' => '24 hours, 7 days',
                'category' => 'Crisis',
                'is_emergency' => true,
                'position' => 1,
            ],
        ];

        foreach ($resources as $resource) {
            HelplineResource::query()->create($resource + ['is_active' => false]);
        }
    }
}
