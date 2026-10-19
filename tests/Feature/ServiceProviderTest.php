<?php

declare(strict_types=1);

it('merges the package config', function () {
    expect(config('filemanager.disk'))->toBe('public')
        ->and(config('filemanager.authorizer'))->toBe('gate');
});
