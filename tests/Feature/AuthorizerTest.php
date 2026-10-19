<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Contracts\FmAuthorizer;
use VmEngine\FileManager\Enums\FmAction;

beforeEach(fn () => pinFmConfig(['read', 'upload']));

it('denies everything with the gate driver when no gates are defined', function () {
    expect(FmConfig::canUserDo(fmUser(), 'fm/public', FmAction::Read))->toBeFalse();
});

it('asks the fm.{action} gate with the folder path', function () {
    Gate::define('fm.upload', fn ($user, string $folder): bool => $folder === 'fm/public');

    $user = fmUser();

    expect(FmConfig::canUserDo($user, 'fm/public', FmAction::Upload))->toBeTrue()
        ->and(FmConfig::canUserDo($user, 'fm/public', FmAction::Delete))->toBeFalse();
});

it('honours Gate::before as a super-admin bypass', function () {
    Gate::before(fn (): bool => true);

    expect(FmConfig::getAllowedActions(fmUser(), 'fm/public'))->toBe(FmAction::cases());
});

it('denies unconfigured folders even when the driver allows all', function () {
    Gate::before(fn (): bool => true);

    expect(FmConfig::canUserDo(fmUser(), 'not/configured', FmAction::Read))->toBeFalse();
});

it('checks the folder role map with the spatie driver', function () {
    config()->set('filemanager.authorizer', 'spatie');
    app()->forgetInstance(FmAuthorizer::class);

    $admin = fmUser(['admin']);
    $viewer = fmUser(['viewer']);

    expect(FmConfig::getAllowedActions($admin, 'fm/public'))->toBe([FmAction::Read, FmAction::Upload])
        ->and(FmConfig::canUserDo($viewer, 'fm/public', FmAction::Read))->toBeFalse();
});

it('throws a clear error when the spatie driver gets a user without hasRole()', function () {
    config()->set('filemanager.authorizer', 'spatie');
    app()->forgetInstance(FmAuthorizer::class);

    $user = new class implements Authenticatable
    {
        use Illuminate\Auth\Authenticatable;
    };

    FmConfig::canUserDo($user, 'fm/public', FmAction::Read);
})->throws(LogicException::class, 'HasRoles');

it('resolves a custom authorizer class', function () {
    $custom = new class implements FmAuthorizer
    {
        public function allows(Authenticatable $user, string $folderPath, FmAction $action): bool
        {
            return $action === FmAction::Read;
        }
    };
    config()->set('filemanager.authorizer', $custom::class);
    app()->forgetInstance(FmAuthorizer::class);

    expect(FmConfig::getAllowedActions(fmUser(), 'fm/public'))->toBe([FmAction::Read]);
});
