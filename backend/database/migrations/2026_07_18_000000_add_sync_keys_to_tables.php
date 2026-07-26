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
        $key = config('sync.key_column', 'uuid');
        $rev = config('sync.revision_column', 'revision');

        foreach (array_keys(config('sync.tables', [])) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $key, $rev) {
                if (! Schema::hasColumn($table, $key)) {
                    $t->uuid($key)->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, $rev)) {
                    $t->unsignedBigInteger($rev)->default(1)->after($key);
                }
            });

            DB::table($table)->whereNull($key)->orderBy('id')->chunkById(500, function ($rows) use ($table, $key) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([$key => (string) Str::uuid()]);
                }
            });

            Schema::table($table, function (Blueprint $t) use ($table, $key) {
                $t->unique($key, "{$table}_{$key}_unique");
            });
        }
    }

    public function down(): void
    {
        $key = config('sync.key_column', 'uuid');
        $rev = config('sync.revision_column', 'revision');

        foreach (array_keys(config('sync.tables', [])) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $key, $rev) {
                $t->dropUnique("{$table}_{$key}_unique");
                if (Schema::hasColumn($table, $rev)) {
                    $t->dropColumn($rev);
                }
                if (Schema::hasColumn($table, $key)) {
                    $t->dropColumn($key);
                }
            });
        }
    }
};
