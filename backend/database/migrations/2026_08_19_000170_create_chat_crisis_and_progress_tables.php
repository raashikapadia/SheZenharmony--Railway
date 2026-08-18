<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('anonymous_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_support_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('chat_mode', 30)->default('chatbot');
            $table->string('chat_status', 20)->default('open');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
            $table->index(['anonymous_session_id', 'started_at']);
            $table->index(['assigned_support_user_id', 'chat_status']);
            $table->index(['chat_status', 'started_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 30);
            $table->longText('message_text');
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();
            $table->index(['chat_session_id', 'created_at']);
            $table->index(['is_flagged', 'created_at']);
        });

        Schema::create('crisis_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_message_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_status', 20)->default('open');
            $table->string('severity', 20)->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['report_status', 'reported_at']);
            $table->index(['handled_by_user_id', 'report_status']);
        });

        Schema::create('progress_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('anonymous_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stress_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('intervention_usage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_type', 50);
            $table->decimal('metric_value', 10, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['user_id', 'recorded_at']);
            $table->index(['anonymous_session_id', 'recorded_at']);
            $table->index(['metric_type', 'recorded_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE chat_sessions ADD CONSTRAINT chk_chat_at_most_one_owner CHECK (user_id IS NULL OR anonymous_session_id IS NULL)');
            DB::statement('ALTER TABLE progress_entries ADD CONSTRAINT chk_progress_at_most_one_owner CHECK (user_id IS NULL OR anonymous_session_id IS NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_entries');
        Schema::dropIfExists('crisis_reports');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
