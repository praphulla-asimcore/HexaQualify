<?php

namespace App\Services\Scoring;

/**
 * Pure scoring engine (spec §6.1).
 *
 * It has no framework or database dependencies: it takes the vendor-safe
 * questionnaire structure, the hidden scoring guide, the vendor's answers and
 * any reviewer-entered manual points, and returns the result snapshot.
 */
final class ScoringEngine
{
    public const PASS = 'Pass';

    public const FAIL = 'Fail';

    public const REVIEW = 'Review';

    public const UNSCORED = 'Unscored';

    /**
     * @param  array  $structure  structure_json: ['sections' => [['questions' => [...]]]]
     * @param  array|null  $guide  guide_json: ['passMark' => 75, 'q' => [id => rule]]
     * @param  array  $answers  answers_json keyed by question id
     * @param  array  $manual  reviewer points for text (and file) questions keyed by id
     * @return array{score: float, max: float, pct: float, knockouts: list<string>, pending: list<string>, result: string, breakdown: array<string, array>}
     */
    public function score(array $structure, ?array $guide, array $answers, array $manual = []): array
    {
        if ($guide === null || ! isset($guide['q']) || ! is_array($guide['q'])) {
            return [
                'score' => 0.0, 'max' => 0.0, 'pct' => 0.0,
                'knockouts' => [], 'pending' => [], 'result' => self::UNSCORED, 'breakdown' => [],
            ];
        }

        $score = 0.0;
        $max = 0.0;
        $knockouts = [];
        $pending = [];
        $breakdown = [];

        foreach (self::questions($structure) as $q) {
            $id = (string) $q['id'];
            $g = $guide['q'][$id] ?? [];
            $a = $answers[$id] ?? null;
            $w = self::weight($q['type'] ?? '', $g);
            $pts = 0.0;
            $ko = false;
            $status = 'scored';

            switch ($q['type'] ?? null) {
                case 'yesno':
                    $met = self::isAnswered($a) && self::norm($a) === self::norm($g['expected'] ?? null);
                    $pts = $met ? $w : 0.0;
                    $ko = ! empty($g['ko']) && ! $met;
                    break;

                case 'choice':
                    $points = $g['points'] ?? [];
                    $koOptions = $g['koOptions'] ?? [];
                    $answered = self::isAnswered($a);
                    $pts = $answered && array_key_exists((string) $a, $points) ? (float) $points[(string) $a] : 0.0;
                    $ko = ($answered && in_array((string) $a, array_map('strval', $koOptions), true))
                        || (! $answered && count($koOptions) > 0);
                    break;

                case 'number':
                    $met = self::numberMet($a, $g['op'] ?? '>=', $g['value'] ?? null);
                    $pts = $met ? $w : 0.0;
                    $ko = ! empty($g['ko']) && ! $met;
                    break;

                case 'text':
                    if ($w == 0.0) {
                        $status = 'info';
                    } elseif (! isset($manual[$id]) || $manual[$id] === '' || ! is_numeric($manual[$id])) {
                        $status = 'pending';
                        $pending[] = $id;
                    } else {
                        $pts = self::clamp((float) $manual[$id], 0.0, $w);
                    }
                    break;

                case 'file':
                    // Phase 2: the reviewer marks the evidence valid or invalid.
                    $mark = $manual[$id] ?? null;
                    if ($mark === null || $mark === '') {
                        $status = 'pending';
                        $pending[] = $id;
                    } else {
                        $met = self::norm($mark) === 'valid';
                        $pts = $met ? $w : 0.0;
                        $ko = ! empty($g['ko']) && ! $met;
                    }
                    break;

                default:
                    $status = 'info';
            }

            if ($ko) {
                $knockouts[] = $id;
            }
            $score += $pts;
            $max += $w;
            $breakdown[$id] = ['points' => $pts, 'max' => $w, 'knockout' => $ko, 'status' => $status];
        }

        $pct = $max > 0 ? round($score / $max * 100, 1) : 0.0;
        $passMark = (float) ($guide['passMark'] ?? 100);

        $result = match (true) {
            count($knockouts) > 0 => self::FAIL,
            count($pending) > 0 => self::REVIEW,
            $pct >= $passMark => self::PASS,
            default => self::FAIL,
        };

        return [
            'score' => $score,
            'max' => $max,
            'pct' => $pct,
            'knockouts' => $knockouts,
            'pending' => $pending,
            'result' => $result,
            'breakdown' => $breakdown,
        ];
    }

    /** Flatten sections into an ordered list of questions. */
    public static function questions(array $structure): array
    {
        $out = [];
        foreach ($structure['sections'] ?? [] as $section) {
            foreach ($section['questions'] ?? [] as $q) {
                $out[] = $q;
            }
        }

        return $out;
    }

    /** Maximum points available for a question under a guide rule. */
    public static function weight(string $type, array $g): float
    {
        if ($type === 'choice' && ! empty($g['points'])) {
            return (float) max(array_map('floatval', $g['points']));
        }

        return (float) ($g['weight'] ?? 0);
    }

    private static function numberMet(mixed $a, string $op, mixed $value): bool
    {
        if (! is_numeric($a) || ! is_numeric($value)) {
            return false;
        }

        return $op === '<=' ? (float) $a <= (float) $value : (float) $a >= (float) $value;
    }

    private static function isAnswered(mixed $a): bool
    {
        return $a !== null && $a !== '' && $a !== [];
    }

    private static function norm(mixed $v): ?string
    {
        return is_scalar($v) ? strtolower(trim((string) $v)) : null;
    }

    private static function clamp(float $v, float $lo, float $hi): float
    {
        return max($lo, min($hi, $v));
    }
}
