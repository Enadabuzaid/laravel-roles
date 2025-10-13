<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $multi = (bool) config('roles.i18n.enabled', false);

        Schema::table('roles', function (Blueprint $table) use ($multi) {
            if ($multi) {
                // Multi-language: store translatable fields as JSON
                if (!Schema::hasColumn('roles', 'label')) {
                    $table->json('label')->nullable()->after('name');
                }
                if (!Schema::hasColumn('roles', 'description')) {
                    $table->json('description')->nullable()->after('label');
                }
            } else {
                // Single-language: no label; plain text description
                if (!Schema::hasColumn('roles', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                // Note: Do NOT add a 'label' column in single-language mode
            }

            // Soft deletes are useful regardless of i18n
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
                try { $table->dropUnique(['name', 'guard_name']); } catch (\Throwable $e) {}
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