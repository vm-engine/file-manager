# Changelog

All notable changes to `vm-engine/file-manager` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## 0.1.0 - 2026-10-19

First release: a standalone port of the Synapse `synmod-fm` module. It needs only Laravel 13 and Livewire 4; Synapse and synapps-auth are not required.

### Added

- **File manager page** (`<livewire:filemanager::file-manager />`, route `filemanager.index` at `/file-manager`):
  - folder tree and grid/list views
  - drag-and-drop upload, rename, new folder, details drawer
  - recursive search, sorting
  - clipboard copy/cut/paste, zip compression
  - trash with restore and purge
  - full-screen image/video/PDF viewer, keyboard shortcuts
- **File picker** (`<livewire:filemanager::file-picker />`): a `wire:model` form field with a modal browser.
  - It can be locked to one folder (`folder`), filtered server-side (`accept`), and scoped with `pickerKey` so several pickers can share a page.
  - It speaks the `fm:open-picker` / `fm:file-selected` browser events.
- **Config:** `config/filemanager.php`, published with `php artisan vendor:publish --tag=filemanager-config`. It covers the disk, root folders, upload limits, thumbnail size, trash, authorizer, page route (prefix, middleware, `ability`, on/off), layout and API.
- **Per-folder authorization** through the `FmAuthorizer` contract, with three drivers:
  - `gate` (default): asks `fm.{action}` gates with the folder path.
  - `spatie`: checks each folder's role → actions map with `hasRole()`.
  - Any class implementing the contract.

  Folders that aren't in the config are always denied.
- **`FileManagerActivity` event** (`userId`, `action`, `description`, `feature`), fired after every user-initiated write.
- **`filemanager:purge-trash` command:** removes trashed files older than `trash.auto_purge_days` (`0` disables it). It's meant to be scheduled.
- **Opt-in API** (`api.enabled`): `POST upload` and `GET stream/{id}`, Sanctum by default, with the same folder permission checks as the UI.
- **TinyMCE integration** (`resources/js/fm-tinymce.js`): adds browsing to TinyMCE's image, link and media dialogs.
- **Jodit integration** (`resources/js/fm-jodit.js`): `fmImage` / `fmFile` toolbar buttons that insert an image or a file link.
- **Package-owned UI:** `<x-filemanager::modal>`, `<x-filemanager::confirm>` and `<x-filemanager::toasts>` (Alpine), a minimal full-page layout, and standalone button, form and input-group styles in `fm.css`. Dark mode follows a `.dark` class on `<html>`.
- **Bundled assets:** Phosphor Icons (regular, MIT) and pdf.js (Apache-2.0), served by the package's asset route with no build step.
- **Publish tags:** `filemanager-config`, `filemanager-views`, `filemanager-lang`.
- **Translations:** English and Indonesian.
- **Tests:** a Pest + Orchestra Testbench suite.

### Changed (compared to synmod-fm)

- **Namespace and prefix:** `VmEngine\Fm\` → `VmEngine\FileManager\`, and the `fm::` view/translation/Livewire prefix → `filemanager::`.
- **Config source:** `synapps/config/fm.json` and the `fm:setup` command are replaced by the published config file.
- **Activity logging:** `SynAuth::logActivity()` is replaced by the `FileManagerActivity` event.
- **Dev bypass:** `isDev()` is gone; use `Gate::before`, or list the role in each folder's map.
- **Synapse UI:** the modal, confirm dialog, toastr, breadcrumbs and module ACL directives are replaced by package components.
  - Browser events are renamed `fm-notify`, `fm-confirm` and `fm-confirmed`, so they don't clash with a Synapse host.
- **Remembered position:** the remembered folder, view and sort use the session under `filemanager.position`; the clipboard uses `filemanager.clipboard`.
- **User model:** `FmFile::creator()` uses the host's `auth.providers.users.model` instead of `App\Models\User`.
- **Creator ids:** `upload()` and `compress()` accept `int|string` creator ids.

### Security

- **Sandboxed streams:** the stream endpoint now sends `Content-Security-Policy: sandbox` and `X-Content-Type-Options: nosniff`, so uploaded SVG/HTML can't run script on the app origin.
- **Streamed responses:** files are streamed instead of being read into memory.
