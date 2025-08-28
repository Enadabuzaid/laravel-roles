<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'label')) {
                $table->json('label')->nullable()->after('name');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->json('description')->nullable()->after('label');
            }
            if (!Schema::hasColumn('roles', 'deleted_at')) {
                $table->softDeletes();
            }

            // Optional tenant scoping on roles (same-table mode)
            if (config('roles.tenancy.mode') === 'team_scoped') {
                $fk = config('permission.team_foreign_key', 'team_id');
                if (!Schema::hasColumn('roles', $fk)) {
                    $table->unsignedBigInteger($fk)->nullable()->index()->after('guard_name');
                }
                // adjust unique index to include tenant FK
                try { $table->dropUnique('roles_name_guard_name_unique'); } catch (\Throwable $e) {}
                $table->unique(array_filter(['name', 'guard_name', $fk]));
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'label')) $table->dropColumn('label');
            if (Schema::hasColumn('roles', 'description')) $table->dropColumn('description');
            if (Schema::hasColumn('roles', 'deleted_at')) $table->dropSoftDeletes();

            if (config('roles.tenancy.mode') === 'team_scoped') {
                $fk = config('permission.team_foreign_key', 'team_id');
                if (Schema::hasColumn('roles', $fk)) {
                    try { $table->dropUnique(['name', 'guard_name', $fk]); } catch (\Throwable $e) {}
                    $table->dropColumn($fk);
                }
                $table->unique(['name', 'guard_name']);
            }
        });
    }
};