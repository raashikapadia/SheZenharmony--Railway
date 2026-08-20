<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Student', 'slug' => 'student'],
            ['name' => 'Administrator', 'slug' => 'admin'],
            ['name' => 'Moderator', 'slug' => 'moderator'],
            ['name' => 'Counsellor', 'slug' => 'counsellor'],
        ] as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role + ['is_active' => true]);
        }
    }
}
