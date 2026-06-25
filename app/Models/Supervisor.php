<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supervisor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_slug',
        'email',
        'user_id',
        'confirmed',
    ];

    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
        ];
    }

    /**
     * The login account for this roster entry. Projects link to users.id via
     * fyp_projects.supervisor_id, so this is how a matched roster row resolves
     * to the FK value.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
