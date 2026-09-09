<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpline resources: the support contacts an admin publishes for the student
 * Resource tab. Deliberately its own table rather than another
 * `wellbeing_activities` category — a helpline is a contact record (number,
 * hours, website), not a piece of media with a video URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpline_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('organisation')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 50);
            $table->string('alternate_phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('website_url', 2000)->nullable();
            $table->string('availability', 150)->nullable(); // "24 hours, 7 days"
            $table->string('category', 100)->nullable();     // Crisis, Counselling, Campus…
            // Emergency contacts sort to the top of the student list.
            $table->boolean('is_emergency')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_emergency', 'position'], 'helpline_resources_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpline_resources');
    }
};
