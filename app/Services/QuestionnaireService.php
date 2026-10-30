<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\ScoringGuide;
use App\Models\SectionBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds immutable questionnaire versions from the editor payload, splitting
 * each question into a vendor-safe part (structure_json) and the hidden guide.
 */
class QuestionnaireService
{
    public const TYPES = ['yesno', 'choice', 'number', 'text'];

    /** Keys a question may expose to vendors. Everything else is dropped. */
    private const VENDOR_KEYS = ['id', 'text', 'type', 'help', 'required', 'unit', 'options'];

    /**
     * Create a questionnaire or a new version of one.
     *
     * @param  array  $data  {slug?, name, category, description, active, pass_mark, sections: [{title, block?, questions: [{..., guide: {...}}]}]}
     */
    public function save(array $data, User $by, ?Questionnaire $questionnaire = null): QuestionnaireVersion
    {
        [$structure, $guide] = $this->split($data['sections'] ?? [], (float) $data['pass_mark']);

        return DB::transaction(function () use ($data, $by, $questionnaire, $structure, $guide) {
            $before = null;
            if ($questionnaire === null) {
                $questionnaire = Questionnaire::create([
                    'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'active' => $data['active'] ?? true,
                    'sort_order' => (int) Questionnaire::withTrashed()->max('sort_order') + 1,
                    'created_by' => $by->id,
                ]);
            } else {
                $before = ['version' => $questionnaire->latestVersion?->version];
                $questionnaire->update([
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'active' => $data['active'] ?? $questionnaire->active,
                ]);
            }

            $next = (int) QuestionnaireVersion::where('questionnaire_id', $questionnaire->id)->max('version') + 1;
            $version = QuestionnaireVersion::create([
                'questionnaire_id' => $questionnaire->id,
                'version' => $next,
                'pass_mark' => $guide['passMark'],
                'structure_json' => $structure,
                'created_by' => $by->id,
            ]);
            ScoringGuide::create(['questionnaire_version_id' => $version->id, 'guide_json' => $guide + ['version' => $next]]);

            AuditLog::record($before ? 'guide.edit' : 'questionnaire.create', 'questionnaire', $questionnaire->id,
                $before, ['version' => $next, 'pass_mark' => $guide['passMark']]);

            return $version;
        });
    }

    /** Seed a questionnaire version directly from Appendix B style structure + guide JSON. */
    public function import(string $slug, array $form, array $guide, int $order, ?User $by = null): QuestionnaireVersion
    {
        return DB::transaction(function () use ($slug, $form, $guide, $order, $by) {
            $q = Questionnaire::create([
                'slug' => $slug, 'name' => $form['name'], 'category' => $form['category'],
                'description' => $form['description'] ?? null, 'active' => true, 'sort_order' => $order,
                'created_by' => $by?->id,
            ]);
            $sections = array_map(fn ($s) => [
                'title' => $s['title'],
                'block' => $s['title'] === 'Company and integrity' ? SectionBlock::BASELINE : null,
                'questions' => array_map(fn ($q) => $q + ['guide' => $guide['q'][$q['id']] ?? []], $s['questions']),
            ], $form['sections']);
            [$structure, $g] = $this->split($sections, (float) $guide['passMark']);

            $version = QuestionnaireVersion::create([
                'questionnaire_id' => $q->id, 'version' => 1, 'pass_mark' => $g['passMark'],
                'structure_json' => $structure, 'created_by' => $by?->id,
            ]);
            ScoringGuide::create(['questionnaire_version_id' => $version->id, 'guide_json' => $g + ['version' => 1]]);

            return $version;
        });
    }

    /** Duplicate a questionnaire's latest version as a new, inactive questionnaire. */
    public function duplicate(Questionnaire $source, User $by): QuestionnaireVersion
    {
        $editor = $this->toEditor($source->latestVersion);

        return $this->save([
            'name' => $source->name.' (copy)',
            'category' => $source->category,
            'description' => $source->description,
            'active' => false,
            'pass_mark' => $editor['pass_mark'],
            'sections' => $editor['sections'],
        ], $by);
    }

    /** Merge a version's structure and guide back into the editor shape (staff only). */
    public function toEditor(QuestionnaireVersion $version): array
    {
        $guide = $version->guide?->guide_json ?? ['passMark' => $version->pass_mark, 'q' => []];

        return [
            'pass_mark' => $guide['passMark'],
            'sections' => array_map(fn ($s) => [
                'title' => $s['title'],
                'block' => $s['block'] ?? null,
                'questions' => array_map(fn ($q) => $q + ['guide' => $guide['q'][$q['id']] ?? []], $s['questions']),
            ], $version->structure_json['sections']),
        ];
    }

    /** Vendor-safe questions and guide for a reusable section block. */
    public function blockSection(SectionBlock $block): array
    {
        return [
            'title' => $block->title,
            'block' => $block->slug,
            'questions' => array_map(fn ($q) => $q + ['guide' => $block->guide_json[$q['id']] ?? []], $block->questions_json),
        ];
    }

