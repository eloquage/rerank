<?php

namespace Eloquage\Rerank;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Primary entrypoint for eloquage/rerank.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Rerank
{
    public function name(): string
    {
        return 'rerank';
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    public function rerank(
        string $query,
        array $candidates,
        ?Scorer $scorer = null,
        int $rrfK = 60,
        ?int $topN = null,
    ): array {
        if ($rrfK <= 0) {
            throw new InvalidArgumentException('rrfK must be a positive integer.');
        }

        if ($topN !== null && $topN < 0) {
            throw new InvalidArgumentException('topN must be null or a non-negative integer.');
        }

        $validated = $this->validateCandidates($candidates);

        if ($validated === []) {
            return [];
        }

        $rrfScores = $this->fuseRanks($validated, $rrfK);
        $results = [];

        foreach ($validated as $ordinal => $candidate) {
            $rrfScore = $rrfScores[$candidate['id']];
            $score = $scorer === null
                ? $rrfScore
                : $scorer->score($query, $candidate, $rrfScore);

            if (! is_finite($score)) {
                throw new UnexpectedValueException('The scorer must return a finite score.');
            }

            $result = $candidate;
            $result['score'] = (float) $score;
            $results[] = [
                'candidate' => $result,
                'ordinal' => $ordinal,
                'score' => (float) $score,
            ];
        }

        usort($results, static function (array $left, array $right): int {
            if ($left['score'] === $right['score']) {
                return $left['ordinal'] <=> $right['ordinal'];
            }

            return $left['score'] > $right['score'] ? -1 : 1;
        });

        $ordered = array_column($results, 'candidate');

        return $topN === null ? $ordered : array_slice($ordered, 0, $topN);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function validateCandidates(array $candidates): array
    {
        if (! array_is_list($candidates)) {
            throw new InvalidArgumentException('Candidates must be a packed list of records.');
        }

        $ids = [];
        $usedRanks = [];
        $validated = [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || array_is_list($candidate)) {
                throw new InvalidArgumentException('Each candidate must be an associative array.');
            }

            if (! array_key_exists('id', $candidate) || ! is_string($candidate['id']) || $candidate['id'] === '') {
                throw new InvalidArgumentException('Candidate IDs must be non-empty strings.');
            }

            if (array_key_exists('score', $candidate)) {
                throw new InvalidArgumentException('Candidate input score is reserved.');
            }

            if (! array_key_exists('text', $candidate) || ! is_string($candidate['text'])) {
                throw new InvalidArgumentException('Candidate text must be a string.');
            }

            if (array_key_exists($candidate['id'], $ids)) {
                throw new InvalidArgumentException('Candidate IDs must be unique within a rerank call.');
            }

            $ids[$candidate['id']] = true;
            if (array_key_exists('features', $candidate)) {
                $this->validateFeatures($candidate['features']);
            }

            if (array_key_exists('rank_lists', $candidate)) {
                $this->validateRankLists($candidate['rank_lists'], $usedRanks);
            }
            $validated[] = $candidate;
        }

        return $validated;
    }

    private function validateFeatures(mixed $features): void
    {
        if (! is_array($features)) {
            throw new InvalidArgumentException('Candidate features must be a map.');
        }

        foreach ($features as $name => $value) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('Feature names must be non-empty strings.');
            }

            if (! self::isFiniteNumber($value)) {
                throw new InvalidArgumentException('Feature values must be finite integers or floats.');
            }
        }
    }

    /**
     * @param  array<string, array<int, bool>>  $usedRanks
     */
    private function validateRankLists(mixed $rankLists, array &$usedRanks): void
    {
        if (! is_array($rankLists)) {
            throw new InvalidArgumentException('Candidate rank_lists must be a map.');
        }

        foreach ($rankLists as $source => $rank) {
            if (! is_string($source) || $source === '') {
                throw new InvalidArgumentException('Rank-list source names must be non-empty strings.');
            }

            if (! is_int($rank) || $rank < 1) {
                throw new InvalidArgumentException('Rank-list ranks must be positive integers.');
            }

            if (($usedRanks[$source][$rank] ?? false) === true) {
                throw new InvalidArgumentException('A rank-list source cannot repeat a rank.');
            }

            $usedRanks[$source][$rank] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, float>
     */
    private function fuseRanks(array $candidates, int $rrfK): array
    {
        $scores = [];

        foreach ($candidates as $candidate) {
            $score = 0.0;

            foreach ($candidate['rank_lists'] ?? [] as $rank) {
                $score += 1.0 / ((float) $rrfK + (float) $rank);
            }

            $scores[$candidate['id']] = $score;
        }

        return $scores;
    }

    private static function isFiniteNumber(mixed $value): bool
    {
        return is_int($value) || (is_float($value) && is_finite($value));
    }
}
