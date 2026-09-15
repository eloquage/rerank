# eloquage/rerank

In-process RRF and feature-scoring helpers for PHP search pipelines.

## Package boundary

- Composer package: `eloquage/rerank`
- Public entrypoint: `Eloquage\Rerank\Rerank`
- Public scorer seam: `Scorer`
- Production adapter: `LinearFeatureScorer`
- Deterministic test adapter: `FakeScorer`
- `src/` is the pure-PHP source of truth; no Illuminate or external service dependency.
- The Laravel app at the monorepo root is a local test bench only.

Candidate validation, private RRF accumulation, scorer invocation, finite-score
checks, stable ordering, and `topN` slicing belong behind `Rerank::rerank()`.
Keep the candidate/result boundary as ordinary PHP arrays and preserve the
explicit input-order tie policy.

## Workflow

From `packages/rerank`:

```bash
composer test
vendor/bin/pest --coverage --min=90
composer format
```

Add package behavior under `tests/` and exercise it through `Rerank`; keep
Laravel welcome assertions in the root `tests/Feature` suite. Do not add HTTP,
Cohere, ONNX, model, persistence, or training code for the v1 capability.

## TypePHP boundary

The package retains an optional Docker-only TypePHP extension contract
(`mode: ext`) in `project.yml.example` and `TYPEPHP.md`:

```bash
docker/typephp/build-package.sh rerank
```

Do not Composer-require `swoole/typephp`, add `libphp.so`, commit `project.yml`
or `build/` output, or treat the empty `native/` directory as a proven build.
For `rerank-fusion-and-features`, native compilation is an explicit v1 skip;
the release gates are pure-PHP Pest coverage and the root welcome Feature test.

## Documentation boundary

Keep installation and human usage examples in `README.md`. Keep this file to
actionable source, test, style, build, and package-boundary guidance.
