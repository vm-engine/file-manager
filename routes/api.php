<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VmEngine\FileManager\Http\Controllers\FileController;

Route::middleware((array) config('filemanager.api.middleware', ['api', 'auth:sanctum']))->group(function (): void {
    Route::post('/upload', [FileController::class, 'upload'])->name('upload');
    Route::get('/stream/{id}', [FileController::class, 'stream'])->name('stream');
});
