<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Authorizers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use VmEngine\FileManager\Contracts\FmAuthorizer;
use VmEngine\FileManager\Enums\FmAction;

/**
 * Delegates to the host's gates: fm.read, fm.upload, … receiving the folder path.
 */
final class GateAuthorizer implements FmAuthorizer
{
    public function allows(Authenticatable $user, string $folderPath, FmAction $action): bool
    {
        return Gate::forUser($user)->allows('fm.'.$action->value, [$folderPath]);
    }
}
