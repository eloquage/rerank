# eloquage/rerank

In-process Reciprocal Rank Fusion and feature-weighted reranking helpers for
PHP search pipelines.

## Installation

```bash
composer require eloquage/rerank
```

The package is framework-agnostic and always works through its pure-PHP source.
It has no HTTP, model, ONNX, or Laravel dependency.

## Usage

`Rerank::rerank()` accepts a query and a packed list of candidate records. Each
record needs a unique non-empty string `id` and a string `text`. It may include
named numeric `features` and per-source one-based `rank_lists`:

```php
use Eloquage\Rerank\Rerank;

$results = (new Rerank)->rerank('hybrid search', [
    [
        'id' => 'doc-1',
        'text' => 'Hybrid retrieval guide',
        'features' => ['semantic' => 0.9, 'freshness' => 2],
        'rank_lists' => ['lexical' => 2, 'vector' => 1],
    ],
    [
        'id' => 'doc-2',
        'text' => 'Lexical retrieval guide',
        'rank_lists' => ['lexical' => 1],
    ],
]);
```

The returned records preserve the input fields and add one finite float
`score`. Input records are not mutated. Candidate input `score` is reserved
and rejected; malformed IDs, text, features, ranks, duplicate IDs, and
duplicate ranks within a source raise `InvalidArgumentException`.

## Reciprocal Rank Fusion

Without a scorer, each result uses:

```text
RRF(d) = Σ 1 / (k + rank(source, d))
```

The default smoothing constant is `k = 60`. Set `rrfK` to a positive integer
to use another value. A candidate missing from a source contributes nothing
for that source, and a candidate absent from every source remains eligible for
an explicitly supplied scorer.

```php
$results = (new Rerank)->rerank(
    'query',
    $candidates,
    rrfK: 20,
    topN: 10,
);
```

`topN` is optional, may be zero, and limits the final ordered list. Descending
scores use an explicit original-input ordinal as the tie breaker, so exact
ties retain caller order.

## Feature-weighted scoring

`LinearFeatureScorer` computes a named weighted sum and can explicitly blend
the private RRF component. Missing configured features contribute zero and
extra candidate features are ignored. Negative finite weights are allowed.

```php
use Eloquage\Rerank\LinearFeatureScorer;

$scorer = new LinearFeatureScorer(
    weights: ['semantic' => 0.7, 'freshness' => 0.3],
    rrfWeight: 0.25,
);

$results = (new Rerank)->rerank('query', $candidates, scorer: $scorer);
```

`FakeScorer` is a deterministic, no-I/O adapter for tests and local demos. It
maps candidate IDs to finite scores and accepts a finite `defaultScore` for
unmapped IDs.

```php
use Eloquage\Rerank\FakeScorer;

$results = (new Rerank)->rerank(
    'query',
    $candidates,
    scorer: new FakeScorer(['doc-1' => 1.0], defaultScore: 0.0),
);
```

Both adapters implement the narrow `Scorer` interface, which receives the
query, validated candidate, and private RRF score and returns the final score.
A future ONNX cross-encoder can use the same seam for query/document pairs;
v1 does not load models, call Cohere, or add an ONNX dependency.

## Testing and optional native acceleration

```bash
composer test
vendor/bin/pest --coverage --min=90
```

TypePHP is an optional maintainer build in Docker, using extension mode and
the shared builder contract documented in [TYPEPHP.md](TYPEPHP.md). Native
compilation is explicitly outside the v1 rerank capability; PHP consumers do
not need TypePHP or `swoole/typephp`.
