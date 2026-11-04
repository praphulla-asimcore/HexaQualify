<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\SectionBlock;
use App\Models\User;
use App\Services\QuestionnaireService;
use Illuminate\Database\Seeder;

/** Seeds the common baseline block and the five starter questionnaires (Appendix B) as version 1. */
class DatabaseSeeder extends Seeder
{
    public function run(QuestionnaireService $service): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/data/starter-questionnaires.json'), true, flags: JSON_THROW_ON_ERROR);

        // The "Company and integrity" baseline, shared by reference.
        $first = $data['forms']['hrms-payroll']['sections'][0];
        $guide = $data['guides']['hrms-payroll']['q'];
        SectionBlock::updateOrCreate(['slug' => SectionBlock::BASELINE], [
            'title' => $first['title'],
            'questions_json' => $first['questions'],
            'guide_json' => array_intersect_key($guide, array_flip(array_column($first['questions'], 'id'))),
        ]);

        foreach ($data['forms'] as $slug => $form) {
            if (! Questionnaire::withTrashed()->where('slug', $slug)->exists()) {
                $service->import($slug, $form, $data['guides'][$slug], $form['order'] ?? 0);
            }
        }

        if ($email = config('hexaqualify.bootstrap_admin')) {
            User::firstOrCreate(['email' => strtolower($email)], ['name' => 'Administrator', 'role' => 'admin', 'active' => true]);
        }
    }
}
