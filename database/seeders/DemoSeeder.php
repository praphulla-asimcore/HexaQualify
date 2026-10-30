<?php

namespace Database\Seeders;

use App\Models\Decision;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\User;
use App\Models\VendorResponse;
use App\Services\Scoring\ScoringEngine;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;

/** Local demo data: staff in each role and a handful of vendor responses. Never run in production. */
class DemoSeeder extends Seeder
{
    public function run(ScoringService $scoring): void
    {
        abort_if(app()->isProduction(), 500, 'DemoSeeder must not run in production.');
        $this->call(DatabaseSeeder::class);

        $admin = User::firstOrCreate(['email' => 'admin@hexamatics.com'], ['name' => 'Asha Admin', 'role' => 'admin']);
        $reviewer = User::firstOrCreate(['email' => 'reviewer@hexamatics.com'], ['name' => 'Rabin Reviewer', 'role' => 'reviewer']);
        User::firstOrCreate(['email' => 'viewer@hexamatics.com'], ['name' => 'Vina Viewer', 'role' => 'viewer']);

        $vendors = [
            ['hrms-payroll', 'Himal Payroll Pvt. Ltd.', 'Sita Sharma', 'perfect', 4],
            ['hrms-payroll', 'Kathmandu HR Cloud', 'Bikash Thapa', 'ko', null],
            ['it-saas', 'Everest Cloud Services', 'Anil Gurung', 'pending', null],
            ['professional', 'Annapurna Advisory LLP', 'Maya Rai', 'weak', 2],
            ['facilities', 'Valley Office Supplies', 'Ramesh KC', 'perfect', 3],
        ];
        $guides = json_decode(file_get_contents(__DIR__.'/data/starter-questionnaires.json'), true)['guides'];

        foreach ($vendors as $i => [$slug, $company, $contact, $profile, $manual]) {
            $q = Questionnaire::where('slug', $slug)->with('latestVersion')->first();
            $email = 'vendor'.($i + 1).'@example.com';
            if (Invitation::where('contact_email', $email)->exists()) {
                continue;
            }
            [, $hash] = Invitation::newToken();
            $inv = Invitation::create([
                'questionnaire_version_id' => $q->latestVersion->id, 'vendor_company' => $company, 'contact_name' => $contact,
                'contact_email' => $email, 'token_hash' => $hash, 'status' => 'submitted', 'expires_at' => now()->addDays(10),
                'sent_at' => now()->subDays(5 - $i), 'opened_at' => now()->subDays(4 - $i / 2), 'created_by' => $reviewer->id,
            ]);
            $answers = [];
            foreach (ScoringEngine::questions($q->latestVersion->structure_json) as $qq) {
                $g = $guides[$slug]['q'][$qq['id']];
                $answers[$qq['id']] = match ($qq['type']) {
                    'yesno' => $g['expected'],
                    'choice' => array_search(max($g['points']), $g['points'], true),
                    'number' => $g['value'],
                    'text' => 'We serve multi-entity groups across Nepal and Malaysia, with payroll for 40+ clients and named integrations with Zoho Books and Google Workspace.',
                };
            }
            if ($profile === 'ko') {
                $answers['h7'] = 'Not specified';
            }
            if ($profile === 'weak') {
                $answers['p2'] = 'no';
                $answers['p6'] = 'no';
                $answers['p9'] = 'Uncapped time-and-materials';
                $answers['c4'] = 'None';
                $answers['c3'] = 1;
                $answers['p10'] = 5;
            }
            $response = VendorResponse::create([
                'invitation_id' => $inv->id,
                'vendor_json' => ['company' => $company, 'pan' => (string) (600000000 + $i * 1111), 'contact_person' => $contact,
                    'designation' => 'Director', 'email' => $email, 'phone' => '+977 98'.str_pad((string) ($i * 13579), 8, '0')],
                'answers_json' => $answers, 'declaration_at' => now()->subDays(3 - $i / 2), 'submitted_at' => now()->subDays(3 - $i / 2),
                'submitted_ip' => '203.0.113.'.($i + 10), 'user_agent' => 'Demo',
            ]);
            $scoring->score($response->load('invitation.version.guide'));
            if ($manual !== null) {
                $pending = $response->fresh()->latestScore->pending_json;
                $scoring->score($response->fresh(), array_fill_keys($pending, $manual), $reviewer->id);
            }
            if ($profile === 'perfect' && $i === 0) {
                Decision::create(['response_id' => $response->id, 'final_result' => 'Pass', 'override' => false,
                    'no_conflict_confirmed' => true, 'decided_by' => $reviewer->id, 'decided_at' => now()->subDay()]);
                $inv->update(['status' => 'decided']);
            }
        }

        // A few open invitations.
        foreach ([['it-saas', 'Gandaki Data Centre', 'sent'], ['facilities', 'Bagmati Catering', 'in_progress']] as $j => [$slug, $company, $status]) {
            if (Invitation::where('vendor_company', $company)->exists()) {
                continue;
            }
            [, $hash] = Invitation::newToken();
            Invitation::create([
                'questionnaire_version_id' => Questionnaire::where('slug', $slug)->first()->latestVersion->id,
                'vendor_company' => $company, 'contact_name' => 'Contact '.($j + 1), 'contact_email' => 'open'.($j + 1).'@example.com',
                'token_hash' => $hash, 'status' => $status, 'expires_at' => now()->addDays(2 + $j * 7), 'sent_at' => now()->subDay(),
                'created_by' => $admin->id,
            ]);
        }
    }
}
