<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use VmEngine\FileManager\Tests\Fixtures\User;
use VmEngine\FileManager\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Create a persisted test user, optionally with role names for the spatie driver.
 *
 * @param  array<int, string>  $roles
 */
function fmUser(array $roles = []): User
{
    $user = User::forceCreate([
        'name' => 'Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'secret',
    ]);

    User::$roles[$user->getKey()] = $roles;

    return $user;
}

/**
 * Pin the file manager config for a test: one 'fm/public' folder whose
 * 'admin' role gets $adminPermissions.
 *
 * @param  array<int, string>  $adminPermissions
 */
function pinFmConfig(array $adminPermissions = ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir']): void
{
    config()->set('filemanager.disk', 'public');
    config()->set('filemanager.folders', [
        ['path' => 'fm/public', 'name' => 'Public', 'permissions' => ['admin' => $adminPermissions]],
    ]);
    config()->set('filemanager.image', ['thumbnail_width' => 200, 'thumbnail_height' => 200]);
    config()->set('filemanager.upload', ['max_size_kb' => 10240, 'allowed_extensions' => ['jpg', 'png', 'pdf']]);
    config()->set('filemanager.trash', ['auto_purge_days' => 30]);
}
