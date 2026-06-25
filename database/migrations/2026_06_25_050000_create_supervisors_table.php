<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical roster of SE lecturers, seeded from storage/app/csv/SE_Lecturers.csv.
     *
     * name_slug is the normalized match key (see App\Support\SupervisorName) that
     * the FYP CSV import resolves a messy SUPERVISOR string against. user_id links
     * the roster row to the lecturer's login account, so a matched project sets
     * fyp_projects.supervisor_id (which references users.id) to this user_id.
     */
    public function up(): void
    {
        Schema::create('supervisors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_slug')->unique();
            $table->string('email')->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisors');
    }
};
