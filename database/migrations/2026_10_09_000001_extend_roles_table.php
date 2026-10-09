<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->jsonb('label')->nullable();
            $table->jsonb('description')->nullable();
            $table->string('color', 16)->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->dropIndex(['is_system']);
        });

        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'color', 'is_system', 'is_default', 'sort_order', 'deleted_at']);
        });
    }
};
