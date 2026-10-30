<?php

use App\Mail\InvitationMail;
use App\Mail\OtpMail;
use App\Mail\SubmissionReceivedMail;
use App\Mail\SubmittedMail;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('runs the full flow: invite, OTP, draft, return later, submit, scored within seconds', function () {
    $reviewer = staff('reviewer');
    $q = Questionnaire::where('slug', 'it-saas')->first();

    $this->actingAs($reviewer)->post('/invitations', [
        'vendor_company' => 'Everest Cloud', 'contact_name' => 'Anil', 'contact_email' => 'anil@everest.example',
        'questionnaire_id' => $q->id, 'expiry_days' => 14, 'notify_result' => false,
    ])->assertSessionHasNoErrors()->assertSessionHas('link');

    $link = null;
    Mail::assertSent(InvitationMail::class, function ($m) use (&$link) {
        $link = $m->link;

        return $m->hasTo('anil@everest.example');
    });
    $token = basename($link);
    $inv = Invitation::where('contact_email', 'anil@everest.example')->first();
    expect($inv->token_hash)->toBe(hash('sha256', $token))->and($inv->status)->toBe('sent');
    auth()->logout();

    // Landing page asks for the OTP.
    $this->get("/v/$token")->assertOk()->assertInertia(fn ($p) => $p->component('Vendor/Landing'));
    $this->get("/v/$token/form")->assertRedirect("/v/$token");

    $code = null;
    $this->post("/v/$token/otp")->assertRedirect();
    Mail::assertSent(OtpMail::class, function ($m) use (&$code) {
        $code = $m->code;

        return true;
    });
    expect($code)->toMatch('/^\d{6}$/');
    $wrong = $code === '000000' ? '111111' : '000000';
    $this->post("/v/$token/otp/verify", ['code' => $wrong])->assertSessionHasErrors('code');
    $this->post("/v/$token/otp/verify", ['code' => $code])->assertRedirect("/v/$token/form");

    // Save a draft, come back later.
    $this->putJson("/v/$token/draft", ['vendor' => ['company' => 'Everest Cloud'], 'answers' => ['c1' => 'yes', 'c3' => '7', 'zz' => 'ignored']])
        ->assertOk()->assertJsonStructure(['savedAt']);
    expect($inv->fresh()->status)->toBe('in_progress')
        ->and($inv->fresh()->response->answers_json)->toBe(['c1' => 'yes', 'c3' => 7]);
    $this->get("/v/$token/form")->assertOk()->assertInertia(fn ($p) => $p->component('Vendor/Form')->where('answers.c1', 'yes'));

    // Incomplete submit is rejected with highlighted fields.
    $this->post("/v/$token/submit", ['vendor' => [], 'answers' => ['c1' => 'yes'], 'declaration' => false])
        ->assertSessionHasErrors(['vendor.pan', 'answers.c2', 'declaration']);

    $start = microtime(true);
    $response = submitPerfect($this, $inv, $token);
    expect(microtime(true) - $start)->toBeLessThan(5);

    $score = $response->latestScore;
    expect($score->result)->toBe('Review')->and($score->pending_json)->toBe(['c9', 'i14'])
        ->and($inv->fresh()->status)->toBe('scored')
        ->and($response->submitted_ip)->not->toBeNull()
        ->and($response->declaration_at)->not->toBeNull();
    Mail::assertQueued(SubmittedMail::class, fn ($m) => $m->hasTo($reviewer->email));
    Mail::assertQueued(SubmissionReceivedMail::class, fn ($m) => $m->hasTo('anil@everest.example'));

    // Read-only afterwards.
    $this->get("/v/$token/form")->assertRedirect("/v/$token/done");
    $this->putJson("/v/$token/draft", ['answers' => ['c1' => 'no']])->assertStatus(410);
    $this->post("/v/$token/submit", [])->assertStatus(410);
    $this->get("/v/$token/done")->assertOk()->assertInertia(fn ($p) => $p->component('Vendor/Done'));
});

it('locks OTP after 5 wrong attempts', function () {
    [$inv, $token] = invite();
    $this->post("/v/$token/otp");
    foreach (range(1, 5) as $i) {
        $this->post("/v/$token/otp/verify", ['code' => '999999x']);
    }
    $this->post("/v/$token/otp/verify", ['code' => '999999'])->assertSessionHasErrors(['code' => 'Too many attempts. Please wait 15 minutes and request a new code.']);
    expect(app(OtpService::class)->lockedUntil($inv))->not->toBeNull();
});

it('expires OTP codes after 10 minutes', function () {
    [$inv, $token] = invite();
    $this->post("/v/$token/otp");
    $code = null;
    Mail::assertSent(OtpMail::class, function ($m) use (&$code) {
        $code = $m->code;

        return true;
    });
    $this->travel(11)->minutes();
    $this->post("/v/$token/otp/verify", ['code' => $code])->assertSessionHasErrors('code');
});

it('shows a clear message for expired, revoked and unknown links and accepts no submissions', function (string $case) {
    [$inv, $token] = invite();
    if ($case === 'expired') {
        $inv->update(['expires_at' => now()->subMinute()]);
    } elseif ($case === 'revoked') {
        $inv->update(['status' => 'revoked']);
    } else {
        $token = str_repeat('A', 43);
    }

    $this->get("/v/$token")->assertStatus($case === 'unknown' ? 404 : 410)
        ->assertInertia(fn ($p) => $p->component('Vendor/Closed')->where('reason', $case === 'unknown' ? 'invalid' : $case));

    [$structure, $guide] = starter('hrms-payroll');
    asVendor($this, $inv)->post("/v/$token/submit", ['vendor' => vendorDetails(), 'answers' => perfectAnswers($structure, $guide), 'declaration' => true])
        ->assertStatus($case === 'unknown' ? 403 : 410);
    expect($inv->fresh()->response)->toBeNull();
})->with(['expired', 'revoked', 'unknown']);

it('rejects vendor writes without an OTP-verified session', function () {
    [$inv, $token] = invite();
    $this->putJson("/v/$token/draft", ['answers' => ['c1' => 'yes']])->assertForbidden();
    $this->post("/v/$token/submit", [])->assertForbidden();
});

it('scopes a vendor session to its own invitation', function () {
    [$a, $tokenA] = invite();
    [$b, $tokenB] = invite('it-saas', ['contact_email' => 'other@vendor.example']);
    asVendor($this, $a)->get("/v/$tokenB/form")->assertRedirect("/v/$tokenB");
    asVendor($this, $a)->putJson("/v/$tokenB/draft", ['answers' => []])->assertForbidden();
});

it('rate-limits OTP endpoints', function () {
    [$inv, $token] = invite();
    foreach (range(1, 10) as $i) {
        $this->post("/v/$token/otp/verify", ['code' => '1']);
    }
    $this->post("/v/$token/otp/verify", ['code' => '1'])->assertStatus(429);
});
