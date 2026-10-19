<?php

declare(strict_types=1);

use VmEngine\FileManager\Config\FmConfig;

it('reads the disk from config', function () {
    config()->set('filemanager.disk', 's3');

    expect(FmConfig::getDisk())->toBe('s3');
});

it('ships sensible upload defaults', function () {
    expect(FmConfig::getUploadConfig()['max_size_kb'])->toBe(10240)
        ->and(FmConfig::getUploadConfig()['allowed_extensions'])->toContain('jpg', 'pdf');
});

it('finds a folder by path', function () {
    pinFmConfig();

    expect(FmConfig::getFolderByPath('fm/public')['name'])->toBe('Public')
        ->and(FmConfig::getFolderByPath('nope'))->toBeNull();
});

it('returns image and trash config', function () {
    pinFmConfig();

    expect(FmConfig::getImageConfig()['thumbnail_width'])->toBe(200)
        ->and(FmConfig::getTrashConfig()['auto_purge_days'])->toBe(30);
});