    /**
     * Validate editor sections and split into [structure_json, guide_json].
     * Sections that reference a block are re-resolved from the current block so the baseline stays consistent.
     */
    public function split(array $sections, float $passMark): array
    {
        $errors = [];
        if ($passMark < 0 || $passMark > 100) {
            $errors['pass_mark'] = 'Pass mark must be between 0 and 100.';
        }
        if ($sections === []) {
            $errors['sections'] = 'Add at least one section.';
        }

        // validated() can return nested arrays out of order; the numeric keys hold the editor order.
        ksort($sections);
        $sections = array_values($sections);
        $blocks = SectionBlock::query()->get()->keyBy('slug');
        $structure = ['sections' => []];
        $guide = ['passMark' => $passMark, 'q' => []];
        $seen = [];

        foreach ($sections as $si => $section) {
            if (! empty($section['block']) && $blocks->has($section['block'])) {
                $section = $this->blockSection($blocks[$section['block']]);
            }
            $title = trim((string) ($section['title'] ?? ''));
            if ($title === '') {
                $errors["sections.$si.title"] = 'Section title is required.';
            }
            $out = ['title' => $title, 'questions' => []];
            if (! empty($section['block'])) {
                $out['block'] = $section['block'];
            }
            if (empty($section['questions'])) {
                $errors["sections.$si.questions"] = 'Add at least one question.';
            }

            $questions = $section['questions'] ?? [];
            ksort($questions);
            foreach (array_values($questions) as $qi => $q) {
                $path = "sections.$si.questions.$qi";
                $id = trim((string) ($q['id'] ?? ''));
                $type = $q['type'] ?? '';
                if (! preg_match('/^[a-z][a-z0-9_]{0,31}$/', $id)) {
                    $errors["$path.id"] = 'Use a short lowercase id, e.g. "h12".';
                } elseif (isset($seen[$id])) {
                    $errors["$path.id"] = "Duplicate question id \"$id\".";
                }
                $seen[$id] = true;
                if (! in_array($type, self::TYPES, true)) {
                    $errors["$path.type"] = 'Unknown question type.';
                }
                if (trim((string) ($q['text'] ?? '')) === '') {
                    $errors["$path.text"] = 'Question text is required.';
                }

                $vendor = array_intersect_key($q, array_flip(self::VENDOR_KEYS));
                $vendor['id'] = $id;
                $vendor['text'] = trim((string) ($q['text'] ?? ''));
                $vendor['help'] = trim((string) ($q['help'] ?? ''));
                $vendor['required'] = (bool) ($q['required'] ?? true);
                if ($type === 'choice') {
                    $vendor['options'] = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $q['options'] ?? []), 'strlen'));
                    if (count($vendor['options']) < 2) {
                        $errors["$path.options"] = 'A choice question needs at least two options.';
                    }
                } else {
                    unset($vendor['options']);
                }
                if ($type === 'number') {
                    $vendor['unit'] = trim((string) ($q['unit'] ?? ''));
                } else {
                    unset($vendor['unit']);
                }

                [$rule, $ruleErrors] = $this->rule($type, $q['guide'] ?? [], $vendor['options'] ?? []);
                foreach ($ruleErrors as $k => $msg) {
                    $errors["$path.guide.$k"] = $msg;
                }

                $out['questions'][] = $vendor;
                $guide['q'][$id] = $rule;
            }
            $structure['sections'][] = $out;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$structure, $guide];
    }

    /** Normalise a guide rule for one question. */
    private function rule(string $type, array $g, array $options): array
    {
        $errors = [];
        $note = trim((string) ($g['note'] ?? ''));
        $weight = (float) ($g['weight'] ?? 0);
        if ($weight < 0) {
            $errors['weight'] = 'Points cannot be negative.';
        }

        switch ($type) {
            case 'yesno':
                $expected = in_array($g['expected'] ?? null, ['yes', 'no'], true) ? $g['expected'] : 'yes';

                return [['weight' => $weight, 'expected' => $expected, 'ko' => (bool) ($g['ko'] ?? false), 'note' => $note], $errors];

            case 'choice':
                $points = [];
                foreach ($options as $o) {
                    $points[$o] = (float) ($g['points'][$o] ?? 0);
                }
                $koOptions = array_values(array_intersect($options, $g['koOptions'] ?? []));
                $w = $points ? max($points) : 0;

                return [['weight' => $w, 'points' => $points, 'koOptions' => $koOptions, 'note' => $note], $errors];

            case 'number':
                if (! is_numeric($g['value'] ?? null)) {
                    $errors['value'] = 'Threshold value is required.';
                }

                return [[
                    'weight' => $weight,
                    'op' => ($g['op'] ?? '>=') === '<=' ? '<=' : '>=',
                    'value' => (float) ($g['value'] ?? 0),
                    'ko' => (bool) ($g['ko'] ?? false),
                    'note' => $note,
                ], $errors];

            default: // text
                return [['weight' => $weight, 'note' => $note], $errors];
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'questionnaire';
        $slug = $base;
        for ($i = 2; Questionnaire::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }
}
