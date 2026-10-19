<?php

declare(strict_types=1);

namespace VmEngine\FileManager;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use VmEngine\FileManager\Authorizers\GateAuthorizer;
use VmEngine\FileManager\Authorizers\SpatieAuthorizer;
use VmEngine\FileManager\Console\Commands\PurgeTrash;
use VmEngine\FileManager\Contracts\FmAuthorizer;

class FileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filemanager.php', 'filemanager');

        $this->app->singleton(FmAuthorizer::class, function (Application $app): FmAuthorizer {
            $driver = (string) config('filemanager.authorizer', 'gate');

            return match ($driver) {
                'gate' => new GateAuthorizer,
                'spatie' => new SpatieAuthorizer,
                default => $app->make($driver),
            };
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filemanager');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filemanager');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Livewire::addNamespace('filemanager', viewPath: __DIR__.'/../resources/views/livewire');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeTrash::class]);

            $this->publishes([__DIR__.'/../config/filemanager.php' => config_path('filemanager.php')], 'filemanager-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/filemanager')], 'filemanager-views');
            $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/filemanager')], 'filemanager-lang');
        }
    }

    /**
     * Assets are always registered; the page and API routes follow config.
     */
    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $webGroup = fn (string $file) => Route::prefix((string) config('filemanager.routes.prefix', 'file-manager'))
            ->name((string) config('filemanager.routes.name', 'filemanager.'))
            ->group(__DIR__.'/../routes/'.$file);

        $webGroup('assets.php');

        if (config('filemanager.routes.enabled', true)) {
            $webGroup('web.php');
        }

        if (config('filemanager.api.enabled', false)) {
            Route::prefix((string) config('filemanager.api.prefix', 'api/file-manager'))
                ->name('filemanager.api.')
                ->group(__DIR__.'/../routes/api.php');
        }
    }
}
