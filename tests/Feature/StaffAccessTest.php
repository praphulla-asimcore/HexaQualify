<?php

use App\Http\Controllers\Staff\DashboardController;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\User;
use App\Services\QuestionnaireService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;

beforeEach(fn () => Mail::fake());

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/invitations')->assertRedirect('/login');
});

it('enforces roles server-side', function () {
    $viewer = staff('viewer');
    $reviewer = staff('reviewer');
    $q = Questionnaire::first();

    $this->actingAs($viewer)->get('/')->assertOk();
    $this->actingAs($viewer)->get('/invitations')->assertForbidden();
    $this->actingAs($viewer)->post('/invitations', [])->assertForbidden();

    $this->actingAs($reviewer)->get('/invitations')->assertOk();
    $this->actingAs($reviewer)->get("/questionnaires/{$q->id}/edit")->assertForbidden();
    $this->actingAs($reviewer)->put("/questionnaires/{$q->id}", [])->assertForbidden();
    $this->actingAs($reviewer)->get('/settings')->assertForbidden();
});

it('hides the scoring guide and breakdown from viewers', function () {
    $viewer = staff('viewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token);
    $this->actingAs($viewer)->get("/responses/{$response->id}")->assertOk()
        ->assertInertia(fn ($p) => $p->where('guide', null)->where('breakdown', null)->where('can.review', false));
    $this->actingAs($viewer)->get('/questionnaires')->assertInertia(fn ($p) => $p->where('items.0.pass_mark', null));
});

it('seeds the recruitment questionnaire for review and invitations idempotently', function () {
    $admin = staff('admin');
    $questionnaire = Questionnaire::where('slug', 'recruitment-staffing')->firstOrFail();
    $version = $questionnaire->latestVersion;

    expect(count($version->questions()))->toBe(49)
        ->and($version->pass_mark)->toEqual(70)
        ->and(array_sum(array_column($version->guide->guide_json['q'], 'weight')))->toEqual(112);

    $this->actingAs($admin)->get('/questionnaires')->assertInertia(fn ($page) => $page
        ->where('items', fn ($items) => collect($items)->contains(fn ($item) => $item['slug'] === 'recruitment-staffing')));
    $this->actingAs($admin)->get('/invitations')->assertInertia(fn ($page) => $page
        ->where('questionnaires', fn ($items) => collect($items)->contains(fn ($item) => $item['id'] === $questionnaire->id)));

    (new DatabaseSeeder)->run(app(QuestionnaireService::class));

    expect(Questionnaire::where('slug', 'recruitment-staffing')->count())->toBe(1)
        ->and($questionnaire->versions()->count())->toBe(1);
});

it('warns about duplicate open invitations', function () {
    $reviewer = staff('reviewer');
    $q = Questionnaire::where('slug', 'facilities')->first();
    $data = ['vendor_company' => 'Valley', 'contact_name' => 'R', 'contact_email' => 'r@valley.example', 'questionnaire_id' => $q->id, 'expiry_days' => 14];

    $this->actingAs($reviewer)->post('/invitations', $data)->assertSessionHasNoErrors();
    $this->actingAs($reviewer)->postJson('/invitations/check', ['contact_email' => 'R@Valley.example', 'questionnaire_id' => $q->id])->assertJson(['open' => 1]);
    $this->actingAs($reviewer)->post('/invitations', $data)->assertSessionHasErrors('contact_email');
    $this->actingAs($reviewer)->post('/invitations', $data + ['confirm_duplicate' => true])->assertSessionHasNoErrors();
    expect(Invitation::where('contact_email', 'r@valley.example')->count())->toBe(2);
});

it('resends with a fresh link, extends and revokes invitations', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();

    $this->actingAs($reviewer)->post("/invitations/{$inv->id}/resend")->assertSessionHas('link');
    $this->get("/v/$token")->assertStatus(404); // old link no longer works

    $inv->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
    $this->actingAs($reviewer)->post("/invitations/{$inv->id}/extend", ['days' => 7]);
    expect($inv->fresh()->acceptsAnswers())->toBeTrue();

    $this->actingAs($reviewer)->post("/invitations/{$inv->id}/revoke");
    expect($inv->fresh()->status)->toBe('revoked');
});

