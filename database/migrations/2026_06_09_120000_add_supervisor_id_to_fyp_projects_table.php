<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: a nullable supervisor_id FK to users.id. Existing rows
     * keep supervisor_id = null, so nothing reads differently yet. The legacy
     * supervisor_name string is left untouched (display/fallback for now).
     */
    public function up(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('supervisor_name')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // SQLite rebuilds the table on column drop, removing the FK with it.
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->dropColumn('supervisor_id');
        });
    }
};
