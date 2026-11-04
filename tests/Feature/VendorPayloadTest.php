<?php

use App\Mail\ResultMail;
use App\Models\QuestionnaireVersion;
use Illuminate\Support\Facades\Mail;

/**
 * §7: no vendor endpoint payload may contain scoring data.
 */
const FORBIDDEN_KEYS = ['weight', 'expected', 'points', 'koOptions', 'passMark', 'note'];

function assertNoScoringData(string $body): void
{
    foreach (FORBIDDEN_KEYS as $key) {
        expect($body)->not->toContain("\"$key\"", "vendor payload leaked \"$key\"")
            ->and($body)->not->toContain("&quot;$key&quot;", "vendor payload leaked \"$key\" (HTML-encoded)");
    }
    // Guide notes must not appear as values either.
    expect($body)->not->toContain('Must be a registered entity able to issue VAT/PAN invoices')
        ->and($body)->not->toContain('Hosting location must be named');
}

beforeEach(fn () => Mail::fake());

it('never sends scoring data to any vendor endpoint', function () {
    foreach (['hrms-payroll', 'it-saas', 'professional', 'facilities', 'recruitment-staffing'] as $i => $slug) {
        [$inv, $token] = invite($slug, ['contact_email' => "v$i@vendor.example", 'note' => 'internal note: low priority']);
        [$structure, $guide] = starter($slug);
        $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => ''];

        $bodies = [];
        $bodies[] = $this->get("/v/$token")->getContent();
        $bodies[] = $this->get("/v/$token", $inertia)->getContent();
        $bodies[] = $this->post("/v/$token/otp")->getContent();
        $bodies[] = asVendor($this, $inv)->get("/v/$token/form")->getContent();
        $bodies[] = asVendor($this, $inv)->get("/v/$token/form", $inertia)->getContent();
        $bodies[] = asVendor($this, $inv)->putJson("/v/$token/draft", ['answers' => ['c1' => 'yes']])->getContent();
        $bodies[] = asVendor($this, $inv)->post("/v/$token/submit", ['vendor' => vendorDetails(), 'answers' => perfectAnswers($structure, $guide), 'declaration' => true])->getContent();
        $bodies[] = asVendor($this, $inv)->get("/v/$token/done")->getContent();
        $bodies[] = asVendor($this, $inv)->get("/v/$token/done", $inertia)->getContent();
        $bodies[] = $this->get("/v/$token")->getContent(); // closed: already submitted

        foreach ($bodies as $body) {
            assertNoScoringData($body);
            expect($body)->not->toContain('internal note')->not->toContain('Pass mark');
        }
        expect($inv->fresh()->response->latestScore)->not->toBeNull();
    }
});

it('keeps scoring fields out of the stored vendor-safe structure', function () {
    foreach (QuestionnaireVersion::all() as $v) {
        assertNoScoringData(json_encode($v->structure_json));
    }
})->with([fn () => staff('admin')]);

it('never leaks scores in vendor emails', function () {
    Mail::fake();
    $reviewer = staff('reviewer');
    [$inv, $token] = invite('it-saas', ['notify_result' => true]);
    $response = submitPerfect($this, $inv, $token);
    $this->actingAs($reviewer)->post("/responses/{$response->id}/decision", ['override' => true, 'final_result' => 'Pass', 'reason' => 'Strong references', 'no_conflict' => true]);

    Mail::assertQueued(ResultMail::class, function ($m) {
        $text = strip_tags($m->render());
        expect($text)->toContain('qualified')->not->toMatch('/\d+(\.\d)?\s*%/')->not->toContain('score')->not->toContain('points');

        return true;
    });
});
