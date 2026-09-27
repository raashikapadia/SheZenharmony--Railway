<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These exact contacts shipped without verification or durable source
        // metadata. Fail closed once during deployment; an administrator can
        // verify and deliberately reactivate a contact afterwards.
        foreach ([
            ['name' => 'USP Student Counselling', 'phone' => '+679 323 1000'],
            ['name' => 'National Crisis Helpline', 'phone' => '1325'],
        ] as $placeholder) {
            DB::table('helpline_resources')
                ->where($placeholder)
                ->whereNull('created_by_user_id')
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Never republish unverified support contacts during a rollback.
    }
};
