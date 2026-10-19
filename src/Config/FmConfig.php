<?php

declare(strict_types=1);

namespace VmEngine\FileManager\Config;

use Illuminate\Contracts\Auth\Authenticatable;
use VmEngine\FileManager\Contracts\FmAuthorizer;
use VmEngine\FileManager\Enums\FmAction;

/**
 * Typed accessors over config('filemanager') plus the authorization entry point.
 */
final class FmConfig
{
    public static function getDisk(): string
    {
        return (string) config('filemanager.disk', 'public');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getFolders(): array
    {
        return (array) config('filemanager.folders', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getFolderByPath(string $path): ?array
    {
        foreach (self::getFolders() as $folder) {
            if (($folder['path'] ?? '') === $path) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * @return array{max_size_kb?: int, allowed_extensions?: array<int, string>}
     */
    public static function getUploadConfig(): array
    {
        /** @var array{max_size_kb?: int, allowed_extensions?: array<int, string>} */
        return (array) config('filemanager.upload');
    }

    /**
     * @return array{thumbnail_width?: int, thumbnail_height?: int}
     */
    public static function getImageConfig(): array
    {
        /** @var array{thumbnail_width?: int, thumbnail_height?: int} */
        return (array) config('filemanager.image');
    }

    /**
     * @return array{auto_purge_days?: int}
     */
    public static function getTrashConfig(): array
    {
        /** @var array{auto_purge_days?: int} */
        return (array) config('filemanager.trash');
    }

    /**
     * Folders that are not configured are always denied, whatever the driver says.
     */
    public static function canUserDo(Authenticatable $user, string $folderPath, FmAction $action): bool
    {
        if (self::getFolderByPath($folderPath) === null) {
            return false;
        }

        return app(FmAuthorizer::class)->allows($user, $folderPath, $action);
    }

    /**
     * @return array<int, FmAction>
     */
    public static function getAllowedActions(Authenticatable $user, string $folderPath): array
    {
        return array_values(array_filter(
            FmAction::cases(),
            fn (FmAction $action): bool => self::canUserDo($user, $folderPath, $action),
        ));
    }
}
