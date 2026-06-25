<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            //1. Remove the OLD rule: student_id unique on its own.
            $table->dropUnique('fyp_projects_student_id_unique');

            // 2. Add the NEW rule: the three columns must be unique *together*
            $table->unique(['student_id', 'semester', 'fyp_phase']);
        });
    }

    public function down(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            // Reverse it, in the opposite order.
            $table->dropUnique(['student_id', 'semester', 'fyp_phase']);
            $table->unique('student_id');
        });
        
    }
};
