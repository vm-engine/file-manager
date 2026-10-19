<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VmEngine\FileManager\Http\Controllers\AssetController;

/*
 * Always registered (even with filemanager.routes.enabled = false): embedded
 * components and the file picker load their CSS/JS/fonts from here.
 */
Route::get('/assets/{file}', AssetController::class)
    ->where('file', 'fm\.(css|js)|fm-(tinymce|jodit)\.js|pdf(\.worker)?\.min\.mjs|phosphor\.css|Phosphor\.woff2')
    ->name('assets');
