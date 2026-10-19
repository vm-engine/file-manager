<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk every folder below lives on.
    |
    */

    'disk' => env('FILEMANAGER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Root folders
    |--------------------------------------------------------------------------
    |
    | Root folders shown in the file manager. 'path' is relative to the disk.
    | 'permissions' (role => actions) is only read by the 'spatie' authorizer.
    | Actions: read, upload, delete, rename, move, copy, mkdir.
    |
    */

    'folders' => [
        [
            'name' => 'Public Files',
            'path' => 'fm/public',
            'permissions' => [
                'admin' => ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir'],
            ],
        ],
    ],

    'image' => [
        'thumbnail_width' => 200,
        'thumbnail_height' => 200,
    ],

    'upload' => [
        'max_size_kb' => 10240,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'mp4'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash
    |--------------------------------------------------------------------------
    |
    | Trashed files older than this are removed by `filemanager:purge-trash`.
    | 0 disables purging.
    |
    */

    'trash' => [
        'auto_purge_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorizer
    |--------------------------------------------------------------------------
    |
    | 'gate'   → Gate::allows('fm.{action}', [$folderPath])  (default)
    | 'spatie' → folders[].permissions role map, checked with $user->hasRole()
    | or a class-string implementing VmEngine\FileManager\Contracts\FmAuthorizer
    |
    */

    'authorizer' => 'gate',

    /*
    |--------------------------------------------------------------------------
    | Full-page route
    |--------------------------------------------------------------------------
    |
    | 'ability' is the Gate ability required to open the page; null = no check.
    | The asset route (CSS/JS/fonts) is always registered under 'prefix'.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'file-manager',
        'name' => 'filemanager.',
        'middleware' => ['web', 'auth'],
        'ability' => 'fm.manage',
    ],

    /*
    | Layout the full-page route renders in. It receives {{ $slot }} and $title.
    */

    'layout' => 'filemanager::layouts.app',

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | Optional upload/stream endpoints for other applications.
    |
    */

    'api' => [
        'enabled' => false,
        'prefix' => 'api/file-manager',
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
