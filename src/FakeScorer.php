<?php

namespace Eloquage\Rerank;

use InvalidArgumentException;

final class FakeScorer implements Scorer
{
    /** @var array<string, int|float> */
    private array $scores;

    private float $defaultScore;

    /**
     * @param  array<string, int|float>  $scores
     */
    public function __construct(array $scores, mixed $defaultScore = 0.0)
    {
        foreach ($scores as $id => $score) {
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException('Fake scorer IDs must be non-empty strings.');
            }

            if (! self::isFiniteNumber($score)) {
                throw new InvalidArgumentException('Fake scorer scores must be finite integers or floats.');
            }
        }

        if (! self::isFiniteNumber($defaultScore)) {
            throw new InvalidArgumentException('defaultScore must be a finite integer or float.');
        }

        $this->scores = $scores;
        $this->defaultScore = (float) $defaultScore;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function score(string $query, array $candidate, float $rrfScore): float
    {
        return (float) ($this->scores[$candidate['id']] ?? $this->defaultScore);
    }

    private static function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
