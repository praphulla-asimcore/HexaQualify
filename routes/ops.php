<?php

use App\Http\Controllers\OpsController;
use Illuminate\Support\Facades\Route;

/*
| Ops: scheduled jobs and migrations for serverless hosts, guarded by the CRON_SECRET bearer token.
*/
Route::get('/ops/cron/daily', [OpsController::class, 'daily']);
Route::post('/ops/migrate', [OpsController::class, 'migrate']);
