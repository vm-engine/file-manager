<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use VmEngine\FileManager\Models\FmFile;

beforeEach(function () {
    Storage::fake('public');
    pinFmConfig();

    $this->trashedFile = function (string $name, int $daysAgo): FmFile {
        Storage::disk('public')->put('fm/public/'.$name, 'x');

        return FmFile::create([
            'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => $name, 'filename' => $name,
            'original_name' => $name, 'extension' => 'txt', 'mime_type' => 'text/plain', 'size' => 1,
            'is_trashed' => true, 'trashed_at' => now()->subDays($daysAgo),
        ]);
    };
});

it('purges trashed files older than the configured days', function () {
    $old = ($this->trashedFile)('old.txt', 31);
    $recent = ($this->trashedFile)('recent.txt', 5);

    $this->artisan('filemanager:purge-trash')
        ->expectsOutputToContain('Purged 1 file(s).')
        ->assertSuccessful();

    expect(FmFile::find($old->id))->toBeNull()
        ->and(FmFile::find($recent->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists('fm/public/old.txt'))->toBeFalse();
});

it('does nothing when purging is disabled', function () {
    config()->set('filemanager.trash.auto_purge_days', 0);
    $old = ($this->trashedFile)('old.txt', 400);

    $this->artisan('filemanager:purge-trash')->assertSuccessful();

    expect(FmFile::find($old->id))->not->toBeNull();
});
