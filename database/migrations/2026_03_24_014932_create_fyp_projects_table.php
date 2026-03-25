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
        Schema::create('fyp_projects', function (Blueprint $table) {
            $table->id();
            $table->string('student_name');
            $table->string('student_id')->unique();
            $table->string('title');
            $table->string('supervisor_name');
            // New fields based on coordinator's info:
            $table->enum('application_type', ['Web App', 'Mobile App', 'Desktop App', 'IoT/Hardware']);
            $table->enum('fyp_phase', ['FYP 1', 'FYP 2']);
            $table->string('semester'); // e.g., "MARCH 2026"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fyp_projects');
    }
};
