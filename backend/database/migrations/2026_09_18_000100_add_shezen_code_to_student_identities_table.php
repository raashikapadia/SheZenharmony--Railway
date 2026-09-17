<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every student a short, readable SheZen ID (e.g. SZ7K42P) in place of
 * the 35-character hex string derived from their UUID. The UUID stays as the
 * internal key; this is the identifier people see and quote.
 */
return new class extends Migration
{
    // Mirrors StudentIdentity::CODE_* — inlined so this migration keeps
    // producing the same codes even if the model's rules move on later.
    private const PREFIX = 'SZ';

    private const LENGTH = 5;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        Schema::table('student_identities', function (Blueprint $table): void {
            $table->string('shezen_code', 12)->nullable()->unique()->after('pseudonymous_uuid');
        });

        $taken = DB::table('student_identities')->whereNotNull('shezen_code')->pluck('shezen_code')->flip()->all();
        DB::table('student_identities')->whereNull('shezen_code')->orderBy('id')->eachById(
            function (object $identity) use (&$taken): void {
                do {
                    $code = self::PREFIX;
                    for ($i = 0; $i < self::LENGTH; $i++) {
                        $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                    }
                } while (isset($taken[$code]));
                $taken[$code] = true;

                DB::table('student_identities')->where('id', $identity->id)->update(['shezen_code' => $code]);
            }
        );

        Schema::table('student_identities', function (Blueprint $table): void {
            $table->string('shezen_code', 12)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_identities', function (Blueprint $table): void {
            $table->dropUnique(['shezen_code']);
            $table->dropColumn('shezen_code');
        });
    }
};
