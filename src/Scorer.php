<?php

namespace Eloquage\Rerank;

interface Scorer
{
    /**
     * @param array<string, mixed> $candidate
     */
    public function score(string $query, array $candidate, float $rrfScore): float;
}
