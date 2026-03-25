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
            $table->string('app_type')->default('Web App'); // Web App, Mobile App, etc.ph
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            //
        });
    }
};
