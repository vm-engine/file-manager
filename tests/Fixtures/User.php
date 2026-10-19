<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test user. hasRole() mimics spatie's HasRoles for the SpatieAuthorizer
 * without pulling spatie into dev dependencies.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    /** @var array<int|string, array<int, string>> user id => role names */
    public static array $roles = [];

    public function hasRole(string $role): bool
    {
        return in_array($role, self::$roles[$this->getKey()] ?? [], true);
    }
}
