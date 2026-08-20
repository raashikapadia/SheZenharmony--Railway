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
        $this->assertExistingSchemaIsCompatible();
        $this->extendInterventions();
        $this->ensureRecommendations();
        $this->extendUsages();
        $this->ensureTaxonomy();
        $this->ensureMoodChecks();
    }

    public function down(): void
    {
        foreach (['intervention_tags', 'tags'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
        if (Schema::hasTable('intervention_content_categories')) {
            if (Schema::hasIndex('intervention_content_categories', 'uq_intervention_content_category')) {
                Schema::table('intervention_content_categories', fn (Blueprint $table) => $table->dropUnique('uq_intervention_content_category'));
            }
            Schema::drop('intervention_content_categories');
        }
        if (Schema::hasTable('content_categories')) {
            Schema::drop('content_categories');
        }

        if (Schema::hasTable('intervention_usages')) {
            foreach (['chk_mood_after', 'chk_mood_before'] as $check) {
                if ($this->hasCheckConstraint('intervention_usages', $check)) {
                    DB::statement("ALTER TABLE intervention_usages DROP CHECK {$check}");
                }
            }
            foreach ([
                'intervention_usages_intervention_id_started_at_index',
                'intervention_usages_anonymous_session_fk_started_at_index',
                'intervention_usages_user_id_started_at_index',
                'intervention_usages_usage_status_index',
            ] as $index) {
                if (Schema::hasIndex('intervention_usages', $index)) {
                    Schema::table('intervention_usages', fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
            if (Schema::hasForeignKey('intervention_usages', 'intervention_usages_user_id_foreign')) {
                Schema::table('intervention_usages', fn (Blueprint $table) => $table->dropForeign('intervention_usages_user_id_foreign'));
            }
            foreach (['user_id', 'usage_status', 'mood_before', 'mood_after', 'duration_seconds'] as $column) {
                if (Schema::hasColumn('intervention_usages', $column)) {
                    Schema::table('intervention_usages', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('intervention_recommendations')) {
            foreach (['idx_intervention_rec_band_active_priority', 'uq_intervention_rec_band_intervention'] as $index) {
                if (Schema::hasIndex('intervention_recommendations', $index)) {
                    Schema::table('intervention_recommendations', fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
            Schema::drop('intervention_recommendations');
        }

        if (Schema::hasTable('interventions')) {
            if (Schema::hasIndex('interventions', 'interventions_is_active_content_type_index')) {
                Schema::table('interventions', fn (Blueprint $table) => $table->dropIndex('interventions_is_active_content_type_index'));
            }
            if (Schema::hasForeignKey('interventions', 'interventions_created_by_user_id_foreign')) {
                Schema::table('interventions', fn (Blueprint $table) => $table->dropForeign('interventions_created_by_user_id_foreign'));
            }
            if (Schema::hasIndex('interventions', 'interventions_slug_unique')) {
                Schema::table('interventions', fn (Blueprint $table) => $table->dropUnique('interventions_slug_unique'));
            }
            foreach (['slug', 'instructions', 'created_by_user_id'] as $column) {
                if (Schema::hasColumn('interventions', $column)) {
                    Schema::table('interventions', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }

    private function extendInterventions(): void
    {
        if (! Schema::hasColumn('interventions', 'slug')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->string('slug')->nullable()->after('title'));
        }
        if (! Schema::hasIndex('interventions', 'interventions_slug_unique')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->unique('slug'));
        }
        if (! Schema::hasColumn('interventions', 'instructions')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->text('instructions')->nullable()->after('external_url'));
        }
        if (! Schema::hasColumn('interventions', 'created_by_user_id')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->foreignId('created_by_user_id')->nullable()->after('is_active'));
        }
        if (! Schema::hasForeignKey('interventions', 'interventions_created_by_user_id_foreign')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete());
        }
        if (! Schema::hasIndex('interventions', 'interventions_is_active_content_type_index')) {
            Schema::table('interventions', fn (Blueprint $table) => $table->index(['is_active', 'content_type']));
        }

        DB::table('interventions')->whereNull('slug')->orderBy('id')->eachById(
            fn (object $row) => DB::table('interventions')->where('id', $row->id)->update([
                'slug' => Str::slug($row->title).'-'.$row->id,
            ])
        );
    }

    private function ensureRecommendations(): void
    {
        if (! Schema::hasTable('intervention_recommendations')) {
            Schema::create('intervention_recommendations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('stress_score_band_id');
                $table->foreignId('intervention_id');
                $table->unsignedSmallInteger('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasForeignKey('intervention_recommendations', 'intervention_recommendations_stress_score_band_id_foreign')) {
            Schema::table('intervention_recommendations', fn (Blueprint $table) => $table->foreign('stress_score_band_id')->references('id')->on('stress_score_bands')->cascadeOnDelete());
        }
        if (! Schema::hasForeignKey('intervention_recommendations', 'intervention_recommendations_intervention_id_foreign')) {
            Schema::table('intervention_recommendations', fn (Blueprint $table) => $table->foreign('intervention_id')->references('id')->on('interventions')->cascadeOnDelete());
        }
        if (! Schema::hasIndex('intervention_recommendations', 'uq_intervention_rec_band_intervention')) {
            Schema::table('intervention_recommendations', fn (Blueprint $table) => $table->unique(['stress_score_band_id', 'intervention_id'], 'uq_intervention_rec_band_intervention'));
        }
        if (! Schema::hasIndex('intervention_recommendations', 'idx_intervention_rec_band_active_priority')) {
            Schema::table('intervention_recommendations', fn (Blueprint $table) => $table->index(['stress_score_band_id', 'is_active', 'priority'], 'idx_intervention_rec_band_active_priority'));
        }
    }

    private function extendUsages(): void
    {
        if (! Schema::hasColumn('intervention_usages', 'user_id')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->foreignId('user_id')->nullable()->after('intervention_id'));
        }
        if (! Schema::hasForeignKey('intervention_usages', 'intervention_usages_user_id_foreign')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->foreign('user_id')->references('id')->on('users')->nullOnDelete());
        }
        if (! Schema::hasColumn('intervention_usages', 'usage_status')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->string('usage_status', 20)->default('started')->after('anonymous_session_fk'));
        }
        if (! Schema::hasColumn('intervention_usages', 'mood_before')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->unsignedTinyInteger('mood_before')->nullable()->after('usage_status'));
        }
        if (! Schema::hasColumn('intervention_usages', 'mood_after')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->unsignedTinyInteger('mood_after')->nullable()->after('mood_before'));
        }
        if (! Schema::hasColumn('intervention_usages', 'duration_seconds')) {
            Schema::table('intervention_usages', fn (Blueprint $table) => $table->unsignedInteger('duration_seconds')->nullable()->after('completed_at'));
        }
        foreach ([
            'intervention_usages_user_id_started_at_index' => ['user_id', 'started_at'],
            'intervention_usages_anonymous_session_fk_started_at_index' => ['anonymous_session_fk', 'started_at'],
            'intervention_usages_intervention_id_started_at_index' => ['intervention_id', 'started_at'],
            'intervention_usages_usage_status_index' => ['usage_status'],
        ] as $name => $columns) {
            if (! Schema::hasIndex('intervention_usages', $name)) {
                Schema::table('intervention_usages', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    private function ensureTaxonomy(): void
    {
        if (! Schema::hasTable('content_categories')) {
            Schema::create('content_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('intervention_content_categories')) {
            Schema::create('intervention_content_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('intervention_id');
                $table->foreignId('content_category_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex('intervention_content_categories', 'uq_intervention_content_category')) {
            Schema::table('intervention_content_categories', fn (Blueprint $table) => $table->unique(['intervention_id', 'content_category_id'], 'uq_intervention_content_category'));
        }
        if (! Schema::hasIndex('intervention_content_categories', ['content_category_id'])) {
            Schema::table('intervention_content_categories', fn (Blueprint $table) => $table->index('content_category_id', 'idx_intervention_content_category'));
        }
        if (! Schema::hasForeignKey('intervention_content_categories', 'intervention_content_categories_intervention_id_foreign')) {
            Schema::table('intervention_content_categories', fn (Blueprint $table) => $table->foreign('intervention_id')->references('id')->on('interventions')->cascadeOnDelete());
        }
        if (! Schema::hasForeignKey('intervention_content_categories', 'intervention_content_categories_content_category_id_foreign')) {
            Schema::table('intervention_content_categories', fn (Blueprint $table) => $table->foreign('content_category_id')->references('id')->on('content_categories')->cascadeOnDelete());
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('intervention_tags')) {
            Schema::create('intervention_tags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('intervention_id');
                $table->foreignId('tag_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex('intervention_tags', 'intervention_tags_intervention_id_tag_id_unique')) {
            Schema::table('intervention_tags', fn (Blueprint $table) => $table->unique(['intervention_id', 'tag_id']));
        }
        if (! Schema::hasIndex('intervention_tags', ['tag_id'])) {
            Schema::table('intervention_tags', fn (Blueprint $table) => $table->index('tag_id'));
        }
        if (! Schema::hasForeignKey('intervention_tags', 'intervention_tags_intervention_id_foreign')) {
            Schema::table('intervention_tags', fn (Blueprint $table) => $table->foreign('intervention_id')->references('id')->on('interventions')->cascadeOnDelete());
        }
        if (! Schema::hasForeignKey('intervention_tags', 'intervention_tags_tag_id_foreign')) {
            Schema::table('intervention_tags', fn (Blueprint $table) => $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete());
        }
    }

    private function ensureMoodChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! $this->hasCheckConstraint('intervention_usages', 'chk_mood_before')) {
            DB::statement('ALTER TABLE intervention_usages ADD CONSTRAINT chk_mood_before CHECK (mood_before IS NULL OR mood_before BETWEEN 1 AND 5)');
        }
        if (! $this->hasCheckConstraint('intervention_usages', 'chk_mood_after')) {
            DB::statement('ALTER TABLE intervention_usages ADD CONSTRAINT chk_mood_after CHECK (mood_after IS NULL OR mood_after BETWEEN 1 AND 5)');
        }
    }

    private function hasCheckConstraint(string $table, string $constraint): bool
    {
        return DB::getDriverName() === 'mysql' && DB::table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'CHECK')
            ->exists();
    }

    /**
     * MySQL DDL is not transactional. A failed run can therefore leave objects behind
     * without a migrations-table entry. Validate such objects before resuming so the
     * conditional create logic never treats an incompatible definition as complete.
     */
    private function assertExistingSchemaIsCompatible(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->assertColumns('interventions', [
            'slug' => ['varchar', true],
            'instructions' => ['text', true],
            'created_by_user_id' => ['bigint', true, 'unsigned'],
        ], allowMissing: true);
        $this->assertColumns('intervention_recommendations', [
            'id' => ['bigint', false, 'unsigned'],
            'stress_score_band_id' => ['bigint', false, 'unsigned'],
            'intervention_id' => ['bigint', false, 'unsigned'],
            'priority' => ['smallint', false, 'unsigned'],
            'is_active' => ['tinyint', false],
            'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);
        $this->assertColumns('intervention_usages', [
            'user_id' => ['bigint', true, 'unsigned'],
            'usage_status' => ['varchar', false],
            'mood_before' => ['tinyint', true, 'unsigned'],
            'mood_after' => ['tinyint', true, 'unsigned'],
            'duration_seconds' => ['int', true, 'unsigned'],
        ], allowMissing: true);
        $this->assertColumns('content_categories', [
            'id' => ['bigint', false, 'unsigned'], 'name' => ['varchar', false],
            'slug' => ['varchar', false], 'description' => ['text', true],
            'is_active' => ['tinyint', false], 'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);
        $this->assertColumns('intervention_content_categories', [
            'id' => ['bigint', false, 'unsigned'], 'intervention_id' => ['bigint', false, 'unsigned'],
            'content_category_id' => ['bigint', false, 'unsigned'], 'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);
        $this->assertColumns('tags', [
            'id' => ['bigint', false, 'unsigned'], 'name' => ['varchar', false],
            'slug' => ['varchar', false], 'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);
        $this->assertColumns('intervention_tags', [
            'id' => ['bigint', false, 'unsigned'], 'intervention_id' => ['bigint', false, 'unsigned'],
            'tag_id' => ['bigint', false, 'unsigned'], 'created_at' => ['timestamp', true],
            'updated_at' => ['timestamp', true],
        ]);

        foreach ([
            ['interventions', 'interventions_slug_unique', ['slug'], true],
            ['interventions', 'interventions_is_active_content_type_index', ['is_active', 'content_type'], false],
            ['intervention_recommendations', 'uq_intervention_rec_band_intervention', ['stress_score_band_id', 'intervention_id'], true],
            ['intervention_recommendations', 'idx_intervention_rec_band_active_priority', ['stress_score_band_id', 'is_active', 'priority'], false],
        ] as [$table, $name, $columns, $unique]) {
            $this->assertNamedIndexIfPresent($table, $name, $columns, $unique);
        }

        foreach ([
            ['interventions', 'interventions_created_by_user_id_foreign', 'created_by_user_id', 'users', 'id', 'SET NULL'],
            ['intervention_recommendations', 'intervention_recommendations_stress_score_band_id_foreign', 'stress_score_band_id', 'stress_score_bands', 'id', 'CASCADE'],
            ['intervention_recommendations', 'intervention_recommendations_intervention_id_foreign', 'intervention_id', 'interventions', 'id', 'CASCADE'],
            ['intervention_usages', 'intervention_usages_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL'],
        ] as $foreignKey) {
            $this->assertForeignKeyIfPresent(...$foreignKey);
        }
    }

    /** @param array<string, array{0: string, 1: bool, 2?: string}> $expected */
    private function assertColumns(string $table, array $expected, bool $allowMissing = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $actual = DB::table('information_schema.columns')
            ->whereRaw('table_schema = DATABASE()')->where('table_name', $table)
            ->get()->keyBy('COLUMN_NAME');

        foreach ($expected as $column => $expectedDefinition) {
            [$type, $nullable] = $expectedDefinition;
            $attribute = $expectedDefinition[2] ?? null;
            if (! $actual->has($column)) {
                if ($allowMissing) {
                    // Extension columns are intentionally added later in this migration.
                    continue;
                }
                throw new RuntimeException("Existing {$table} is missing required column {$column} for migration 2026_08_19_000160.");
            }
            $definition = $actual->get($column);
            $matches = strtolower($definition->DATA_TYPE) === $type
                && ($definition->IS_NULLABLE === 'YES') === $nullable
                && ($attribute !== 'unsigned' || str_contains(strtolower($definition->COLUMN_TYPE), 'unsigned'));

            if (! $matches) {
                throw new RuntimeException("Existing {$table}.{$column} is incompatible with migration 2026_08_19_000160.");
            }
        }
    }

    /** @param list<string> $columns */
    private function assertNamedIndexIfPresent(string $table, string $name, array $columns, bool $unique): void
    {
        $rows = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')->where('table_name', $table)->where('index_name', $name)
            ->orderBy('seq_in_index')->get();
        if ($rows->isEmpty()) {
            return;
        }

        if ($rows->pluck('COLUMN_NAME')->all() !== $columns || ((int) $rows->first()->NON_UNIQUE === 0) !== $unique) {
            throw new RuntimeException("Existing index {$name} is incompatible with migration 2026_08_19_000160.");
        }
    }

    private function assertForeignKeyIfPresent(
        string $table,
        string $name,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $deleteRule,
    ): void {
        $actual = DB::table('information_schema.key_column_usage as k')
            ->join('information_schema.referential_constraints as r', function ($join): void {
                $join->on('r.constraint_schema', '=', 'k.constraint_schema')
                    ->on('r.constraint_name', '=', 'k.constraint_name');
            })
            ->whereRaw('k.table_schema = DATABASE()')->where('k.table_name', $table)
            ->where('k.constraint_name', $name)
            ->first([
                'k.column_name as local_column',
                'k.referenced_table_name as foreign_table',
                'k.referenced_column_name as foreign_column',
                'r.delete_rule as on_delete',
            ]);
        if ($actual === null) {
            return;
        }

        if ($actual->local_column !== $column || $actual->foreign_table !== $referencedTable
            || $actual->foreign_column !== $referencedColumn || strtoupper($actual->on_delete) !== $deleteRule) {
            throw new RuntimeException("Existing foreign key {$name} is incompatible with migration 2026_08_19_000160.");
        }
    }
};
