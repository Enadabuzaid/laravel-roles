<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Permission Metadata Columns
 *
 * This migration adds nullable metadata columns to the permissions table:
 * - label: Human-readable label for the permission
 * - description: Description of what the permission allows
 * - group_label: Human-readable label for the permission group
 *
 * These columns are required for the roles:sync command to work properly.
 * Columns are added as TEXT for SQLite compatibility.
 *
 * This migration is idempotent - safe to run multiple times.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Add label column if not exists (TEXT for SQLite compatibility)
            if (!Schema::hasColumn('permissions', 'label')) {
                $table->text('label')->nullable()->after('name');
            }

            // Add description column if not exists
            if (!Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('label');
            }

            // Add group_label column if not exists
            if (!Schema::hasColumn('permissions', 'group_label')) {
                $table->text('group_label')->nullable()->after('group');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'label')) {
                $table->dropColumn('label');
            }
            if (Schema::hasColumn('permissions', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('permissions', 'group_label')) {
                $table->dropColumn('group_label');
            }
        });
    }
};
