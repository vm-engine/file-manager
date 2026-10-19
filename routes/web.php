<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$ability = config('filemanager.routes.ability');

$middleware = (array) config('filemanager.routes.middleware', ['web', 'auth']);

if (is_string($ability) && $ability !== '') {
    $middleware[] = 'can:'.$ability;
}

Route::middleware($middleware)->group(function (): void {
    Route::livewire('/', 'filemanager::file-manager')->name('index');
});
