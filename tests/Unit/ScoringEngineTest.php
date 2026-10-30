<?php

use App\Services\Scoring\ScoringEngine;

beforeEach(function () {
    $this->engine = new ScoringEngine;
});

/** A tiny questionnaire with one of each type. */
function mini(array $guideOverrides = [], int $passMark = 75): array
{
    $structure = ['sections' => [['title' => 'S', 'questions' => [
        ['id' => 'y', 'type' => 'yesno', 'text' => 'Y?'],
        ['id' => 'c', 'type' => 'choice', 'text' => 'C?', 'options' => ['A', 'B', 'C']],
        ['id' => 'n', 'type' => 'number', 'text' => 'N?'],
        ['id' => 't', 'type' => 'text', 'text' => 'T?'],
    ]]]];
    $guide = ['passMark' => $passMark, 'q' => array_replace_recursive([
        'y' => ['weight' => 10, 'expected' => 'yes', 'ko' => false],
        'c' => ['weight' => 10, 'points' => ['A' => 10, 'B' => 5, 'C' => 0], 'koOptions' => []],
        'n' => ['weight' => 10, 'op' => '>=', 'value' => 5, 'ko' => false],
        't' => ['weight' => 10],
    ], $guideOverrides)];

    return [$structure, $guide];
}

it('fails on every knockout answered wrongly, even at 99%', function () {
    [$structure, $guide] = starter('hrms-payroll');
    $perfect = perfectAnswers($structure, $guide);
    $manual = ['c9' => 5];

    $koIds = collect($guide['q'])->filter(fn ($g) => ! empty($g['ko']) || ! empty($g['koOptions']))->keys();
    expect($koIds)->not->toBeEmpty();

    foreach ($koIds as $id) {
        $g = $guide['q'][$id];
        $answers = $perfect;
        $answers[$id] = match (true) {
            isset($g['expected']) => $g['expected'] === 'yes' ? 'no' : 'yes',
            isset($g['koOptions']) => $g['koOptions'][0],
            isset($g['op']) => $g['op'] === '<=' ? $g['value'] + 1 : $g['value'] - 1,
        };

        $r = $this->engine->score($structure, $guide, $answers, $manual);
        expect($r['result'])->toBe('Fail', "knockout $id")
            ->and($r['knockouts'])->toBe([$id])
            ->and($r['pct'])->toBeGreaterThan(90);
    }

    // A single knockout at ~99%: the full-points option is itself a knockout option.
    [$s, $gd] = mini(['c' => ['koOptions' => ['A']]], 1);
    $r = $this->engine->score($s, $gd, ['y' => 'yes', 'c' => 'A', 'n' => 9], ['t' => 9.5]);
    expect($r['pct'])->toBeGreaterThanOrEqual(98.0)->and($r['result'])->toBe('Fail');
});

it('returns Review when all closed answers are correct but a text answer is unscored', function () {
    [$structure, $guide] = starter('it-saas');
    $r = $this->engine->score($structure, $guide, perfectAnswers($structure, $guide), []);

    expect($r['result'])->toBe('Review')
        ->and($r['pending'])->toBe(['c9', 'i14'])
        ->and($r['knockouts'])->toBe([])
        ->and($r['max'])->toBe(102.0);
});

it('passes exactly at the pass mark (inclusive)', function () {
    [$s, $g] = mini([], 75);
    // 10 + 5 + 10 + 5 = 30 / 40 = 75.0%
    $r = $this->engine->score($s, $g, ['y' => 'yes', 'c' => 'B', 'n' => 5], ['t' => 5]);
    expect($r['pct'])->toBe(75.0)->and($r['result'])->toBe('Pass');

    $r = $this->engine->score($s, $g, ['y' => 'yes', 'c' => 'B', 'n' => 5], ['t' => 4.5]);
    expect($r['pct'])->toBe(73.8)->and($r['result'])->toBe('Fail');
});