it('lets an admin build and publish a questionnaire with all four question types', function () {
    $admin = staff('admin');
    $this->actingAs($admin)->post('/questionnaires', [
        'name' => 'Marketing agencies', 'category' => 'Marketing & creative', 'description' => 'Agencies.', 'active' => true, 'pass_mark' => 60,
        'sections' => [
            ['block' => 'company-integrity'],
            ['title' => 'Capability', 'questions' => [
                ['id' => 'm1', 'type' => 'yesno', 'text' => 'Do you have a Nepal office?', 'required' => true, 'guide' => ['weight' => 4, 'expected' => 'yes', 'ko' => true, 'note' => 'Local presence']],
                ['id' => 'm2', 'type' => 'choice', 'text' => 'Team size', 'options' => ['1-5', '6-20', '20+'], 'guide' => ['points' => ['1-5' => 1, '6-20' => 3, '20+' => 5], 'koOptions' => ['1-5']]],
                ['id' => 'm3', 'type' => 'number', 'text' => 'Turnaround', 'unit' => 'days', 'guide' => ['weight' => 3, 'op' => '<=', 'value' => 5]],
                ['id' => 'm4', 'type' => 'text', 'text' => 'Best campaign?', 'guide' => ['weight' => 6, 'note' => 'Named client and measurable result']],
            ]],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $q = Questionnaire::where('slug', 'marketing-agencies')->first();
    $v = $q->latestVersion;
    expect($v->version)->toBe(1)
        ->and(count($v->questions()))->toBe(13)
        ->and($v->structure_json['sections'][0]['block'])->toBe('company-integrity')
        ->and($v->guide->guide_json['q']['m2']['weight'])->toEqual(5)
        ->and(json_encode($v->structure_json))->not->toContain('weight')->not->toContain('koOptions');

    // It is now available for invitations.
    $this->actingAs($admin)->get('/invitations')->assertInertia(fn ($p) => $p->where('questionnaires', fn ($qs) => collect($qs)->pluck('id')->contains($q->id)));
});

it('validates the builder payload', function () {
    $admin = staff('admin');
    $this->actingAs($admin)->post('/questionnaires', [
        'name' => 'Bad', 'category' => 'X', 'pass_mark' => 50,
        'sections' => [['title' => 'S', 'questions' => [
            ['id' => 'a1', 'type' => 'choice', 'text' => 'Pick', 'options' => ['only']],
            ['id' => 'a1', 'type' => 'number', 'text' => 'Dup id'],
        ]]],
    ])->assertSessionHasErrors(['sections.0.questions.0.options', 'sections.0.questions.1.id', 'sections.0.questions.1.guide.value']);
});

it('exports exactly the filtered dashboard rows', function () {
    $reviewer = staff('reviewer');
    [$a, $ta] = invite('hrms-payroll', ['vendor_company' => 'Alpha Payroll', 'contact_email' => 'a@a.example']);
    [$b, $tb] = invite('it-saas', ['vendor_company' => 'Beta Cloud', 'contact_email' => 'b@b.example']);
    submitPerfect($this, $a, $ta, ['h7' => 'Not specified']);
    submitPerfect($this, $b, $tb);

    $this->actingAs($reviewer)->get('/?result=Fail')->assertInertia(fn ($p) => $p->where('rows.total', 1)->where('rows.data.0.vendor', 'Alpha Payroll'));
    $csv = $this->actingAs($reviewer)->get('/export/responses.csv?result=Fail')->streamedContent();
    expect($csv)->toContain('Alpha Payroll')->not->toContain('Beta Cloud');

    $csv = $this->actingAs($reviewer)->get('/export/responses.csv?q=beta')->streamedContent();
    expect($csv)->toContain('Beta Cloud')->not->toContain('Alpha Payroll');
    expect(AuditLog::where('action', 'export.responses')->count())->toBe(2);
});

it('neutralises spreadsheet formulas in exports', function () {
    expect(DashboardController::safe('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(DashboardController::safe('Acme'))->toBe('Acme');
});

it('signs in only verified Google accounts on allowed domains that exist as active users', function () {
    $user = staff('reviewer');
    $google = fn (string $email, bool $verified = true) => (new Laravel\Socialite\Two\User)
        ->setRaw(['email_verified' => $verified])->map(['email' => $email, 'name' => 'G User', 'avatar' => null]);

    $user->update(['active' => false]);
    Socialite::shouldReceive('driver->user')->andReturn(
        $google('someone@gmail.com'),
        $google('stranger@hexamatics.com'),
        $google($user->email, verified: false),
        $google($user->email),
        $google($user->email),
    );

    foreach (range(1, 4) as $attempt) { // wrong domain, unknown user, unverified email, inactive user
        if ($attempt === 4) {
            $user->update(['active' => false]);
        }
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    $user->update(['active' => true]);
    $this->get('/auth/google/callback')->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('sends security headers', function () {
    $r = $this->get('/login');
    expect($r->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->not->toContain('unsafe-inline')
        ->and($r->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($r->headers->get('Referrer-Policy'))->toBe('same-origin');
});

it('hides ops endpoints unless the cron secret is presented', function () {
    config(['hexaqualify.cron_secret' => str_repeat('s', 40)]);
    $this->get('/ops/cron/daily')->assertNotFound();
    $this->get('/ops/cron/daily', ['Authorization' => 'Bearer wrong'])->assertNotFound();
    $this->get('/ops/cron/daily', ['Authorization' => 'Bearer '.str_repeat('s', 40)])->assertOk()->assertJsonStructure(['hexaqualify:expire']);

    config(['hexaqualify.cron_secret' => null]);
    $this->get('/ops/cron/daily', ['Authorization' => 'Bearer '])->assertNotFound();
});

it('serves ops endpoints without touching sessions, so migrate works on an empty database', function () {
    config(['hexaqualify.cron_secret' => str_repeat('s', 40), 'session.driver' => 'database']);
    Schema::drop('sessions');
    $this->post('/ops/migrate', [], ['Authorization' => 'Bearer '.str_repeat('s', 40)])->assertOk()->assertJsonStructure(['migrate', 'seed']);
});

it('returns to the invitations page after inviting, wherever the reviewer came from', function () {
    $reviewer = staff('reviewer');
    $q = Questionnaire::where('slug', 'it-saas')->first();
    $data = ['vendor_company' => 'Acme', 'contact_name' => 'A', 'contact_email' => 'a@acme.example', 'questionnaire_id' => $q->id, 'expiry_days' => 14];

    $this->actingAs($reviewer)->from("/questionnaires/{$q->id}/edit")->post('/invitations', $data)
        ->assertRedirect('/invitations')->assertSessionHas('link');
    $this->actingAs($reviewer)->from("/questionnaires/{$q->id}/edit")->post('/invitations', $data)
        ->assertRedirect('/invitations')->assertSessionHasErrors('contact_email');
});
