<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->string('phase')->default('FYP 1')->after('semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
