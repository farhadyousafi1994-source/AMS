<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'current_branch')) {
                $table->foreignId('current_branch')->nullable()->after('current_company');
            }
        });

        if (! Schema::hasTable('branch_user')) {
            Schema::create('branch_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('branch_role')->nullable(); // optional per-branch role label
                $table->timestamps();
                $table->unique(['branch_id', 'user_id']);
            });
        }

        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
        Schema::dropIfExists('branch_user');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'current_branch')) {
                $table->dropColumn('current_branch');
            }
        });
    }
};
