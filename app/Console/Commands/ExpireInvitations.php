<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invitation;
use Illuminate\Console\Command;

class ExpireInvitations extends Command
{
    protected $signature = 'hexaqualify:expire';

    protected $description = 'Mark open invitations past their expiry date as expired';

    public function handle(): int
    {
        $expired = Invitation::whereIn('status', Invitation::OPEN)->where('expires_at', '<=', now())->get();
        foreach ($expired as $invitation) {
            $before = $invitation->status;
            $invitation->update(['status' => 'expired']);
            AuditLog::record('invitation.expire', 'invitation', $invitation->id, ['status' => $before], ['status' => 'expired'], 'system');
        }
        $this->info("Expired {$expired->count()} invitation(s).");

        return self::SUCCESS;
    }
}
