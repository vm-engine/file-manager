<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use VmEngine\FileManager\Events\FileManagerActivity;
use VmEngine\FileManager\Services\FileManagerService;

beforeEach(function () {
    Storage::fake('public');
    pinFmConfig();
});

it('dispatches FileManagerActivity when a user uploads', function () {
    Event::fake([FileManagerActivity::class]);
    $user = fmUser();

    app(FileManagerService::class)->upload(UploadedFile::fake()->image('a.jpg'), 'fm/public', '', $user->getKey());

    Event::assertDispatched(
        FileManagerActivity::class,
        fn (FileManagerActivity $event): bool => $event->userId === $user->getKey()
            && str_starts_with($event->action, 'fm.')
            && $event->feature !== '',
    );
});

it('does not dispatch without a user', function () {
    Event::fake([FileManagerActivity::class]);

    app(FileManagerService::class)->upload(UploadedFile::fake()->image('a.jpg'), 'fm/public', '', null);

    Event::assertNotDispatched(FileManagerActivity::class);
});
