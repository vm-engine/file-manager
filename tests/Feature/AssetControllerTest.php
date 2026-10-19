<?php

declare(strict_types=1);

use VmEngine\FileManager\Http\Controllers\AssetController;

it('serves the stylesheet with a long-lived cache header', function () {
    $response = $this->get(route('filemanager.assets', ['file' => 'fm.css']));

    $response->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000');
});

it('serves the script', function () {
    $this->get(route('filemanager.assets', ['file' => 'fm.js']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});

it('404s for anything outside the whitelist', function (string $file) {
    $this->get('/file-manager/assets/'.$file)->assertNotFound();
})->with(['app.css', '..%2Fcomposer.json', 'fm.css.bak']);

it('builds a cache-busted url', function () {
    expect(AssetController::url('fm.css'))->toContain('/file-manager/assets/fm.css?v=');
});

it('serves the vendored pdf.js module and worker as javascript', function (string $file) {
    $this->get(route('filemanager.assets', ['file' => $file]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
})->with(['pdf.min.mjs', 'pdf.worker.min.mjs']);

it('serves the vendored phosphor icon font and stylesheet', function () {
    $this->get(route('filemanager.assets', ['file' => 'phosphor.css']))->assertOk();
    $this->get(route('filemanager.assets', ['file' => 'Phosphor.woff2']))
        ->assertOk()
        ->assertHeader('Content-Type', 'font/woff2');
});

it('serves the editor bridges', function (string $file) {
    $this->get(route('filemanager.assets', ['file' => $file]))->assertOk();
})->with(['fm-tinymce.js', 'fm-jodit.js']);

it('keeps serving assets when the page route is disabled', function () {
    config()->set('filemanager.routes.enabled', false);

    $this->get(route('filemanager.assets', ['file' => 'fm.css']))->assertOk();
});
