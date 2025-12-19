<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add status column to roles table
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'status')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->after('guard_name');
                $table->index('status');
            });

            // Set existing roles to active status
            DB::table('roles')->whereNull('deleted_at')->update(['status' => 'active']);
            DB::table('roles')->whereNotNull('deleted_at')->update(['status' => 'deleted']);
        }

        // Add status column to permissions table
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'status')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->after('guard_name');
                $table->index('status');
            });

            // Set existing permissions to active status
            DB::table('permissions')->whereNull('deleted_at')->update(['status' => 'active']);
            DB::table('permissions')->whereNotNull('deleted_at')->update(['status' => 'deleted']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('roles', 'status')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('permissions', 'status')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};

