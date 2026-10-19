<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;
use VmEngine\FileManager\FileManagerServiceProvider;
use VmEngine\FileManager\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use WithLaravelMigrations;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, FileManagerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }
}
