<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\VendorResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Dashboard list + export share the same filters, so the export always matches the screen. */
class ResponseQuery
{
    public const RESULTS = ['Pass', 'Fail', 'Review'];

    public static function filters(Request $request): array
    {
        return [
            'questionnaire' => $request->string('questionnaire')->toString() ?: null,
            'result' => in_array($request->input('result'), self::RESULTS, true) ? $request->input('result') : null,
            'from' => self::date($request->input('from')),
            'to' => self::date($request->input('to')),
            'q' => trim((string) $request->input('q')) ?: null,
        ];
    }

    /** @return Collection<int, array> */
    public static function rows(array $f): Collection
    {
        $query = VendorResponse::query()
            ->whereNotNull('submitted_at')
            ->with(['invitation.version.questionnaire', 'latestScore', 'latestDecision.decider'])
            ->orderByDesc('submitted_at');

        if ($f['questionnaire']) {
            $query->whereHas('invitation.version', fn ($q) => $q->where('questionnaire_id', $f['questionnaire']));
        }
        if ($f['from']) {
            $query->where('submitted_at', '>=', Carbon::parse($f['from'], 'Asia/Kathmandu')->startOfDay());
        }
        if ($f['to']) {
            $query->where('submitted_at', '<=', Carbon::parse($f['to'], 'Asia/Kathmandu')->endOfDay());
        }
        if ($f['q']) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($f['q'])).'%';
            $query->whereHas('invitation', fn ($q) => $q->whereRaw('lower(vendor_company) like ?', [$like])
                ->orWhereRaw('lower(contact_email) like ?', [$like]));
        }

        return $query->get()
            ->map(fn (VendorResponse $r) => self::row($r))
            ->when($f['result'], fn ($rows) => $rows->filter(fn ($row) => self::bucket($row['final_result']) === $f['result']))
            ->values();
    }

    public static function row(VendorResponse $r): array
    {
        $score = $r->latestScore;
        $decision = $r->latestDecision;
        $version = $r->invitation->version;

        return [
            'id' => $r->id,
            'vendor' => $r->invitation->vendor_company,
            'contact_email' => $r->invitation->contact_email,
            'questionnaire' => $version->questionnaire->name,
            'category' => $version->questionnaire->category,
            'received' => $r->submitted_at?->toIso8601String(),
            'pct' => $score?->pct,
            'pass_mark' => $version->pass_mark,
            'knockouts' => count($score?->knockouts_json ?? []),
            'calculated_result' => $score?->result,
            'final_result' => $r->finalResult(),
            'override' => (bool) $decision?->override,
            'decided_by' => $decision?->decider?->name,
            'decided_at' => $decision?->decided_at?->toIso8601String(),
        ];
    }

    /** Pass / Fail / Review (Review includes Unscored). */
    public static function bucket(?string $result): string
    {
        return in_array($result, ['Pass', 'Fail'], true) ? $result : 'Review';
    }

    public static function kpis(): array
    {
        $rows = self::rows(['questionnaire' => null, 'result' => null, 'from' => null, 'to' => null, 'q' => null]);
        $buckets = $rows->countBy(fn ($r) => self::bucket($r['final_result']));

        return [
            'total' => $rows->count(),
            'qualified' => $buckets['Pass'] ?? 0,
            'not_qualified' => $buckets['Fail'] ?? 0,
            'review' => $buckets['Review'] ?? 0,
            'pending_invitations' => Invitation::whereIn('status', Invitation::OPEN)->where('expires_at', '>', now())->count(),
        ];
    }

    private static function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
