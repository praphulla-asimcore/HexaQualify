<?php

namespace App\Models;

use App\Services\Scoring\ScoringEngine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** An immutable snapshot of a questionnaire. structure_json is vendor-safe. */
class QuestionnaireVersion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['questionnaire_id', 'version', 'pass_mark', 'structure_json', 'created_by'];

    protected function casts(): array
    {
        return ['structure_json' => 'array', 'pass_mark' => 'float'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Questionnaire versions are immutable.'));
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class)->withTrashed();
    }

    public function guide(): HasOne
    {
        return $this->hasOne(ScoringGuide::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): array
    {
        return ScoringEngine::questions($this->structure_json);
    }
}
