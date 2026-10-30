<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use Illuminate\Support\Facades\Mail;

/** Email one-time codes for vendors: 6 digits, 10-minute validity, 5 attempts then a 15-minute lock. */
class OtpService
{
    public function lockedUntil(Invitation $invitation): ?\DateTimeInterface
    {
        $locked = $invitation->otpChallenges()->where('locked_until', '>', now())->max('locked_until');

        return $locked ? new \DateTimeImmutable($locked) : null;
    }

    public function send(Invitation $invitation): void
    {
        if ($this->lockedUntil($invitation)) {
            return;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Only one live code at a time.
        $invitation->otpChallenges()->whereNull('verified_at')->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
        $invitation->otpChallenges()->create([
            'code_hash' => $this->hash($invitation, $code),
            'expires_at' => now()->addMinutes(config('hexaqualify.otp.ttl_minutes')),
        ]);
        Mail::to($invitation->contact_email)->send(new OtpMail($invitation, $code));
        AuditLog::record('otp.sent', 'invitation', $invitation->id, actorType: 'vendor', actorId: $invitation->id);
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public function verify(Invitation $invitation, string $code): string
    {
        if ($this->lockedUntil($invitation)) {
            return 'locked';
        }
        $challenge = $invitation->otpChallenges()->whereNull('verified_at')->latest()->first();
        if (! $challenge || $challenge->expires_at->isPast()) {
            return 'expired';
        }

        if (! hash_equals($challenge->code_hash, $this->hash($invitation, preg_replace('/\D/', '', $code)))) {
            $challenge->attempts++;
            if ($challenge->attempts >= config('hexaqualify.otp.max_attempts')) {
                $challenge->locked_until = now()->addMinutes(config('hexaqualify.otp.lock_minutes'));
                $challenge->expires_at = now();
                AuditLog::record('otp.locked', 'invitation', $invitation->id, actorType: 'vendor', actorId: $invitation->id);
            }
            $challenge->save();

            return $challenge->locked_until ? 'locked' : 'invalid';
        }

        $challenge->update(['verified_at' => now()]);
        AuditLog::record('vendor.login', 'invitation', $invitation->id, actorType: 'vendor', actorId: $invitation->id);

        return 'ok';
    }

    private function hash(Invitation $invitation, string $code): string
    {
        return hash_hmac('sha256', $invitation->id.'|'.$code, config('app.key'));
    }
}
