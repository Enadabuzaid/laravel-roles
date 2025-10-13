<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $multi = (bool) config('roles.i18n.enabled', false);

        Schema::table('permissions', function (Blueprint $table) use ($multi) {
            if ($multi) {
                // Multi-language: translatable fields as JSON
                if (!Schema::hasColumn('permissions', 'label')) {
                    $table->json('label')->nullable()->after('name');
                }
                if (!Schema::hasColumn('permissions', 'description')) {
                    $table->json('description')->nullable()->after('label');
                }
                if (!Schema::hasColumn('permissions', 'group_label')) {
                    $table->json('group_label')->nullable()->after('group');   // {"en":"Users","ar":"المستخدمون"}
                }
            } else {
                // Single-language: no label/group_label; plain text description
                if (!Schema::hasColumn('permissions', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                // Note: Do NOT add 'label' or 'group_label' in single-language mode
            }

            if (!Schema::hasColumn('permissions', 'group')) {
                $table->string('group')->nullable()->after('guard_name'); // slug like 'users' or 'roles'
            }

            if (!Schema::hasColumn('permissions', 'deleted_at')) {
                $table->softDeletes();
            }

            if (config('roles.tenancy.mode') === 'team_scoped') {
                $fk = config('permission.team_foreign_key', 'team_id');
                if (!Schema::hasColumn('permissions', $fk)) {
                    $table->unsignedBigInteger($fk)->nullable()->index()->after('guard_name');
                }
                try {
                    $table->dropUnique(['name', 'guard_name']);
                } catch (\Throwable $e) {
                }
                $table->unique(array_filter(['name', 'guard_name', $fk]));
            }
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'label')) $table->dropColumn('label');
            if (Schema::hasColumn('permissions', 'description')) $table->dropColumn('description');
            if (Schema::hasColumn('permissions', 'group_label')) $table->dropColumn('group_label');
            if (Schema::hasColumn('permissions', 'group')) $table->dropColumn('group');
            if (Schema::hasColumn('permissions', 'deleted_at')) $table->dropSoftDeletes();

            if (config('roles.tenancy.mode') === 'team_scoped') {
                $fk = config('permission.team_foreign_key', 'team_id');
                if (Schema::hasColumn('permissions', $fk)) {
                    try {
                        $table->dropUnique(['name', 'guard_name', $fk]);
                    } catch (\Throwable $e) {
                    }
                    $table->dropColumn($fk);
                }
                $table->unique(['name', 'guard_name']);
            }
        });
    }
};