<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FypProject extends Model
{
    use HasFactory;

    // This tells Laravel to use the table name you defined in the migration
    protected $table = 'fyp_projects';

    // These are the fields your CSV Import and Dashboard will use
    protected $attributes = [
        'domain'           => 'Others',
        'application_type' => 'Unknown',
        'is_ifyp'          => false,
        'phase'            => 'FYP 1',
    ];

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
        'phase',
        'semester',
        'pair_number',
        'supervisor_id',
    ];

    /**
     * The supervisor (user) this project is assigned to, when linked.
     * Nullable: legacy/unmatched rows have supervisor_id = null.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
