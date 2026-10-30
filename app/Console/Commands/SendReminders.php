<?php

namespace App\Console\Commands;

use App\Mail\ReminderMail;
use App\Models\AuditLog;
use App\Models\Invitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Remind vendors 3 days before expiry if they have not submitted (§4.7). */
class SendReminders extends Command
{
    protected $signature = 'hexaqualify:remind';

    protected $description = 'Email vendors whose invitation expires within 3 days and is not yet submitted';

    public function handle(): int
    {
        $due = Invitation::with('version.questionnaire')
            ->whereIn('status', ['sent', 'opened', 'in_progress'])
            ->whereNull('reminded_at')
            ->whereBetween('expires_at', [now(), now()->addDays(3)])
            ->get();

        foreach ($due as $invitation) {
            Mail::to($invitation->contact_email)->queue(new ReminderMail($invitation));
            $invitation->update(['reminded_at' => now()]);
            AuditLog::record('invitation.reminder', 'invitation', $invitation->id, actorType: 'system');
        }
        $this->info("Sent {$due->count()} reminder(s).");

        return self::SUCCESS;
    }
}
