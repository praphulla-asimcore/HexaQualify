<?php

use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\User;
use App\Models\VendorResponse;
use App\Services\QuestionnaireService;
use App\Services\Scoring\ScoringEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/** Load one of the Appendix B starter questionnaires: [structure, guide]. */
function starter(string $slug): array
{
    static $data;
    $data ??= json_decode(file_get_contents(__DIR__.'/../database/seeders/data/starter-questionnaires.json'), true);

    return [$data['forms'][$slug], $data['guides'][$slug]];
}

/** Answers that satisfy every closed question of a starter guide (best option / expected value). */
function perfectAnswers(array $structure, array $guide): array
{
    $answers = [];
    foreach (ScoringEngine::questions($structure) as $q) {
        $g = $guide['q'][$q['id']];
        $answers[$q['id']] = match ($q['type']) {
            'yesno' => $g['expected'],
            'choice' => array_search(max($g['points']), $g['points'], true),
            'number' => $g['value'],
            'text' => 'A specific, detailed answer.',
        };
    }

    return $answers;
}

/** Seed starter questionnaires and return a staff user with the given role. */
function staff(string $role = 'reviewer'): User
{
    if (! Questionnaire::exists()) {
        (new DatabaseSeeder)->run(app(QuestionnaireService::class));
    }

    return User::create(['email' => "$role-".Str::random(6).'@hexamatics.com', 'name' => ucfirst($role), 'role' => $role, 'active' => true]);
}

/** Create an invitation directly and return [invitation, plain token]. */
function invite(string $slug = 'hrms-payroll', array $attrs = []): array
{
    $creator = staff('reviewer');
    $q = Questionnaire::where('slug', $slug)->firstOrFail();
    [$plain, $hash] = Invitation::newToken();
    $inv = Invitation::create($attrs + [
        'questionnaire_version_id' => $q->latestVersion->id, 'vendor_company' => 'Acme Pvt. Ltd.', 'contact_name' => 'Ram',
        'contact_email' => 'ram@acme.example', 'token_hash' => $hash, 'status' => 'sent', 'expires_at' => now()->addDays(14),
        'created_by' => $creator->id,
    ]);

    return [$inv, $plain];
}

/** Mark the vendor session as OTP-verified for an invitation. */
function asVendor(TestCase $test, Invitation $inv): TestCase
{
    return $test->withSession(['vendor_invitations.'.$inv->id => time()]);
}

function vendorDetails(): array
{
    return ['company' => 'Acme Pvt. Ltd.', 'pan' => '601234567', 'contact_person' => 'Ram', 'designation' => 'CEO', 'email' => 'ram@acme.example', 'phone' => '+977 9800000000'];
}

/** Submit perfect answers for an invitation through the real endpoint and return the response model. */
function submitPerfect(TestCase $test, Invitation $inv, string $token, array $override = []): VendorResponse
{
    [$structure, $guide] = starter($inv->version->questionnaire->slug);
    asVendor($test, $inv)->post("/v/$token/submit", [
        'vendor' => vendorDetails(), 'answers' => array_replace(perfectAnswers($structure, $guide), $override), 'declaration' => true,
    ])->assertRedirect("/v/$token/done");

    return $inv->fresh()->response;
}
