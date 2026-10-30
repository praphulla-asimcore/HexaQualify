<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A vendor's response to an invitation (table "responses"). */
class VendorResponse extends Model
{
    use HasUuids;

    protected $table = 'responses';

    protected $fillable = [
        'invitation_id', 'vendor_json', 'answers_json', 'declaration_at', 'submitted_at', 'submitted_ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'vendor_json' => 'array', 'answers_json' => 'array',
            'declaration_at' => 'datetime', 'submitted_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'response_id')->orderByDesc('computed_at');
    }

    public function latestScore(): HasOne
    {
        // Ordered hasOne rather than latestOfMany(): Postgres has no max() for uuid keys.
        return $this->hasOne(Score::class, 'response_id')->orderByDesc('computed_at')->orderByDesc('id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class, 'response_id')->orderByDesc('decided_at');
    }

    public function latestDecision(): HasOne
    {
        return $this->hasOne(Decision::class, 'response_id')->orderByDesc('decided_at')->orderByDesc('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResponseFile::class, 'response_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** Final result: the reviewer's decision if any, else the calculated result. */
    public function finalResult(): ?string
    {
        return $this->latestDecision?->final_result ?? $this->latestScore?->result;
    }
}
