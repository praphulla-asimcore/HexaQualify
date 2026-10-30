<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A score snapshot. Each new row supersedes the previous one; history is kept. */
class Score extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'response_id', 'guide_id', 'score', 'max', 'pct', 'knockouts_json', 'pending_json', 'manual_json',
        'result', 'ai_suggestions_json', 'computed_at', 'computed_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float', 'max' => 'float', 'pct' => 'float',
            'knockouts_json' => 'array', 'pending_json' => 'array', 'manual_json' => 'array',
            'ai_suggestions_json' => 'array', 'computed_at' => 'datetime',
        ];
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(ScoringGuide::class);
    }
}
