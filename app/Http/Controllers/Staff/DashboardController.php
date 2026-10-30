<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Questionnaire;
use App\Services\ResponseQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = ResponseQuery::filters($request);
        $rows = ResponseQuery::rows($filters);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        return Inertia::render('Dashboard', [
            'kpis' => ResponseQuery::kpis(),
            'filters' => $filters,
            'rows' => new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
                'path' => $request->url(), 'query' => $request->query(),
            ]),
            'questionnaires' => Questionnaire::withTrashed()->orderBy('sort_order')->get(['id', 'name', 'category']),
        ]);
    }

    /** CSV export of exactly the filtered rows (opens in Excel). */
    public function export(Request $request): StreamedResponse
    {
        $filters = ResponseQuery::filters($request);
        $rows = ResponseQuery::rows($filters);
        AuditLog::record('export.responses', 'response', null, null, ['filters' => $filters, 'rows' => $rows->count()]);

        $labels = ['Pass' => 'Qualified', 'Fail' => 'Not qualified', 'Review' => 'Needs review', 'Unscored' => 'Unscored'];

        return response()->streamDownload(function () use ($rows, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Vendor', 'Contact email', 'Questionnaire', 'Received', 'Score %', 'Pass mark %', 'Knockouts failed', 'Calculated result', 'Final result', 'Override', 'Decided by', 'Decided at']);
            foreach ($rows as $r) {
                fputcsv($out, array_map([self::class, 'safe'], [
                    $r['vendor'], $r['contact_email'], $r['questionnaire'], $r['received'], $r['pct'], $r['pass_mark'],
                    $r['knockouts'], $labels[$r['calculated_result']] ?? '', $labels[$r['final_result']] ?? '',
                    $r['override'] ? 'Yes' : 'No', $r['decided_by'], $r['decided_at'],
                ]));
            }
            fclose($out);
        }, 'hexaqualify-responses-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection from vendor-supplied text. */
    public static function safe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
