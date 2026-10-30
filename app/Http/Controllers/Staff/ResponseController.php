<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Mail\ResultMail;
use App\Models\AuditLog;
use App\Models\Decision;
use App\Models\User;
use App\Models\VendorResponse;
use App\Services\AiScoringService;
use App\Services\Scoring\ScoringEngine;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ResponseController extends Controller
{
    public function __construct(private ScoringService $scoring) {}

    public function show(Request $request, VendorResponse $response)
    {
        abort_unless($response->isSubmitted(), 404);
        $response->load(['invitation.version.questionnaire.latestVersion', 'invitation.creator', 'latestScore.guide.version', 'decisions.decider']);
        $canReview = $request->user()->canReview();
        $inv = $response->invitation;
        $version = $inv->version;
        $score = $response->latestScore;

        if ($canReview) {
            AuditLog::record('response.view', 'response', $response->id);
        }

        $audit = AuditLog::query()
            ->where(fn ($q) => $q->where(['entity' => 'response', 'entity_id' => $response->id])
                ->orWhere(fn ($q) => $q->where(['entity' => 'invitation', 'entity_id' => $inv->id])))
            ->orderByDesc('created_at')->limit(200)->get();
        $actors = User::whereIn('id', $audit->where('actor_type', 'user')->pluck('actor_id')->filter())->pluck('name', 'id');

        return Inertia::render('Responses/Show', [
            'response' => [
                'id' => $response->id,
                'vendor' => $response->vendor_json ?? [],
                'answers' => $response->answers_json ?? [],
                'submitted_at' => $response->submitted_at->toIso8601String(),
                'declaration_at' => $response->declaration_at?->toIso8601String(),
                'submitted_ip' => $canReview ? $response->submitted_ip : null,
                'user_agent' => $canReview ? $response->user_agent : null,
            ],
            'invitation' => [
                'id' => $inv->id, 'vendor_company' => $inv->vendor_company, 'contact_name' => $inv->contact_name,
                'contact_email' => $inv->contact_email, 'status' => $inv->effectiveStatus(),
                'note' => $canReview ? $inv->note : null, 'invited_by' => $inv->creator?->name, 'notify_result' => $inv->notify_result,
            ],
            'questionnaire' => [
                'name' => $version->questionnaire->name, 'category' => $version->questionnaire->category,
                'version' => $version->version, 'latest_version' => $version->questionnaire->latestVersion?->version,
                'structure' => $version->structure_json,
            ],
            'score' => $score ? [
                'score' => $score->score, 'max' => $score->max, 'pct' => $score->pct,
                'pass_mark' => (float) ($score->guide?->guide_json['passMark'] ?? $version->pass_mark),
                'knockouts' => $score->knockouts_json, 'pending' => $score->pending_json,
                'manual' => (object) ($score->manual_json ?? []), 'result' => $score->result,
                'ai_suggestions' => $canReview ? ($score->ai_suggestions_json ?? []) : [],
                'computed_at' => $score->computed_at->toIso8601String(),
                'guide_version' => $score->guide?->version?->version,
            ] : null,
            'guide' => $canReview && $score?->guide ? (object) $score->guide->guide_json['q'] : null,
            'breakdown' => $canReview && $score ? (object) $this->scoring->breakdown($response, $score) : null,
            'final_result' => $response->finalResult(),
            'decisions' => $response->decisions->map(fn (Decision $d) => [
                'id' => $d->id, 'final_result' => $d->final_result, 'override' => $d->override, 'reason' => $d->reason,
                'notes' => $canReview ? $d->notes : null, 'decided_by' => $d->decider?->name, 'decided_at' => $d->decided_at->toIso8601String(),
                'no_conflict_confirmed' => $d->no_conflict_confirmed,
            ]),
            'audit' => $canReview ? $audit->map(fn ($a) => [
                'id' => $a->id, 'action' => $a->action, 'actor' => $a->actor_type === 'user' ? ($actors[$a->actor_id] ?? 'Staff') : ucfirst($a->actor_type),
                'at' => $a->created_at->toIso8601String(), 'after' => $a->after_json, 'before' => $a->before_json,
            ]) : [],
            'can' => [
                'review' => $canReview,
                'rescore' => $request->user()->isAdmin(),
                'ai' => $canReview && app(AiScoringService::class)->enabled(),
            ],
        ]);
    }

    public function manualScores(Request $request, VendorResponse $response)
    {
        abort_unless($response->isSubmitted(), 404);
        $score = $response->latestScore;
        $guide = $score?->guide?->guide_json;
        abort_unless($guide, 422, 'This response has no scoring guide.');

        $manual = $score->manual_json ?? [];
        $errors = [];
        foreach ((array) $request->input('manual', []) as $id => $value) {
            $q = collect(ScoringEngine::questions($response->invitation->version->structure_json))->firstWhere('id', $id);
            $max = (float) ($guide['q'][$id]['weight'] ?? 0);
            if (! $q || $q['type'] !== 'text' || $max <= 0) {
                continue;
            }
            if ($value === null || $value === '') {
                unset($manual[$id]);

                continue;
            }
            if (! is_numeric($value) || fmod((float) $value * 2, 1.0) !== 0.0 || $value < 0 || $value > $max) {
                $errors["manual.$id"] = "Enter 0 to $max in steps of 0.5.";

                continue;
            }
            $manual[$id] = (float) $value;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $before = $score->manual_json;
        $this->scoring->score($response, $manual, $request->user()->id);
        AuditLog::record('score.manual', 'response', $response->id, ['manual' => $before], ['manual' => $manual]);

        return back()->with('success', 'Scores saved.');
    }

    public function aiSuggest(Request $request, VendorResponse $response, AiScoringService $ai)
    {
        abort_unless($response->isSubmitted(), 404);
        $score = $response->latestScore;
        abort_unless($score?->guide, 422);

        try {
            $suggestions = $ai->suggest($response, $score->guide->guide_json);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'AI suggestions are unavailable right now. Please score manually.'], 502);
        }

        // Stored for reference only. Points are not applied until a reviewer saves them.
        $score->forceFill(['ai_suggestions_json' => $suggestions])->save();
        AuditLog::record('score.ai_suggest', 'response', $response->id, null, ['suggestions' => $suggestions, 'model' => config('hexaqualify.ai.model')]);

        return response()->json(['suggestions' => $suggestions]);
    }

    public function decide(Request $request, VendorResponse $response)
    {
        abort_unless($response->isSubmitted(), 404);
        $data = $request->validate([
            'override' => ['required', 'boolean'],
            'final_result' => ['required_if:override,true', 'nullable', 'in:Pass,Fail'],
            'reason' => ['required_if:override,true', 'nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'no_conflict' => ['accepted'],
        ], [
            'reason.required_if' => 'A reason is required to override the calculated result.',
            'no_conflict.accepted' => 'Confirm you have no relationship with this vendor.',
        ]);

        $calculated = $response->latestScore?->result;
        if (! $data['override'] && ! in_array($calculated, ['Pass', 'Fail'], true)) {
            throw ValidationException::withMessages(['override' => 'Score the written answers first, or override with a reason.']);
        }
        if ($data['override'] && blank(trim((string) $data['reason']))) {
            throw ValidationException::withMessages(['reason' => 'A reason is required to override the calculated result.']);
        }

        $final = $data['override'] ? $data['final_result'] : $calculated;
        $before = $response->finalResult();

        DB::transaction(function () use ($response, $data, $final, $request, $before, $calculated) {
            $decision = Decision::create([
                'response_id' => $response->id,
                'final_result' => $final,
                'override' => (bool) $data['override'],
                'reason' => $data['override'] ? trim($data['reason']) : null,
                'notes' => $data['notes'] ?? null,
                'no_conflict_confirmed' => true,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            AuditLog::record($data['override'] ? 'decision.override' : 'decision', 'response', $response->id,
                ['final_result' => $before],
                ['final_result' => $final, 'calculated' => $calculated, 'override' => (bool) $data['override'], 'reason' => $decision->reason]);
            $response->invitation->advanceTo('decided');
        });

        $inv = $response->invitation;
        if ($inv->notify_result && $before !== $final) {
            Mail::to($inv->contact_email)->queue(new ResultMail($inv, $final === 'Pass'));
        }

        return back()->with('success', $final === 'Pass' ? 'Decision recorded: Qualified.' : 'Decision recorded: Not qualified.');
    }

    /** Admin: rescore with the questionnaire's latest guide (logged). */
    public function rescore(Request $request, VendorResponse $response)
    {
        abort_unless($response->isSubmitted(), 404);
        $latest = $response->invitation->version->questionnaire->latestVersion;
        $before = $response->latestScore;
        $score = $this->scoring->score($response, null, $request->user()->id, $latest->guide);
        AuditLog::record('score.rescore', 'response', $response->id,
            ['guide_id' => $before?->guide_id, 'pct' => $before?->pct, 'result' => $before?->result],
            ['guide_id' => $score->guide_id, 'guide_version' => $latest->version, 'pct' => $score->pct, 'result' => $score->result]);

        return back()->with('success', "Rescored with guide v{$latest->version}.");
    }
}
