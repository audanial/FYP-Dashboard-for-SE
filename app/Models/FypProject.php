<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Dual-read ownership scope for the supervisor_id transition.
     *
     * Prefers the structural FK; falls back to the legacy supervisor_name
     * match only for rows where supervisor_id IS NULL. ID precedence is
     * mandatory — a row owned by another supervisor via supervisor_id must
     * never surface through a coincidental name match.
     *
     * Phase 5 retirement: when supervisor_id is populated for every row,
     * replace the body with ->where('supervisor_id', $supervisorId) and
     * remove the $supervisorName parameter.
     */
    public function scopeForSupervisor(Builder $query, int $supervisorId, string $supervisorName): void
    {
        $query->where(function (Builder $q) use ($supervisorId, $supervisorName) {
            $q->where('supervisor_id', $supervisorId)
              ->orWhere(function (Builder $q) use ($supervisorName) {
                  $q->whereNull('supervisor_id')
                    ->where('supervisor_name', $supervisorName);
              });
        });
    }
}
