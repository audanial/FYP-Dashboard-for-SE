<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->integer('pair_number')->nullable()->after('semester');
        });
    }

    public function down(): void
    {
        Schema::table('fyp_projects', function (Blueprint $table) {
            $table->dropColumn('pair_number');
        });
    }
};
