<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after every user-initiated file manager write. The package registers
 * no listener: hosts log it however they like.
 */
final class FileManagerActivity
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $userId,
        public readonly string $action,
        public readonly string $description,
        public readonly string $feature,
    ) {}
}
