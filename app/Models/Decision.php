<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'response_id', 'final_result', 'override', 'reason', 'notes', 'no_conflict_confirmed', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['override' => 'boolean', 'no_conflict_confirmed' => 'boolean', 'decided_at' => 'datetime'];
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
