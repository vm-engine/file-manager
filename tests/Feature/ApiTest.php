<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use VmEngine\FileManager\FileManagerServiceProvider;
use VmEngine\FileManager\Models\FmFile;

beforeEach(function () {
    Storage::fake('public');
    pinFmConfig();

    // The provider registers these only when filemanager.api.enabled is true at
    // boot; register the same route file here, with `auth` standing in for
    // sanctum (not a dev dependency).
    config()->set('filemanager.api.middleware', ['api', 'auth']);
    Route::prefix('api/file-manager')->name('filemanager.api.')
        ->group(dirname(__DIR__, 2).'/routes/api.php');
});

it('uploads through the api when allowed', function () {
    Gate::before(fn (): bool => true);

    $this->actingAs(fmUser())
        ->postJson('/api/file-manager/upload', ['file' => UploadedFile::fake()->image('a.jpg'), 'folder_path' => 'fm/public'])
        ->assertOk()
        ->assertJsonStructure(['id', 'filename', 'url', 'thumbnail_url', 'size', 'human_size', 'mime_type']);

    expect(FmFile::where('filename', 'a.jpg')->exists())->toBeTrue();
});

it('rejects api uploads without upload permission', function () {
    $this->actingAs(fmUser())
        ->postJson('/api/file-manager/upload', ['file' => UploadedFile::fake()->image('a.jpg'), 'folder_path' => 'fm/public'])
        ->assertForbidden();
});

it('rejects api requests from guests', function () {
    $this->postJson('/api/file-manager/upload', ['folder_path' => 'fm/public'])->assertUnauthorized();
});

it('streams a file to users who can read its folder', function () {
    Gate::before(fn (): bool => true);
    Storage::disk('public')->put('fm/public/a.txt', 'hello');
    $file = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'a.txt', 'filename' => 'a.txt',
        'original_name' => 'a.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'size' => 5,
    ]);

    $response = $this->actingAs(fmUser())->get('/api/file-manager/stream/'.$file->id);

    $response->assertOk();
    expect($response->streamedContent())->toBe('hello');
});

it('refuses to stream without read permission', function () {
    $file = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'a.txt', 'filename' => 'a.txt',
        'original_name' => 'a.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'size' => 5,
    ]);

    $this->actingAs(fmUser())->get('/api/file-manager/stream/'.$file->id)->assertForbidden();
});

it('lets the provider register the api routes when api.enabled is true', function () {
    config()->set('filemanager.api.enabled', true);
    config()->set('filemanager.api.prefix', 'api/fm-enabled');

    $provider = app()->getProvider(FileManagerServiceProvider::class);
    (fn () => $this->registerRoutes())->call($provider);
    app('router')->getRoutes()->refreshNameLookups();

    expect(app('router')->getRoutes()->match(request()->create('/api/fm-enabled/upload', 'POST'))->getName())
        ->toBe('filemanager.api.upload');
});

it('streams uploads sandboxed so svg or html files cannot run script on the app origin', function () {
    Gate::before(fn (): bool => true);
    Storage::disk('public')->put('fm/public/evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $file = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'evil.svg', 'filename' => 'evil.svg',
        'original_name' => 'evil.svg', 'extension' => 'svg', 'mime_type' => 'image/svg+xml', 'size' => 90,
    ]);

    $this->actingAs(fmUser())->get('/api/file-manager/stream/'.$file->id)
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'sandbox')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
