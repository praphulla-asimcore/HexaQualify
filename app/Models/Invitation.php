<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invitation extends Model
{
    use HasUuids;

    public const STATUSES = ['draft', 'sent', 'opened', 'in_progress', 'submitted', 'scored', 'decided', 'expired', 'revoked'];

    /** States in which the vendor can still work on the form. */
    public const OPEN = ['draft', 'sent', 'opened', 'in_progress'];

    protected $fillable = [
        'questionnaire_version_id', 'vendor_company', 'contact_name', 'contact_email', 'token_hash',
        'status', 'expires_at', 'sent_at', 'opened_at', 'reminded_at', 'created_by', 'note', 'notify_result',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'sent_at' => 'datetime', 'opened_at' => 'datetime',
            'reminded_at' => 'datetime', 'notify_result' => 'boolean',
        ];
    }

    /** Generate a CSPRNG token (32 bytes, URL-safe) and return [plain, sha256]. */
    public static function newToken(): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [$plain, self::hashToken($plain)];
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByToken(string $plain): ?self
    {
        if (strlen($plain) < 40 || strlen($plain) > 64) {
            return null;
        }

        return static::where('token_hash', self::hashToken($plain))->first();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireVersion::class, 'questionnaire_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function response(): HasOne
    {
        return $this->hasOne(VendorResponse::class);
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expires_at->isPast() && in_array($this->status, self::OPEN, true));
    }

    /** Can the vendor still edit and submit? */
    public function acceptsAnswers(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->expires_at->isFuture();
    }

    /** Status shown to staff, accounting for links that expired without a status update yet. */
    public function effectiveStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function advanceTo(string $status): void
    {
        $order = array_flip(self::STATUSES);
        if (in_array($this->status, ['expired', 'revoked'], true) || $order[$status] <= $order[$this->status]) {
            return;
        }
        $this->status = $status;
        $this->save();
    }
}
