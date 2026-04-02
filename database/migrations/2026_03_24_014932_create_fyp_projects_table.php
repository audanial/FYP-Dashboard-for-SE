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

            // Category 1: Domain (Using the Enum for Medical, AI, etc.)
            $table->enum('domain', ['Medical', 'AI', 'IoT', 'Education', 'Business', 'Others'])->default('Others');

            // Category 2: Platform (Updated with your specific requirements)
            $table->enum('application_type', ['Web App', 'Mobile App', 'PWA', 'Cross Platform', 'Desktop App', 'IoT/Hardware']);

            // Category 3: IFYP Status (true = Industrial, false = Regular)
            $table->boolean('is_ifyp')->default(false);

            $table->enum('fyp_phase', ['FYP 1', 'FYP 2']);
            $table->string('semester'); // e.g., "MARCH 2026"

            $table->timestamps(); // Only one timestamps() at the end
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
