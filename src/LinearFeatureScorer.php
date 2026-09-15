<?php

namespace Eloquage\Rerank;

use InvalidArgumentException;

final class LinearFeatureScorer implements Scorer
{
    /** @var array<string, int|float> */
    private array $weights;

    private float $rrfWeight;

    /**
     * @param  array<string, int|float>  $weights
     */
    public function __construct(array $weights, mixed $rrfWeight = 0.0)
    {
        foreach ($weights as $name => $weight) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('Linear feature names must be non-empty strings.');
            }

            if (! self::isFiniteNumber($weight)) {
                throw new InvalidArgumentException('Linear feature weights must be finite integers or floats.');
            }
        }

        if (! self::isFiniteNumber($rrfWeight)) {
            throw new InvalidArgumentException('rrfWeight must be a finite integer or float.');
        }

        $this->weights = $weights;
        $this->rrfWeight = (float) $rrfWeight;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function score(string $query, array $candidate, float $rrfScore): float
    {
        $features = $candidate['features'] ?? [];
        $linear = 0.0;

        foreach ($this->weights as $name => $weight) {
            $value = $features[$name] ?? 0;
            $linear += (float) $weight * (float) $value;
        }

        return $linear + $this->rrfWeight * $rrfScore;
    }

    private static function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
