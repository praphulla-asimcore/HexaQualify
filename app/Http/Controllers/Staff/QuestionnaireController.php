<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\SectionBlock;
use App\Services\QuestionnaireService;
use App\Services\Scoring\ScoringEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QuestionnaireController extends Controller
{
    public function __construct(private QuestionnaireService $service) {}

    public function index(Request $request)
    {
        $canSeeGuide = $request->user()->canReview();
        $items = Questionnaire::withTrashed()->with(['latestVersion.guide'])->withCount('versions')->orderBy('sort_order')->get()
            ->map(function (Questionnaire $q) use ($canSeeGuide) {
                $v = $q->latestVersion;
                $guide = $v?->guide?->guide_json['q'] ?? [];
                $questions = $v ? $v->questions() : [];

                return [
                    'id' => $q->id, 'slug' => $q->slug, 'name' => $q->name, 'category' => $q->category,
                    'description' => $q->description, 'active' => $q->active, 'archived' => $q->trashed(),
                    'version' => $v?->version, 'questions' => count($questions),
                    'pass_mark' => $canSeeGuide ? $v?->pass_mark : null,
                    'max_points' => $canSeeGuide ? array_sum(array_map(fn ($qq) => ScoringEngine::weight($qq['type'], $guide[$qq['id']] ?? []), $questions)) : null,
                    'knockouts' => $canSeeGuide ? collect($guide)->filter(fn ($g) => ! empty($g['ko']) || ! empty($g['koOptions']))->count() : null,
                    'updated_at' => $v?->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Questionnaires/Index', [
            'items' => $items,
            'can' => ['edit' => $request->user()->isAdmin(), 'guide' => $canSeeGuide],
        ]);
    }

    public function create()
    {
        $baseline = SectionBlock::where('slug', SectionBlock::BASELINE)->first();

        return Inertia::render('Questionnaires/Edit', [
            'questionnaire' => null,
            'editor' => [
                'name' => '', 'category' => '', 'description' => '', 'active' => true, 'pass_mark' => 70,
                'sections' => $baseline ? [$this->service->blockSection($baseline)] : [],
            ],
            'versions' => [],
            'blocks' => $this->blocks(),
        ]);
    }

    public function store(Request $request)
    {
        $version = $this->service->save($this->validated($request), $request->user());

        return redirect()->route('questionnaires.edit', $version->questionnaire_id)->with('success', 'Questionnaire published as version 1.');
    }

    public function edit(Request $request, Questionnaire $questionnaire)
    {
        $versionNo = $request->integer('version') ?: null;
        $version = $versionNo
            ? $questionnaire->versions()->where('version', $versionNo)->firstOrFail()
            : $questionnaire->latestVersion;
        $editor = $this->service->toEditor($version->load('guide'));

        return Inertia::render('Questionnaires/Edit', [
            'questionnaire' => [
                'id' => $questionnaire->id, 'slug' => $questionnaire->slug, 'version' => $version->version,
                'latest' => $questionnaire->latestVersion->version, 'archived' => $questionnaire->trashed(),
            ],
            'editor' => [
                'name' => $questionnaire->name, 'category' => $questionnaire->category,
                'description' => $questionnaire->description, 'active' => $questionnaire->active,
            ] + $editor,
            'versions' => $questionnaire->versions()->with('creator')->get()->map(fn (QuestionnaireVersion $v) => [
                'version' => $v->version, 'pass_mark' => $v->pass_mark, 'created_at' => $v->created_at->toIso8601String(),
                'created_by' => $v->creator?->name, 'questions' => count($v->questions()),
                'invitations' => $v->hasMany(Invitation::class)->count(),
            ]),
            'blocks' => $this->blocks(),
        ]);
    }

    public function update(Request $request, Questionnaire $questionnaire)
    {
        abort_if($questionnaire->trashed(), 422);
        $version = $this->service->save($this->validated($request), $request->user(), $questionnaire);

        return redirect()->route('questionnaires.edit', $questionnaire)->with('success', "Saved as version {$version->version}. Existing responses keep their original version and score.");
    }

    public function duplicate(Request $request, Questionnaire $questionnaire)
    {
        $version = $this->service->duplicate($questionnaire, $request->user());
        AuditLog::record('questionnaire.duplicate', 'questionnaire', $version->questionnaire_id, null, ['source' => $questionnaire->id]);

        return redirect()->route('questionnaires.edit', $version->questionnaire_id)->with('success', 'Duplicated. The copy is inactive until you activate it.');
    }

    public function archive(Questionnaire $questionnaire)
    {
        $questionnaire->update(['active' => false]);
        $questionnaire->delete();
        AuditLog::record('questionnaire.archive', 'questionnaire', $questionnaire->id);

        return redirect()->route('questionnaires.index')->with('success', 'Questionnaire archived.');
    }

    public function restore(string $id)
    {
        $questionnaire = Questionnaire::withTrashed()->findOrFail($id);
        $questionnaire->restore();
        AuditLog::record('questionnaire.restore', 'questionnaire', $questionnaire->id);

        return back()->with('success', 'Questionnaire restored (inactive).');
    }

    /** Preview exactly what a vendor sees (vendor-safe structure only). */
    public function preview(Questionnaire $questionnaire)
    {
        $v = $questionnaire->latestVersion;

        return Inertia::render('Vendor/Form', [
            'preview' => true,
            'token' => 'preview',
            'questionnaire' => ['name' => $questionnaire->name, 'description' => $questionnaire->description, 'structure' => $v->structure_json],
            'deadline' => now()->addDays(14)->toIso8601String(),
            'vendor' => (object) [], 'answers' => (object) [],
            'savedAt' => null,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
            'pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'sections' => ['required', 'array', 'min:1', 'max:30'],
            'sections.*.title' => ['nullable', 'string', 'max:200'],
            'sections.*.block' => ['nullable', 'string', Rule::exists('section_blocks', 'slug')],
            'sections.*.questions' => ['array', 'max:80'],
            'sections.*.questions.*.id' => ['required', 'string', 'max:32'],
            'sections.*.questions.*.type' => ['required', Rule::in(QuestionnaireService::TYPES)],
            'sections.*.questions.*.text' => ['required', 'string', 'max:1000'],
            'sections.*.questions.*.help' => ['nullable', 'string', 'max:1000'],
            'sections.*.questions.*.unit' => ['nullable', 'string', 'max:40'],
            'sections.*.questions.*.required' => ['boolean'],
            'sections.*.questions.*.options' => ['nullable', 'array', 'max:20'],
            'sections.*.questions.*.options.*' => ['nullable', 'string', 'max:300'],
            'sections.*.questions.*.guide' => ['nullable', 'array'],
        ]);
    }

    private function blocks(): array
    {
        return SectionBlock::orderBy('title')->get()->map(fn ($b) => $this->service->blockSection($b))->all();
    }
}
