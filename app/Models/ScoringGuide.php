<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hidden scoring guide. Readable by admin and reviewer roles only; never used by vendor endpoints. */
class ScoringGuide extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['questionnaire_version_id', 'guide_json'];

    protected function casts(): array
    {
        return ['guide_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Scoring guides are immutable.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }
}
