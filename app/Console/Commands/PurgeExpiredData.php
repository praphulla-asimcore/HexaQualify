<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\OtpChallenge;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data retention purge (Individual Privacy Act 2075): deletes vendor data the configured number of
 * years after the decision (or after expiry/revocation when never submitted).
 */
class PurgeExpiredData extends Command
{
    protected $signature = 'hexaqualify:purge {--dry-run : List what would be deleted}';

    protected $description = 'Delete vendor data past the retention period';

    public function handle(): int
    {
        $years = (int) Setting::get('retention_years');
        $cutoff = now()->subYears($years);

        $decided = Invitation::query()
            ->whereHas('response.decisions', fn ($q) => $q->where('decided_at', '<', $cutoff))
            ->whereDoesntHave('response.decisions', fn ($q) => $q->where('decided_at', '>=', $cutoff));
        $abandoned = Invitation::query()
            ->whereIn('status', ['expired', 'revoked', ...Invitation::OPEN])
            ->where('expires_at', '<', $cutoff)
            ->whereDoesntHave('response', fn ($q) => $q->whereNotNull('submitted_at'));

        $ids = $decided->pluck('id')->merge($abandoned->pluck('id'))->unique();
        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleting')." {$ids->count()} invitation(s) older than {$years} year(s).");
        if ($this->option('dry-run') || $ids->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($ids->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach (Invitation::with('response.files')->whereIn('id', $chunk)->get() as $inv) {
                    foreach ($inv->response?->files ?? [] as $file) {
                        Storage::disk(config('filesystems.default'))->delete($file->storage_key);
                    }
                    $inv->delete(); // cascades to responses, scores, decisions, files, OTPs
                    AuditLog::record('retention.purge', 'invitation', $inv->id, null, null, 'system');
                }
            });
        }
        OtpChallenge::where('expires_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }
}
