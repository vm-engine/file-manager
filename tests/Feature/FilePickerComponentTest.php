<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VmEngine\FileManager\Models\FmFile;

beforeEach(function () {
    Storage::fake('public');

    pinFmConfig();

    Gate::before(fn (): bool => true);
    $this->user = fmUser();

    Storage::disk('public')->put('fm/public/report.pdf', 'x');
    $this->file = FmFile::create([
        'disk' => 'public',
        'folder_path' => 'fm/public',
        'relative_path' => 'report.pdf',
        'filename' => 'report.pdf',
        'original_name' => 'report.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size' => 1,
        'has_thumbnail' => false,
        'is_trashed' => false,
    ]);
});

it('renders the picker inside the package modal', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->assertOk()
        ->assertSeeHtml('open-modal-fm-picker-')
        ->assertDontSeeHtml('pickerOpen');
});

it('keeps the public open event contract', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['pickerKey' => 'hero'])
        ->call('openPicker', '*', 'hero')
        ->assertDispatched('fm-picker-open-hero');
});

it('keeps the public close event contract on select', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->call('selectFile', $this->file->id)
        ->assertDispatched('fm:file-selected')
        ->assertDispatched('fm-picker-close');
});

it('picks on click and selects on choose', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->call('pick', $this->file->id)
        ->assertSet('pickedId', $this->file->id)
        ->call('choose')
        ->assertDispatched('fm:file-selected')
        ->assertDispatched('fm-picker-close')
        ->assertSet('value', $this->file->getUrl());
});

it('remembers the last position per locked folder', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b');

    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['folder' => 'fm/public'])
        ->call('navigateTo', 'a/b');

    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['folder' => 'fm/public'])
        ->assertSet('subPath', 'a/b')
        ->assertSet('expanded', ['a', 'a/b']);
});

it('offers the storage switcher only when unlocked with several roots', function () {
    config()->push('filemanager.folders', ['path' => 'fm/docs', 'name' => 'Docs', 'permissions' => ['admin' => ['read']]]);

    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->assertSeeHtml('data-fm-storage-switcher');

    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['folder' => 'fm/public'])
        ->assertDontSeeHtml('data-fm-storage-switcher');
});

it('ignores storage switches when locked', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['folder' => 'fm/public'])
        ->call('switchFolder', 'fm/docs')
        ->assertSet('currentFolder', 'fm/public');
});

it('creates a folder inline in the current folder and closes the popover', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->assertSeeHtml('data-dropdown="newfolder"')
        ->set('newFolderName', 'inbox')
        ->call('createFolder')
        ->assertHasNoErrors()
        ->assertSet('newFolderName', '')
        ->assertDispatched('fm-folder-created');

    Storage::disk('public')->assertExists('fm/public/inbox');
});

it('validates the inline folder name', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->set('newFolderName', 'bad name!')
        ->call('createFolder')
        ->assertHasErrors(['newFolderName' => 'alpha_dash'])
        ->assertNotDispatched('fm-folder-created');
});

it('still filters by accept on the server', function () {
    $items = Livewire::actingAs($this->user)->test('filemanager::file-picker', ['accept' => 'image/*'])
        ->instance()->items;

    expect(array_column($items, 'name'))->not->toContain('report.pdf');
});

it('shows a not-configured state when no storage exists', function () {
    config()->set('filemanager.folders', []);
    Storage::disk('public')->makeDirectory('other-module');

    $component = Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->assertSee(__('filemanager::labels.not_configured'))
        ->assertDontSeeHtml('data-fm-item');

    expect($component->instance()->items)->toBe([]);
});

it('uses a lowercase modal name so the HTML-lowercased listener matches the dispatched event', function () {
    $component = Livewire::actingAs($this->user)->test('filemanager::file-picker');
    $modalName = $component->instance()->jsConfig['modalName'];

    expect($modalName)->toBe(strtolower($modalName));
    $component->assertSeeHtml("fmModal('".$modalName."')");
});

it('refuses to pick or select files outside the browsed storage root', function () {
    $foreign = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/other', 'relative_path' => 'secret.pdf',
        'filename' => 'secret.pdf', 'original_name' => 'secret.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);

    $component = Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->call('selectFile', $foreign->id)
        ->assertNotDispatched('fm:file-selected')
        ->assertSet('value', '')
        ->call('pick', $foreign->id);

    expect($component->instance()->pickedFile)->toBeNull();
});

it('searches subfolders in the picker', function () {
    Storage::disk('public')->put('fm/public/docs/deep/invoice.pdf', 'x');
    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'docs/deep/invoice.pdf',
        'filename' => 'invoice.pdf', 'original_name' => 'invoice.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'has_thumbnail' => false, 'is_trashed' => false,
    ]);

    $component = Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->set('search', 'invoice')
        ->assertSee(__('filemanager::labels.in_location', ['path' => 'docs/deep']));

    expect(array_column($component->instance()->items, 'name'))->toBe(['invoice.pdf']);
});

it('stores browser-rendered thumbnails only inside the browsed root', function () {
    $fake = UploadedFile::fake()->image('t.jpg', 100, 140);
    $dataUrl = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($fake->getPathname()));
    $foreign = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/other', 'relative_path' => 'secret.pdf',
        'filename' => 'secret.pdf', 'original_name' => 'secret.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);

    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->call('storeThumbnail', $foreign->id, $dataUrl)
        ->call('storeThumbnail', $this->file->id, $dataUrl);

    expect($foreign->fresh()->has_thumbnail)->toBeFalse()
        ->and($this->file->fresh()->has_thumbnail)->toBeTrue();
});

it('renders the viewer and a preview button for a previewable picked file', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker')
        ->assertSeeHtml('data-fm-viewer')
        ->assertDontSeeHtml('previewFrom($event)')
        ->call('pick', $this->file->id)
        ->assertSeeHtml('previewFrom($event)')
        ->assertSeeHtml('data-preview="pdf"');
});

it('renders its own toast stack and no inline @php state', function () {
    Livewire::actingAs($this->user)->test('filemanager::file-picker', ['folder' => 'fm/public'])
        ->assertSeeHtml('fmToasts')
        ->assertSeeHtml('fm-picker-field')
        ->assertDontSeeHtml('syn-');
});
