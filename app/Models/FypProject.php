<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FypProject extends Model
{
    // This tells Laravel to use the table name you defined in the migration
    protected $table = 'fyp_projects';

    // These are the fields your CSV Import and Dashboard will use
    protected $fillable = [
        'student_name',
        'student_id',
        'title',
        'supervisor_name',
        'application_type',
        'fyp_phase',
        'semester',
    ];
}
