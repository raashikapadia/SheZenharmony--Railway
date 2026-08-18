<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->text('instructions')->nullable()->after('external_url');
            $table->foreignId('created_by_user_id')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
            $table->index(['is_active', 'content_type']);
        });

        DB::table('interventions')->orderBy('id')->eachById(
            fn (object $intervention) => DB::table('interventions')->where('id', $intervention->id)->update([
                'slug' => Str::slug($intervention->title).'-'.$intervention->id,
            ])
        );

        Schema::create('intervention_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stress_score_band_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['stress_score_band_id', 'intervention_id']);
            $table->index(['stress_score_band_id', 'is_active', 'priority']);
        });

        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('intervention_id')->constrained()->nullOnDelete();
            $table->string('usage_status', 20)->default('started')->after('anonymous_session_fk');
            $table->unsignedTinyInteger('mood_before')->nullable()->after('usage_status');
            $table->unsignedTinyInteger('mood_after')->nullable()->after('mood_before');
            $table->unsignedInteger('duration_seconds')->nullable()->after('completed_at');
            $table->index(['user_id', 'started_at']);
            $table->index(['anonymous_session_fk', 'started_at']);
            $table->index(['intervention_id', 'started_at']);
            $table->index('usage_status');
        });

        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('intervention_content_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['intervention_id', 'content_category_id']);
            $table->index('content_category_id');
        });
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });
        Schema::create('intervention_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['intervention_id', 'tag_id']);
            $table->index('tag_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE intervention_usages ADD CONSTRAINT chk_usage_at_most_one_owner CHECK (user_id IS NULL OR anonymous_session_fk IS NULL)');
            DB::statement('ALTER TABLE intervention_usages ADD CONSTRAINT chk_mood_before CHECK (mood_before IS NULL OR mood_before BETWEEN 1 AND 5)');
            DB::statement('ALTER TABLE intervention_usages ADD CONSTRAINT chk_mood_after CHECK (mood_after IS NULL OR mood_after BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('intervention_content_categories');
        Schema::dropIfExists('content_categories');
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE intervention_usages DROP CHECK chk_mood_after');
            DB::statement('ALTER TABLE intervention_usages DROP CHECK chk_mood_before');
            DB::statement('ALTER TABLE intervention_usages DROP CHECK chk_usage_at_most_one_owner');
        }
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->dropIndex(['intervention_id', 'started_at']);
            $table->dropIndex(['anonymous_session_fk', 'started_at']);
            $table->dropIndex(['user_id', 'started_at']);
            $table->dropIndex(['usage_status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['usage_status', 'mood_before', 'mood_after', 'duration_seconds']);
        });
        Schema::dropIfExists('intervention_recommendations');
        Schema::table('interventions', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'content_type']);
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'instructions']);
        });
    }
};
