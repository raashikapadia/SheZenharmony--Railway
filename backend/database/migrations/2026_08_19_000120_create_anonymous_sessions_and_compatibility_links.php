<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->foreignId('anonymous_session_fk')->nullable()->after('anonymous_session_id')
                ->constrained('anonymous_sessions')->nullOnDelete();
        });
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->foreignId('anonymous_session_fk')->nullable()->after('anonymous_session_id')
                ->constrained('anonymous_sessions')->nullOnDelete();
        });

        $uuids = DB::table('stress_assessments')->whereNotNull('anonymous_session_id')->pluck('anonymous_session_id')
            ->merge(DB::table('intervention_usages')->whereNotNull('anonymous_session_id')->pluck('anonymous_session_id'))
            ->unique()->values();

        foreach ($uuids as $uuid) {
            DB::table('anonymous_sessions')->updateOrInsert(['public_uuid' => $uuid], [
                'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('anonymous_sessions')->orderBy('id')->eachById(function (object $session): void {
            DB::table('stress_assessments')->where('anonymous_session_id', $session->public_uuid)
                ->update(['anonymous_session_fk' => $session->id]);
            DB::table('intervention_usages')->where('anonymous_session_id', $session->public_uuid)
                ->update(['anonymous_session_fk' => $session->id]);
        });
    }

    public function down(): void
    {
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anonymous_session_fk');
        });
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anonymous_session_fk');
        });
        Schema::dropIfExists('anonymous_sessions');
    }
};
