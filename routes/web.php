<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\InvitationController;
use App\Http\Controllers\Staff\QuestionnaireController;
use App\Http\Controllers\Staff\ResponseController;
use App\Http\Controllers\Staff\SettingsController;
use App\Http\Controllers\Staff\StyleguideController;
use App\Http\Controllers\Vendor\PortalController;
use Illuminate\Support\Facades\Route;

/*
| Vendor portal: invitation token + email OTP. No staff session needed.
*/
Route::prefix('v/{token}')->name('vendor.')->where(['token' => '[A-Za-z0-9_-]{40,64}'])->group(function () {
    Route::get('/', [PortalController::class, 'landing'])->middleware('throttle:vendor-token')->name('landing');
    Route::post('/otp', [PortalController::class, 'sendOtp'])->middleware('throttle:vendor-otp')->name('otp');
    Route::post('/otp/verify', [PortalController::class, 'verifyOtp'])->middleware('throttle:vendor-otp')->name('otp.verify');
    Route::get('/form', [PortalController::class, 'form'])->middleware('throttle:vendor-token')->name('form');
    Route::put('/draft', [PortalController::class, 'draft'])->middleware('throttle:vendor-draft')->name('draft');
    Route::post('/submit', [PortalController::class, 'submit'])->middleware('throttle:vendor-otp')->name('submit');
    Route::get('/done', [PortalController::class, 'done'])->name('done');
});

/*
| Staff: Google Workspace SSO, role checked on every route.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/auth/google/redirect', [AuthController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'callback'])->middleware('throttle:10,1');
    Route::post('/login/dev', [AuthController::class, 'devLogin'])->middleware('throttle:10,1')->name('login.dev');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // All staff roles (viewer = read-only).
    Route::middleware('role:admin,reviewer,viewer')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/export/responses.csv', [DashboardController::class, 'export'])->name('export.responses');
        Route::get('/responses/{response}', [ResponseController::class, 'show'])->name('responses.show');
        Route::get('/questionnaires', [QuestionnaireController::class, 'index'])->name('questionnaires.index');
        Route::get('/styleguide', StyleguideController::class)->name('styleguide');
    });

    // Reviewers and admins.
    Route::middleware('role:admin,reviewer')->group(function () {
        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/check', [InvitationController::class, 'check'])->name('invitations.check');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::post('/invitations/{invitation}/extend', [InvitationController::class, 'extend'])->name('invitations.extend');
        Route::post('/invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');

        Route::post('/responses/{response}/manual-scores', [ResponseController::class, 'manualScores'])->name('responses.manual');
        Route::post('/responses/{response}/ai-suggest', [ResponseController::class, 'aiSuggest'])->middleware('throttle:10,1')->name('responses.ai');
        Route::post('/responses/{response}/decision', [ResponseController::class, 'decide'])->name('responses.decide');

        Route::get('/questionnaires/{questionnaire}/preview', [QuestionnaireController::class, 'preview'])->name('questionnaires.preview');
    });

    // Admins only.
    Route::middleware('role:admin')->group(function () {
        Route::post('/responses/{response}/rescore', [ResponseController::class, 'rescore'])->name('responses.rescore');

        Route::get('/questionnaires/create', [QuestionnaireController::class, 'create'])->name('questionnaires.create');
        Route::post('/questionnaires', [QuestionnaireController::class, 'store'])->name('questionnaires.store');
        Route::get('/questionnaires/{questionnaire}/edit', [QuestionnaireController::class, 'edit'])->withTrashed()->name('questionnaires.edit');
        Route::put('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'update'])->withTrashed()->name('questionnaires.update');
        Route::post('/questionnaires/{questionnaire}/duplicate', [QuestionnaireController::class, 'duplicate'])->withTrashed()->name('questionnaires.duplicate');
        Route::post('/questionnaires/{questionnaire}/archive', [QuestionnaireController::class, 'archive'])->name('questionnaires.archive');
        Route::post('/questionnaires/{id}/restore', [QuestionnaireController::class, 'restore'])->name('questionnaires.restore');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/users', [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [SettingsController::class, 'updateUser'])->name('users.update');
    });
});
