<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class InvitationService
{
    /** Open invitations for the same email and questionnaire (duplicate guard). */
    public function duplicates(string $email, Questionnaire $questionnaire): int
    {
        return Invitation::query()
            ->whereRaw('lower(contact_email) = ?', [strtolower($email)])
            ->whereIn('status', Invitation::OPEN)
            ->where('expires_at', '>', now())
            ->whereHas('version', fn ($q) => $q->where('questionnaire_id', $questionnaire->id))
            ->count();
    }

    /** @return array{0: Invitation, 1: string} the invitation and its plain link */
    public function create(array $data, Questionnaire $questionnaire, User $by): array
    {
        [$plain, $hash] = Invitation::newToken();
        $invitation = Invitation::create([
            'questionnaire_version_id' => $questionnaire->latestVersion->id,
            'vendor_company' => $data['vendor_company'],
            'contact_name' => $data['contact_name'],
            'contact_email' => strtolower($data['contact_email']),
            'token_hash' => $hash,
            'status' => 'draft',
            'expires_at' => now()->addDays((int) $data['expiry_days'])->endOfDay(),
            'created_by' => $by->id,
            'note' => $data['note'] ?? null,
            'notify_result' => (bool) ($data['notify_result'] ?? false),
        ]);
        AuditLog::record('invitation.create', 'invitation', $invitation->id, null, [
            'vendor_company' => $invitation->vendor_company, 'questionnaire' => $questionnaire->slug,
            'version' => $questionnaire->latestVersion->version,
        ]);
        $this->send($invitation, $plain);

        return [$invitation, $plain];
    }

    /**
     * (Re)send the invitation. Tokens are stored hashed, so a resend issues a new link and the old one stops working.
     *
     * @return string the new plain link
     */
    public function resend(Invitation $invitation): string
    {
        [$plain, $hash] = Invitation::newToken();
        $invitation->update(['token_hash' => $hash]);
        $this->send($invitation, $plain);
        AuditLog::record('invitation.resend', 'invitation', $invitation->id);

        return $plain;
    }

    public function extend(Invitation $invitation, int $days): void
    {
        $before = ['expires_at' => $invitation->expires_at->toIso8601String(), 'status' => $invitation->status];
        $invitation->expires_at = $invitation->expires_at->isPast() ? now()->addDays($days)->endOfDay() : $invitation->expires_at->addDays($days);
        if ($invitation->status === 'expired') {
            $invitation->status = $invitation->response ? 'in_progress' : ($invitation->opened_at ? 'opened' : 'sent');
        }
        $invitation->save();
        AuditLog::record('invitation.extend', 'invitation', $invitation->id, $before, [
            'expires_at' => $invitation->expires_at->toIso8601String(), 'status' => $invitation->status,
        ]);
    }

    public function revoke(Invitation $invitation): void
    {
        $before = ['status' => $invitation->status];
        $invitation->update(['status' => 'revoked']);
        AuditLog::record('invitation.revoke', 'invitation', $invitation->id, $before, ['status' => 'revoked']);
    }

    public static function link(string $plain): string
    {
        return url('/v/'.$plain);
    }

    private function send(Invitation $invitation, string $plain): void
    {
        Mail::to($invitation->contact_email)->send(new InvitationMail($invitation, self::link($plain)));
        $invitation->sent_at = now();
        if ($invitation->status === 'draft') {
            $invitation->status = 'sent';
        }
        $invitation->save();
    }
}
