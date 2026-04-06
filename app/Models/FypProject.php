<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FypProject extends Model
{
    use HasFactory;

    // This tells Laravel to use the table name you defined in the migration
    protected $table = 'fyp_projects';

    // These are the fields your CSV Import and Dashboard will use
    protected $fillable = [
        'student_name',
        'student_id',
        'title',
        'supervisor_name',
        'assessor_name',
        'domain',           // Category 1
        'application_type', // Category 2
        'is_ifyp',           // Category 3
        'fyp_phase',
        'semester',
    ];
}
