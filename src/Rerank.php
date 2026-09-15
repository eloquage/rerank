<?php

namespace Eloquage\Rerank;

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
}