it('gives 0 points and no knockout for a missing optional choice with no KO options', function () {
    [$s, $g] = mini();
    $r = $this->engine->score($s, $g, ['y' => 'yes', 'n' => 5], ['t' => 10]);
    expect($r['breakdown']['c']['points'])->toBe(0.0)
        ->and($r['knockouts'])->toBe([])
        ->and($r['max'])->toBe(40.0);

    // ...but a missing choice with KO options is a knockout.
    [$s, $g] = mini(['c' => ['koOptions' => ['C']]]);
    expect($this->engine->score($s, $g, ['y' => 'yes', 'n' => 5], ['t' => 10])['knockouts'])->toBe(['c']);
});

it('applies a <= number threshold: 72 met, 73 not met', function () {
    [$s, $g] = mini(['n' => ['op' => '<=', 'value' => 72, 'ko' => true]]);
    $base = ['y' => 'yes', 'c' => 'A'];

    $met = $this->engine->score($s, $g, $base + ['n' => 72], ['t' => 10]);
    expect($met['breakdown']['n']['points'])->toBe(10.0)->and($met['knockouts'])->toBe([]);

    $notMet = $this->engine->score($s, $g, $base + ['n' => 73], ['t' => 10]);
    expect($notMet['breakdown']['n']['points'])->toBe(0.0)
        ->and($notMet['knockouts'])->toBe(['n'])
        ->and($notMet['result'])->toBe('Fail');

    $missing = $this->engine->score($s, $g, $base + ['n' => 'abc'], ['t' => 10]);
    expect($missing['knockouts'])->toBe(['n']);
});

it('clamps manual points above max (and below zero)', function () {
    [$s, $g] = mini();
    $answers = ['y' => 'yes', 'c' => 'A', 'n' => 5];
    expect($this->engine->score($s, $g, $answers, ['t' => 50])['breakdown']['t']['points'])->toBe(10.0)
        ->and($this->engine->score($s, $g, $answers, ['t' => -3])['breakdown']['t']['points'])->toBe(0.0)
        ->and($this->engine->score($s, $g, $answers, ['t' => 50])['score'])->toBe(40.0);
});

it('reproduces the §6.2 worked example (HR & payroll)', function () {
    [$structure, $guide] = starter('hrms-payroll');
    $answers = perfectAnswers($structure, $guide);
    // Lose 20 closed points without hitting a knockout: 124 - 20 = 104.
    $answers['c3'] = 1;      // -3
    $answers['h2'] = 'no';   // -5
    $answers['h4'] = 'no';   // -5
    $answers['h16'] = 99;    // -3
    $answers['h17'] = 'no';  // -4

    $r = $this->engine->score($structure, $guide, $answers, []);
    expect($r['max'])->toBe(129.0)->and($r['score'])->toBe(104.0)->and($r['result'])->toBe('Review');

    $r = $this->engine->score($structure, $guide, $answers, ['c9' => 4]);
    expect($r['score'])->toBe(108.0)->and($r['pct'])->toBe(83.7)->and($r['result'])->toBe('Pass');

    $answers['h7'] = 'Not specified';
    expect($this->engine->score($structure, $guide, $answers, ['c9' => 4])['result'])->toBe('Fail');
});

it('treats text with weight 0 as information only', function () {
    [$s, $g] = mini(['t' => ['weight' => 0]]);
    $r = $this->engine->score($s, $g, ['y' => 'yes', 'c' => 'A', 'n' => 5], []);
    expect($r['pending'])->toBe([])->and($r['max'])->toBe(30.0)->and($r['result'])->toBe('Pass');
});

it('returns Unscored when no guide exists', function () {
    [$s] = mini();
    expect($this->engine->score($s, null, [])['result'])->toBe('Unscored');
});

it('knocks out on a "Not specified" hosting answer regardless of score', function () {
    [$structure, $guide] = starter('hrms-payroll');
    $answers = perfectAnswers($structure, $guide);
    $answers['h7'] = 'Not specified';
    $r = $this->engine->score($structure, $guide, $answers, ['c9' => 5]);
    expect($r['result'])->toBe('Fail')->and($r['knockouts'])->toBe(['h7'])->and($r['pct'])->toBeGreaterThan(95);
});
