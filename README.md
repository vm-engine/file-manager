# vm-engine/file-manager

A standalone file manager for Laravel + Livewire: a full-page file browser, an embeddable form file picker, per-folder access control, thumbnails, a full-screen image/video/PDF viewer, TinyMCE and Jodit integrations, and an optional upload/stream API.

It needs nothing but Laravel and Livewire. It ships its own CSS, JS and icon font (no build step, no Tailwind or Flux requirement), so it works in any Livewire app.

---

## Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Defining permissions](#defining-permissions)
6. [The file manager page](#the-file-manager-page)
7. [The file picker](#the-file-picker)
8. [TinyMCE integration](#tinymce-integration)
9. [Jodit integration](#jodit-integration)
10. [Activity events](#activity-events)
11. [API](#api)
12. [Thumbnails and trash](#thumbnails-and-trash)
13. [Using the service directly](#using-the-service-directly)
14. [Styling and theming](#styling-and-theming)
15. [Customising views and translations](#customising-views-and-translations)
16. [Testing the package](#testing-the-package)
17. [Credits and licenses](#credits-and-licenses)

---

## Features

- **File browser:** a folder tree (with lazy expand and Trash pinned), grid and list views, drag-and-drop upload, sort by name/size/type/date, rename, new folder, and a details drawer. It remembers your last folder, view and sort across visits.
- **Recursive search** of the current folder and everything below it, capped at 100 results with a "refine your search" hint.
- **Context menu and clipboard:** right-click (or ⋯) on any item, copy/cut/paste between folders, and a floating selection pill.
- **Keyboard shortcuts:** Space (preview), F2 (rename), Del (trash, or purge in Trash), Ctrl/⌘ + C/X/V/A, arrow keys, Esc.
- **Full-screen viewer** for images, videos and PDFs. PDFs get lazy page rendering, a page counter and zoom, and ←/→ steps through the files.
- **Compress to .zip:** zips the selected files and folders into the current folder.
- **Soft-delete trash** with restore, permanent purge, and an optional scheduled auto-purge.
- **Thumbnails:**
  - images use GD on the server
  - PDFs and videos are rendered in the browser (pdf.js / `<video>`) and stored server-side, so no Imagick or ffmpeg is needed
- **Per-folder access control** through Laravel Gates (default), spatie/laravel-permission roles, or your own class.
- **File picker:** a reusable form field with a modal browser. It can be locked to one folder, filtered by type on the server, and used several times on one page.
- **Editor integrations:** TinyMCE (its image/link/media dialogs) and Jodit (toolbar buttons).
- **Activity event** after every write, for your own audit log.
- **Optional API** for uploads from other apps and for streaming files from private disks.
- **Host-agnostic URLs:** file URLs are path-only, so they keep working behind proxies or after a domain change.

---

## Requirements

- PHP 8.5+
- Laravel 13
- Livewire 4
- The `gd` PHP extension, for server-side image thumbnails. Without it, thumbnails are simply skipped.

Optional:

- `spatie/laravel-permission`, for the `spatie` authorizer driver
- `laravel/sanctum`, for the API routes

---

## Installation

```bash
composer require vm-engine/file-manager
```

If you install from a private repository or a local path, add a `repositories` entry to your app's `composer.json` first, for example:

```json
"repositories": [
    { "type": "path", "url": "packages/file-manager", "options": { "symlink": true } }
]
```

Then:

```bash
php artisan vendor:publish --tag=filemanager-config   # creates config/filemanager.php
php artisan migrate                                   # creates the fm_files table
php artisan storage:link                              # if your folders live on the "public" disk
```

The service provider is auto-discovered. By default, the file manager is at **`/file-manager`** for logged-in users who pass the `fm.manage` gate (see [Defining permissions](#defining-permissions)).

> Everything is denied until you grant it. With the default `gate` driver and no `fm.*` gates defined, users can open nothing. That's deliberate; define the gates below.

---

## Configuration

`config/filemanager.php`:

| Key | Default | Meaning |
|---|---|---|
| `disk` | `env('FILEMANAGER_DISK', 'public')` | Filesystem disk every folder lives on |
| `folders` | one `Public Files` folder at `fm/public` | Root folders shown in the file manager. Each has `name`, `path` (relative to the disk) and `permissions` (role → actions, read only by the `spatie` driver) |
| `image.thumbnail_width` / `image.thumbnail_height` | `200` / `200` | Bounding box for generated thumbnails |
| `upload.max_size_kb` | `10240` | Max upload size in KB |
| `upload.allowed_extensions` | jpg, jpeg, png, gif, webp, svg, pdf, doc, docx, xls, xlsx, zip, txt, mp4 | Allowed upload extensions |
| `trash.auto_purge_days` | `30` | Trashed files older than this are removed by `filemanager:purge-trash`. `0` disables purging |
| `authorizer` | `'gate'` | `'gate'`, `'spatie'`, or a class name implementing `FmAuthorizer` |
| `routes.enabled` | `true` | Register the full-page route |
| `routes.prefix` | `'file-manager'` | URL prefix for the page and the asset route |
| `routes.name` | `'filemanager.'` | Route name prefix (`filemanager.index`, `filemanager.assets`) |
| `routes.middleware` | `['web', 'auth']` | Middleware for the page |
| `routes.ability` | `'fm.manage'` | Gate ability required to open the page (`can:` middleware); `null` = no check |
| `layout` | `'filemanager::layouts.app'` | Layout the full-page route renders in |
| `api.enabled` | `false` | Register the API routes |
| `api.prefix` | `'api/file-manager'` | API URL prefix |
| `api.middleware` | `['api', 'auth:sanctum']` | API middleware |

The actions a folder can grant are `read`, `upload`, `delete`, `rename`, `move`, `copy` and `mkdir`.

```php
'folders' => [
    [
        'name' => 'Public Files',
        'path' => 'fm/public',
        'permissions' => [
            'admin' => ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir'],
            'editor' => ['read', 'upload'],
        ],
    ],
    [
        'name' => 'Documents',
        'path' => 'fm/docs',
        'permissions' => ['admin' => ['read', 'upload', 'delete']],
    ],
],
```

The asset route (`/{prefix}/assets/{file}`, which serves CSS, JS and fonts) is **always** registered, even when `routes.enabled` is `false`, because embedded components and the picker need it.

---

## Defining permissions

Every action goes through one check, `FmConfig::canUserDo($user, $folderPath, $action)`:

1. A folder that isn't listed in `folders` is **always denied**, whatever the driver says.
2. Otherwise, the configured authorizer decides.

### `gate` driver (default)

The package asks `Gate::forUser($user)->allows('fm.{action}', [$folderPath])`. Define the gates in your `AppServiceProvider`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use VmEngine\FileManager\Enums\FmAction;

public function boot(): void
{
    // Who may open the /file-manager page (routes.ability)
    Gate::define('fm.manage', fn (User $user): bool => $user->is_admin);

    // Per action, optionally per folder
    Gate::define('fm.read', fn (User $user, string $folder): bool => true);
    Gate::define('fm.upload', fn (User $user, string $folder): bool => $folder !== 'fm/archive');

    // Or grant every action at once
    foreach (FmAction::cases() as $action) {
        Gate::define('fm.'.$action->value, fn (User $user, string $folder): bool => $user->is_admin);
    }

    // Super-admin bypass
    Gate::before(fn (User $user): ?bool => $user->is_super_admin ? true : null);
}
```

spatie/laravel-permission registers its permissions with the Gate. So if you create permissions named `fm.read`, `fm.upload` and so on and assign them to roles, the gate driver uses them with no extra code (they apply to every folder).

### `spatie` driver (per-folder role map)

```php
'authorizer' => 'spatie',
```

The driver reads each folder's `permissions` map and checks `$user->hasRole($role)`. Your user model must use `Spatie\Permission\Traits\HasRoles`; if it doesn't, the driver throws a `LogicException`. Super-admins need their role listed in each folder's map.

### Custom driver

```php
use Illuminate\Contracts\Auth\Authenticatable;
use VmEngine\FileManager\Contracts\FmAuthorizer;
use VmEngine\FileManager\Enums\FmAction;

final class TeamFolderAuthorizer implements FmAuthorizer
{
    public function allows(Authenticatable $user, string $folderPath, FmAction $action): bool
    {
        return $folderPath === 'fm/team-'.$user->team_id
            || ($action === FmAction::Read && $folderPath === 'fm/public');
    }
}
```

```php
'authorizer' => App\FileManager\TeamFolderAuthorizer::class,
```

The class is resolved from the container, so constructor injection works.

---

## The file manager page

By default, `GET /file-manager` (route `filemanager.index`) renders the browser full-page:

- **Middleware:** `routes.middleware` (`web`, `auth`) plus `can:fm.manage`. Set `routes.ability` to `null` to drop the gate check, or to another ability name.
- **Your own layout:** set `'layout' => 'layouts.app'` (any Blade layout that echoes `{{ $slot }}`; `$title` holds the page title). The bundled `filemanager::layouts.app` is a minimal standalone page.
- **No route at all:** set `'routes.enabled' => false` and embed the component in your own page:

```blade
{{-- resources/views/admin/files.blade.php, inside your layout --}}
<livewire:filemanager::file-manager />
```

Add your own menu link with `route('filemanager.index')` (or your own route).

---

## The file picker

A form field (a read-only input plus a **Browse** button) that opens a modal browser. Bind it with `wire:model` to get the chosen file's URL:

```blade
<livewire:filemanager::file-picker wire:model="coverImageUrl" label="Cover image" />

{{-- Locked to one folder, images only, with a key so several pickers can share a page --}}
<livewire:filemanager::file-picker
    wire:model="galleryImageUrl"
    folder="fm/public"
    accept="image/*"
    picker-key="gallery-image"
/>
```

| Prop | Type | Description |
|---|---|---|
| `value` | string | The bound value (`wire:model`): the selected file's URL |
| `label` | string | Label shown above the field |
| `folder` | string | Lock the picker to one configured folder path. An unknown path shows an inline error, and the folder switcher is hidden |
| `accept` | string | Filter applied **on the server**: a wildcard (`image/*`, `video/*`), an extension list (`.jpg,.png`), an exact MIME type, or `*` (default) |
| `pickerKey` | string | Scopes the browser events so several pickers can coexist on one page |

In the picker, click marks a file, and double-click or **Choose** selects it. Space or **Preview** opens the viewer, and **New folder** creates one in place. When locked to a `folder`, the picker remembers the last sub-folder you visited.

### Picker events

Open a picker from your own JavaScript:

```js
window.Livewire.dispatch('fm:open-picker', { accept: 'image/*', key: 'gallery-image' });
```

`key` must match the target picker's `picker-key`. If you omit it, **every** picker on the page responds, so pages with several pickers should always pass a key.

When a file is chosen, the picker dispatches `fm:file-selected` with `{ url, path, fileId, key }`:

```js
document.addEventListener('fm:file-selected', (event) => {
    console.log(event.detail.url, event.detail.path, event.detail.fileId);
});
```

---

## TinyMCE integration

The bridge lives at `resources/js/fm-tinymce.js`. It sets TinyMCE's `file_picker_callback`, so the image, link and media dialogs get a browse button that opens the picker. **A `<livewire:filemanager::file-picker />` must be on the page.**

With a bundler:

```js
import fmTinyMCEConfig from '../../vendor/vm-engine/file-manager/resources/js/fm-tinymce.js';

tinymce.init({
    selector: '#editor',
    plugins: 'image link media',
    ...fmTinyMCEConfig,
});
```

Without a bundler, import it from the asset route:

```blade
<script type="module">
    import fmTinyMCEConfig from '{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm-tinymce.js') }}';

    tinymce.init({ selector: '#editor', plugins: 'image link media', ...fmTinyMCEConfig });
</script>
```

---

## Jodit integration

The bridge lives at `resources/js/fm-jodit.js` (tested against Jodit 4). It adds two toolbar buttons:

- **`fmImage`** opens the picker filtered to images and inserts an `<img>`.
- **`fmFile`** opens the picker for any file and inserts a link named after the file.

**A `<livewire:filemanager::file-picker />` must be on the page.**

```js
import fmJoditConfig from '../../vendor/vm-engine/file-manager/resources/js/fm-jodit.js';

// Jodit's default toolbar + our two buttons
Jodit.make('#editor', { ...fmJoditConfig });

// Or with your own toolbar
Jodit.make('#editor', {
    buttons: ['bold', 'italic', '|', 'link', 'fmImage', 'fmFile'],
    extraButtons: fmJoditConfig.extraButtons,
});
```

The asset-route import works the same way as for TinyMCE: `AssetController::url('fm-jodit.js')`.

---

## Activity events

After every user-initiated write (upload, trash, restore, purge, rename, move, copy, compress, new folder), the package dispatches `VmEngine\FileManager\Events\FileManagerActivity`. The package registers no listener; log the event however you like.

| Property | Type | Example |
|---|---|---|
| `userId` | `int\|string` | `1` |
| `action` | `string` | `fm.file.upload`, `fm.file.trash`, `fm.file.restore`, `fm.file.purge`, `fm.file.rename`, `fm.file.move`, `fm.file.copy`, `fm.file.compress`, `fm.folder.create` |
| `description` | `string` | `Uploaded report.pdf to fm/public/report.pdf (204800 bytes)` |
| `feature` | `string` | `file` or `folder` |

The event isn't fired when there's no user, for example during anonymous API calls or the scheduled purge.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use VmEngine\FileManager\Events\FileManagerActivity;

Event::listen(function (FileManagerActivity $event): void {
    Log::channel('audit')->info($event->description, [
        'user_id' => $event->userId,
        'action' => $event->action,
    ]);
});

// With spatie/laravel-activitylog
Event::listen(function (FileManagerActivity $event): void {
    activity('file-manager')
        ->causedBy($event->userId)
        ->event($event->action)
        ->log($event->description);
});
```

---

## API

Off by default. To enable it:

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/file-manager',
    'middleware' => ['api', 'auth:sanctum'],
],
```

Both endpoints run the same folder permission checks as the UI, for the authenticated user.

### `POST /api/file-manager/upload`

| Field | Rules |
|---|---|
| `file` | required, a file, within `upload.max_size_kb` and `upload.allowed_extensions` |
| `folder_path` | required, a configured folder path |
| `sub_path` | optional sub-folder inside it |

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     -F file=@report.pdf -F folder_path=fm/public \
     https://example.com/api/file-manager/upload
```

```json
{
    "id": 42,
    "filename": "report.pdf",
    "url": "/storage/fm/public/report.pdf",
    "thumbnail_url": "/storage/fm/public/report.pdf",
    "size": 204800,
    "human_size": "200 KB",
    "mime_type": "application/pdf"
}
```

Returns `403` without `upload` permission on `folder_path`, and `422` on validation errors.

### `GET /api/file-manager/stream/{id}`

Streams the file's content inline with its `Content-Type`. Requires `read` permission on the file's folder. Use it for files on private disks that have no public URL.

Responses carry `Content-Security-Policy: sandbox` and `X-Content-Type-Options: nosniff`, so an uploaded SVG or HTML file can't run script on your app's origin.

---

## Thumbnails and trash

**Thumbnails:**
- Image thumbnails are generated with GD on upload/copy, sized to fit `image.thumbnail_width` × `image.thumbnail_height` (PNG/WebP transparency is kept). They're skipped when `gd` isn't loaded.
- PDF and video thumbnails are rendered in the browser on first view (pdf.js, which is vendored, or a `<video>` frame) and stored on the server.

**Trash:** deleting a file moves it to the Trash, where it can be restored or purged. To purge old trash automatically, schedule the bundled command:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('filemanager:purge-trash')->daily();
```

It removes trashed files older than `trash.auto_purge_days`, and does nothing when that's `0`.

---

## Using the service directly

All file operations go through `VmEngine\FileManager\Services\FileManagerService`:

```php
use VmEngine\FileManager\Services\FileManagerService;

$service = app(FileManagerService::class);

$service->listDirectory(string $folderPath, string $subPath = '', bool $showTrash = false, string $search = '', string $sortBy = 'filename', string $sortDir = 'asc'): array; // ['dirs' => [...], 'files' => Collection, 'truncated' => bool]
$service->listTrash(string $folderPath, string $search = '', string $sortBy = 'filename', string $sortDir = 'asc'): Collection;
$service->upload(UploadedFile $file, string $folderPath, string $subPath = '', int|string|null $createdBy = null): FmFile;
$service->delete(FmFile $file): void;      // move to trash
$service->restore(FmFile $file): void;
$service->purge(FmFile $file): void;       // permanent: storage + thumbnail + DB row
$service->rename(FmFile $file, string $newName): FmFile;
$service->move(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void;
$service->copy(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void;
$service->compress(array $fileIds, array $dirs, string $folderPath, string $subPath, int|string|null $createdBy): ?FmFile;
$service->createFolder(string $parentFolderPath, string $subPath, string $name): void;
$service->getUrl(FmFile $file): string;
$service->purgeExpiredTrash(): int;
```

The service doesn't check permissions; call `FmConfig::canUserDo()` first. Filenames are made unique automatically (`report_1.pdf`, …) instead of overwriting.

---

## Styling and theming

All styles live in `resources/dist/fm.css`. It's plain CSS served by the asset route and loaded by the components themselves, so there's nothing to build or import.

- **Colours** are `--fm-*` custom properties. They fall back to your theme's `--color-brand-*` / `--color-gray-*` variables when you define them, so the file manager picks up your brand colour automatically. You can also override them directly:

  ```css
  .fm, .fm-modal-root, .fm-toasts, .fm-picker-field {
      --fm-accent: #0d9488;
      --fm-accent-strong: #0f766e;
  }
  ```

- **Dark mode** follows a `.dark` class on `<html>` (the Tailwind/Flux convention).
- **Icons:** Phosphor Icons (regular weight) are bundled and loaded automatically.
- Buttons, inputs, modals and toasts are styled inside the package's own roots only, so nothing leaks into your page.

---

## Customising views and translations

```bash
php artisan vendor:publish --tag=filemanager-views   # → resources/views/vendor/filemanager
php artisan vendor:publish --tag=filemanager-lang    # → lang/vendor/filemanager
```

English (`en`) and Indonesian (`id`) translations are included.

---

## Testing the package

From the package directory:

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint
```

The tests run on Orchestra Testbench with an in-memory SQLite database.

---

## Credits and licenses

- vm-engine/file-manager: MIT, © Achmad Ardiansyah
- [pdf.js](https://mozilla.github.io/pdf.js/) (vendored, `resources/dist/pdf*.mjs`): Apache-2.0, see `resources/dist/pdfjs-LICENSE`
- [Phosphor Icons](https://phosphoricons.com/) (vendored, `resources/dist/phosphor.css`, `Phosphor.woff2`): MIT, see `resources/dist/phosphor-LICENSE`
