<?php

use Eloquage\Rerank\FakeScorer;
use Eloquage\Rerank\LinearFeatureScorer;
use Eloquage\Rerank\Rerank;
use Eloquage\Rerank\Scorer;

function rerank_candidates(): array
{
    return [
        [
            'id' => 'alpha',
            'text' => 'Alpha document',
            'features' => ['semantic' => 0.8, 'freshness' => 2],
            'rank_lists' => ['lexical' => 1, 'vector' => 3],
            'metadata' => ['kind' => 'guide'],
        ],
        [
            'id' => 'beta',
            'text' => 'Beta document',
            'features' => ['semantic' => 0.4],
            'rank_lists' => ['lexical' => 2, 'vector' => 1],
        ],
        [
            'id' => 'gamma',
            'text' => '',
            'features' => ['freshness' => 1],
            'rank_lists' => ['vector' => 2],
        ],
    ];
}

it('preserves the identity API and returns scored copies from RRF', function () {
    $candidates = rerank_candidates();
    $original = $candidates;
    $results = (new Rerank)->rerank('query', $candidates);

    expect((new Rerank)->name())->toBe('rerank')
        ->and(array_column($results, 'id'))->toBe(['beta', 'alpha', 'gamma'])
        ->and($results[0]['score'])->toBe(1 / 61 + 1 / 62)
        ->and($results[1]['score'])->toBe(1 / 61 + 1 / 63)
        ->and($results[2]['score'])->toBe(1 / 62)
        ->and($results[1]['metadata'])->toBe(['kind' => 'guide'])
        ->and($candidates)->toBe($original)
        ->and($results[0]['score'])->toBeFloat();
});

it('uses a configurable RRF constant and retains candidates without ranks for feature scoring', function () {
    $results = (new Rerank)->rerank(
        'query',
        [
            ['id' => 'ranked', 'text' => 'Ranked', 'rank_lists' => ['source' => 1]],
            ['id' => 'unranked', 'text' => 'Unranked', 'features' => ['signal' => 2]],
        ],
        new LinearFeatureScorer(['signal' => 3], 2),
        10,
    );

    expect($results[0]['id'])->toBe('unranked')
        ->and($results[0]['score'])->toBe(6.0)
        ->and($results[1]['score'])->toBe(2 / 11);
});

it('scores configured features, ignores extras, and blends RRF', function () {
    $results = (new Rerank)->rerank(
        'query',
        rerank_candidates(),
        new LinearFeatureScorer(
            ['semantic' => 2, 'freshness' => 0.5, 'missing' => 100],
            10,
        ),
    );

    expect($results[0]['id'])->toBe('alpha')
        ->and($results[0]['score'])->toBe(2 * 0.8 + 0.5 * 2 + 10 * (1 / 61 + 1 / 63))
        ->and(array_column($results, 'id'))->toBe(['alpha', 'beta', 'gamma']);
});

it('uses fake scores, its default, and invokes a scorer once per candidate', function () {
    $scorer = new class implements Scorer
    {
        public int $calls = 0;

        public function score(string $query, array $candidate, float $rrfScore): float
        {
            $this->calls++;

            return $candidate['id'] === 'alpha' ? 4.0 : -1.0;
        }
    };

    $results = (new Rerank)->rerank('query', rerank_candidates(), $scorer);
    $fakeResults = (new Rerank)->rerank('query', rerank_candidates(), new FakeScorer(['gamma' => 9.0], -2.0));

    expect($scorer->calls)->toBe(3)
        ->and(array_column($results, 'id'))->toBe(['alpha', 'beta', 'gamma'])
        ->and(array_column($fakeResults, 'id'))->toBe(['gamma', 'alpha', 'beta'])
        ->and($fakeResults[1]['score'])->toBe(-2.0);
});

it('preserves explicit input order for exact ties and applies topN', function () {
    $candidates = [
        ['id' => 'first', 'text' => 'First'],
        ['id' => 'second', 'text' => 'Second'],
        ['id' => 'third', 'text' => 'Third'],
    ];

    $results = (new Rerank)->rerank('query', $candidates, new FakeScorer([], 1.0), topN: 2);

    expect(array_column($results, 'id'))->toBe(['first', 'second'])
        ->and((new Rerank)->rerank('query', $candidates, topN: 0))->toBe([]);
});

it('validates configuration before returning an empty result', function () {
    $rerank = new Rerank;

    expect(fn () => $rerank->rerank('query', [], rrfK: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $rerank->rerank('query', [], topN: -1))->toThrow(InvalidArgumentException::class)
        ->and($rerank->rerank('query', []))->toBe([]);
});

it('rejects malformed candidate collections and records', function () {
    $valid = ['id' => 'id', 'text' => 'text'];
    $invalid = [
        [['id' => 'id']],
        [['text' => 'text']],
        [['id' => '', 'text' => 'text']],
        [['id' => 'id', 'text' => 'text', 'score' => 1]],
        [['id' => 'id', 'text' => 'text'], $valid],
        [['id' => 'id', 'text' => 'text', 'features' => ['x' => NAN]]],
        [['id' => 'id', 'text' => 'text', 'features' => ['x' => '1']]],
        [['id' => 'id', 'text' => 'text', 'rank_lists' => ['source' => 0]]],
        [
            ['id' => 'one', 'text' => 'one', 'rank_lists' => ['source' => 1]],
            ['id' => 'two', 'text' => 'two', 'rank_lists' => ['source' => 1]],
        ],
        [['id' => 'id', 'text' => 'text', 'features' => 'bad']],
        [['id' => 'id', 'text' => 'text', 'features' => null]],
        [['id' => 'id', 'text' => 'text', 'rank_lists' => 'bad']],
        [['id' => 'id', 'text' => 'text', 'rank_lists' => null]],
        [['id' => 'id', 'text' => 'text', 'features' => [1 => 1]]],
        [['id' => 'id', 'text' => 'text', 'rank_lists' => [1 => 1]]],
        [['id' => 'id', 'text' => 'text', 'rank_lists' => ['' => 1]]],
    ];

    foreach ($invalid as $candidates) {
        expect(fn () => (new Rerank)->rerank('query', $candidates))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => (new Rerank)->rerank('query', ['id' => 'id', 'text' => 'text']))
        ->toThrow(InvalidArgumentException::class);
});

it('validates scorer adapter configuration and rejects non-finite scorer output', function () {
    expect(fn () => new LinearFeatureScorer(['' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new LinearFeatureScorer(['feature' => NAN]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new LinearFeatureScorer([], 'bad'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FakeScorer(['' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FakeScorer(['id' => INF]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new FakeScorer([], NAN))->toThrow(InvalidArgumentException::class);

    $scorer = new class implements Scorer
    {
        public function score(string $query, array $candidate, float $rrfScore): float
        {
            return NAN;
        }
    };

    expect(fn () => (new Rerank)->rerank('query', [['id' => 'id', 'text' => 'text']], $scorer))
        ->toThrow(UnexpectedValueException::class);
});
