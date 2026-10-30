<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Score;
use App\Models\ScoringGuide;
use App\Models\VendorResponse;
use App\Services\Scoring\ScoringEngine;

/** Runs the pure engine against stored data and writes a new score snapshot. */
class ScoringService
{
    public function __construct(private ScoringEngine $engine) {}

    /**
     * Score a submitted response.
     *
     * By default the guide the response is already scored with is reused (or the guide pinned to the
     * invitation's version on first scoring), so later questionnaire edits never change past results.
     * Pass $guide to rescore with a different guide (Admin "Rescore with latest guide").
     */
    public function score(
        VendorResponse $response,
        ?array $manual = null,
        ?string $by = 'system',
        ?ScoringGuide $guide = null,
        ?array $aiSuggestions = null,
    ): Score {
        $previous = $response->latestScore;
        $version = $response->invitation->version;
        $guide ??= $previous?->guide ?? $version->guide;
        $manual ??= $previous?->manual_json ?? [];

        $r = $this->engine->score($version->structure_json, $guide?->guide_json, $response->answers_json ?? [], $manual);

        $score = Score::create([
            'response_id' => $response->id,
            'guide_id' => $guide?->id,
            'score' => $r['score'],
            'max' => $r['max'],
            'pct' => $r['pct'],
            'knockouts_json' => $r['knockouts'],
            'pending_json' => $r['pending'],
            'manual_json' => $manual,
            'result' => $r['result'],
            'ai_suggestions_json' => $aiSuggestions ?? $previous?->ai_suggestions_json,
            'computed_at' => now(),
            'computed_by' => $by,
        ]);

        if ($previous === null || $previous->pct != $score->pct || $previous->result !== $score->result) {
            AuditLog::record('score.change', 'response', $response->id,
                $previous ? ['pct' => $previous->pct, 'result' => $previous->result] : null,
                ['pct' => $score->pct, 'result' => $score->result, 'guide_id' => $guide?->id],
                $by === 'system' ? 'system' : null);
        }

        $response->unsetRelation('latestScore');
        $response->invitation->advanceTo('scored');

        return $score;
    }

    /** Per-question breakdown of a stored score for staff screens. */
    public function breakdown(VendorResponse $response, Score $score): array
    {
        return $this->engine->score(
            $response->invitation->version->structure_json,
            $score->guide?->guide_json,
            $response->answers_json ?? [],
            $score->manual_json ?? [],
        )['breakdown'];
    }
}
