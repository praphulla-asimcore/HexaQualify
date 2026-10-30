<?php

use App\Models\AuditLog;
use App\Models\Questionnaire;
use App\Services\QuestionnaireService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('rejects an override without a reason with HTTP 422 (§6.3 #8)', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token);

    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/decision", ['override' => true, 'final_result' => 'Pass', 'reason' => '', 'no_conflict' => true])
        ->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/decision", ['override' => true, 'final_result' => 'Pass', 'reason' => '   ', 'no_conflict' => true])
        ->assertStatus(422);
    expect($response->decisions()->count())->toBe(0);

    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/decision", ['override' => true, 'final_result' => 'Pass', 'reason' => 'Verified references by phone', 'no_conflict' => true])
        ->assertRedirect();
    expect($response->fresh()->finalResult())->toBe('Pass')
        ->and(AuditLog::where(['action' => 'decision.override', 'entity_id' => $response->id])->first()->after_json['reason'])->toBe('Verified references by phone')
        ->and($inv->fresh()->status)->toBe('decided');
});

it('requires the conflict-of-interest confirmation and scored answers for a calculated decision', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token); // c9 pending → Review

    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/decision", ['override' => false, 'no_conflict' => false])
        ->assertStatus(422)->assertJsonValidationErrors('no_conflict');
    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/decision", ['override' => false, 'no_conflict' => true])
        ->assertStatus(422)->assertJsonValidationErrors('override');
});

it('saves manual text scores, clamps to half points and moves Review to Pass', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token);
    expect($response->latestScore->result)->toBe('Review');

    $this->actingAs($reviewer)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 7]])->assertSessionHasErrors('manual.c9');
    $this->actingAs($reviewer)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 2.3]])->assertSessionHasErrors('manual.c9');
    $this->actingAs($reviewer)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 4.5]])->assertSessionHasNoErrors();

    $score = $response->fresh()->latestScore;
    expect($score->result)->toBe('Pass')->and($score->manual_json)->toBe(['c9' => 4.5])->and($score->score)->toBe(128.5)
        ->and($response->scores()->count())->toBe(2); // history kept
});

it('does not change a stored score when the guide changes, until an admin rescores (§6.3 #6)', function () {
    $admin = staff('admin');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token, ['c3' => 1]); // c3 fails ">= 2 years": -3
    $this->actingAs($admin)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 5]]);
    $before = $response->fresh()->latestScore;
    expect($before->score)->toBe(126.0);

    // Admin edits the questionnaire: c3 now needs >= 1 year and pass mark rises.
    $q = Questionnaire::where('slug', 'hrms-payroll')->first();
    $service = app(QuestionnaireService::class);
    $editor = $service->toEditor($q->latestVersion);
    $editor['sections'][0]['block'] = null; // edit the baseline locally for this test
    $editor['sections'][0]['questions'][2]['guide']['value'] = 1;
    $this->actingAs($admin)->put("/questionnaires/{$q->id}", [
        'name' => $q->name, 'category' => $q->category, 'description' => $q->description, 'active' => true,
        'pass_mark' => 99, 'sections' => $editor['sections'],
    ])->assertSessionHasNoErrors();
    expect($q->fresh()->latestVersion->version)->toBe(2)
        ->and($inv->fresh()->version->version)->toBe(1);

    // Viewing and reviewer saves keep the pinned guide.
    $this->actingAs($admin)->get("/responses/{$response->id}")->assertOk();
    $this->actingAs($admin)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 5]]);
    $after = $response->fresh()->latestScore;
    expect($after->score)->toBe(126.0)->and($after->guide_id)->toBe($before->guide_id)->and($after->result)->toBe('Pass');

    // Explicit rescore applies the latest guide and is logged.
    $this->actingAs($admin)->post("/responses/{$response->id}/rescore")->assertSessionHasNoErrors();
    $rescored = $response->fresh()->latestScore;
    expect($rescored->score)->toBe(129.0)->and($rescored->guide_id)->not->toBe($before->guide_id)
        ->and($rescored->result)->toBe('Pass')
        ->and(AuditLog::where(['action' => 'score.rescore', 'entity_id' => $response->id])->exists())->toBeTrue();
});

it('only lets admins rescore', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token);
    $this->actingAs($reviewer)->post("/responses/{$response->id}/rescore")->assertForbidden();
});

it('fails on a knockout regardless of score and shows it to staff', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite();
    $response = submitPerfect($this, $inv, $token, ['h7' => 'Not specified']);
    $this->actingAs($reviewer)->post("/responses/{$response->id}/manual-scores", ['manual' => ['c9' => 5]]);

    $score = $response->fresh()->latestScore;
    expect($score->result)->toBe('Fail')->and($score->knockouts_json)->toBe(['h7'])->and($score->pct)->toBeGreaterThan(95);
    $this->actingAs($reviewer)->get("/responses/{$response->id}")->assertInertia(fn ($p) => $p
        ->component('Responses/Show')->where('final_result', 'Fail')->where('score.knockouts', ['h7'])->has('guide.h7'));
});

it('fills AI suggestions without saving points or sending contact details', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite('it-saas');
    $response = submitPerfect($this, $inv, $token);

    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['scores' => [
        ['id' => 'c9', 'points' => 9, 'reason' => 'Specific multi-entity outcomes.'],
        ['id' => 'i14', 'points' => 2.3, 'reason' => 'Names Zoho Books API but no plan.'],
        ['id' => 'bogus', 'points' => 3, 'reason' => 'x'],
    ]])]]])]);

    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/ai-suggest")->assertOk()
        ->assertJson(['suggestions' => [['id' => 'c9', 'points' => 5], ['id' => 'i14', 'points' => 2.5]]])
        ->assertJsonCount(2, 'suggestions');

    Http::assertSent(function ($req) {
        $body = $req->body();
        expect($body)->not->toContain('601234567')->not->toContain('ram@acme.example')->not->toContain('+977')
            ->and($req['model'])->toBe('claude-sonnet-5-5')
            ->and($req->header('x-api-key')[0])->toBe('test-key');

        return true;
    });

    $score = $response->fresh()->latestScore;
    expect($score->result)->toBe('Review')->and($score->manual_json)->toBe([])->and($score->ai_suggestions_json)->toHaveCount(2);
});

it('returns a friendly error when the AI call fails', function () {
    $reviewer = staff('reviewer');
    [$inv, $token] = invite('it-saas');
    $response = submitPerfect($this, $inv, $token);
    Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);
    $this->actingAs($reviewer)->postJson("/responses/{$response->id}/ai-suggest")->assertStatus(502)->assertJsonStructure(['message']);
});

it('keeps the audit log append-only', function () {
    $log = AuditLog::record('test', 'x');
    expect(fn () => $log->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::table('audit_log')->where('id', $log->id)->update(['action' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_log')->where('id', $log->id)->delete())->toThrow(QueryException::class);
})->skip(fn () => config('database.default') !== 'pgsql', 'Database trigger is Postgres-only');
