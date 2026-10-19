<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use VmEngine\FileManager\Enums\FmAction;

interface FmAuthorizer
{
    /**
     * Whether $user may perform $action inside the configured root folder $folderPath.
     * Only called for folders present in config('filemanager.folders').
     */
    public function allows(Authenticatable $user, string $folderPath, FmAction $action): bool;
}
