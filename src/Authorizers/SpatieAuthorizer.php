<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Authorizers;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Contracts\FmAuthorizer;
use VmEngine\FileManager\Enums\FmAction;

/**
 * Reads folders[].permissions (role => actions) and checks roles with spatie's hasRole().
 */
final class SpatieAuthorizer implements FmAuthorizer
{
    public function allows(Authenticatable $user, string $folderPath, FmAction $action): bool
    {
        if (! method_exists($user, 'hasRole')) {
            throw new LogicException('The spatie file manager authorizer needs a user model using Spatie\Permission\Traits\HasRoles.');
        }

        /** @var array<string, array<int, string>> $permissions */
        $permissions = FmConfig::getFolderByPath($folderPath)['permissions'] ?? [];

        foreach ($permissions as $role => $actions) {
            if (in_array($action->value, $actions, true) && $user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
