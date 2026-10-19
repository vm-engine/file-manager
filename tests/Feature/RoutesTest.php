<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => pinFmConfig());

it('redirects guests away from the file manager page', function () {
    Route::get('/login', fn () => 'login')->name('login');

    $this->get('/file-manager')->assertRedirect('/login');
});

it('forbids users without the fm.manage ability', function () {
    $this->actingAs(fmUser())->get('/file-manager')->assertForbidden();
});

it('renders the page in the configured layout for allowed users', function () {
    Gate::before(fn (): bool => true);

    $this->actingAs(fmUser())->get('/file-manager')
        ->assertOk()
        ->assertSee('data-filemanager-layout', false);
});

it('does not register api routes by default', function () {
    expect(Route::has('filemanager.api.upload'))->toBeFalse();
});
