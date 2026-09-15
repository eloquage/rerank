<?php

use Eloquage\Rerank\Rerank;

it('bootstraps the package entrypoint', function () {
    $instance = new Rerank();

    expect($instance->name())->toBe('rerank');
});
