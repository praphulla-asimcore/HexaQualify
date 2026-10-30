<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Serverless hosts (Vercel) have no shell, queue worker or scheduler. These endpoints run the
 * scheduled jobs and migrations, and only answer when the request carries the CRON_SECRET
 * bearer token (Vercel Cron sends it automatically).
 */
class OpsController extends Controller
{
    public function daily(Request $request)
    {
        $this->authorise($request);
        $out = [];
        foreach (['hexaqualify:expire', 'hexaqualify:remind', 'hexaqualify:purge'] as $command) {
            Artisan::call($command);
            $out[$command] = trim(Artisan::output());
        }

        return response()->json($out);
    }

    public function migrate(Request $request)
    {
        $this->authorise($request);
        Artisan::call('migrate', ['--force' => true]);
        $migrate = trim(Artisan::output());
        // Idempotent: seeds the baseline block and any missing starter questionnaires.
        Artisan::call('db:seed', ['--force' => true]);

        return response()->json(['migrate' => $migrate, 'seed' => trim(Artisan::output())]);
    }

    private function authorise(Request $request): void
    {
        $secret = (string) config('hexaqualify.cron_secret');
        abort_unless(strlen($secret) >= 32 && hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 404);
    }
}
