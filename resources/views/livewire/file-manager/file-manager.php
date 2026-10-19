<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Enums\FmAction;
use VmEngine\FileManager\Livewire\Concerns\BrowsesFolders;
use VmEngine\FileManager\Models\FmFile;
use VmEngine\FileManager\Services\FileManagerService;
use VmEngine\FileManager\Support\FileTypeStyle;
use VmEngine\FileManager\Support\FmItem;
use VmEngine\FileManager\Support\FmPath;

new class extends Component
{
    use BrowsesFolders;
    use WithFileUploads;

    /** Session key holding the last browsing position. */
    private const POSITION_KEY = 'filemanager.position';

    /**
     * Properties restored from the session when the URL doesn't set them.
     *
     * @var array<int, string>
     */
    private const REMEMBERED = ['viewMode', 'currentFolder', 'subPath', 'sortBy', 'sortDir'];

    #[Url()]
    public string $currentFolder = '';

    #[Url()]
    public string $subPath = '';

    #[Url()]
    public string $viewMode = 'grid';

    #[Url()]
    public string $search = '';

    #[Url()]
    public string $sortBy = 'filename';

    #[Url()]
    public string $sortDir = 'asc';

    public bool $showTrash = false;

    /** @var array<int, int> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $selectedDirs = [];

    /**
     * FM clipboard — survives navigation and root switches for the session.
     *
     * @var array{mode: string, files: array<int, int>}
     */
    #[Session(key: 'filemanager.clipboard')]
    public array $clipboard = ['mode' => '', 'files' => []];

    public ?int $detailsId = null;

    /** @var mixed */
    public $uploadFiles = [];

    public string $renameFileId = '';

    public string $renameName = '';

    public string $newFolderName = '';

    /** Sub-path the new folder is created in; null = current folder. */
    public ?string $newFolderParent = null;

    public int $listKey = 0;

    /**
     * Restore the remembered position (unless the URL names one), then
     * validate it and expand only its ancestors.
     */
    public function mount(): void
    {
        $this->restorePosition();

        if (FmConfig::getFolderByPath($this->currentFolder) === null) {
            $this->currentFolder = FmConfig::getFolders()[0]['path'] ?? '';
            $this->subPath = '';
        }

        if (! $this->enterPath($this->subPath)) {
            $this->subPath = '';
        }
    }

    /**
     * Remember the browsing position for the next visit.
     */
    public function dehydrate(): void
    {
        $position = [];

        foreach (self::REMEMBERED as $property) {
            $position[$property] = $this->{$property};
        }

        session([self::POSITION_KEY => $position]);
    }

    public function title(): string
    {
        return __('filemanager::labels.file_manager');
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
    }

    // ---------------------------------------------------------- navigation

    public function navigateTo(string $path): void
    {
        if (! $this->enterPath($path)) {
            return;
        }

        $this->showTrash = false;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function switchFolder(string $path): void
    {
        if ($path === $this->currentFolder) {
            $this->navigateTo('');

            return;
        }

        if (FmConfig::getFolderByPath($path) === null) {
            return;
        }

        $this->currentFolder = $path;
        $this->subPath = '';
        $this->expanded = [];
        $this->showTrash = false;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function toggleView(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function setSort(string $column): void
    {
        if (in_array($column, ['filename', 'size', 'extension', 'created_at'], true)) {
            $this->sortBy = $column;
        }
    }

    public function setSortDir(string $direction): void
    {
        if (in_array($direction, ['asc', 'desc'], true)) {
            $this->sortDir = $direction;
        }
    }

    /**
     * List-view header click: same column flips direction, new column sorts ascending.
     */
    public function applySort(string $column): void
    {
        if (! in_array($column, ['filename', 'size', 'extension', 'created_at'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    // ----------------------------------------------------------- selection

    public function selectOnly(string $kind, string $key): void
    {
        $this->clearSelection();

        if ($kind === 'file') {
            $this->selected = [(int) $key];

            if ($this->detailsId !== null) {
                $this->detailsId = (int) $key;
            }

            return;
        }

        $dir = FmPath::clean($key);

        if ($dir !== null && $dir !== '') {
            $this->selectedDirs = [$dir];
        }
    }

    public function toggleSelect(string $kind, string $key): void
    {
        if ($kind === 'file') {
            $id = (int) $key;
            $this->selected = in_array($id, $this->selected, true)
                ? array_values(array_diff($this->selected, [$id]))
                : [...$this->selected, $id];

            return;
        }

        $dir = FmPath::clean($key);

        if ($dir === null || $dir === '') {
            return;
        }

        $this->selectedDirs = in_array($dir, $this->selectedDirs, true)
            ? array_values(array_diff($this->selectedDirs, [$dir]))
            : [...$this->selectedDirs, $dir];
    }

    public function selectAll(): void
    {
        $this->selected = $this->fileList['files']->modelKeys();
        $this->selectedDirs = $this->showTrash
            ? []
            : array_map(fn (string $dir): string => FmItem::dir($this->subPath, $dir)['key'], $this->fileList['dirs']);
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectedDirs = [];
    }

    /**
     * @param  array{kind: string, key: string}  $item
     */
    public function isSelected(array $item): bool
    {
        return $item['kind'] === 'dir'
            ? in_array($item['key'], $this->selectedDirs, true)
            : in_array((int) $item['key'], $this->selected, true);
    }

    /**
     * @param  array{kind: string, key: string}  $item
     */
    public function isCut(array $item): bool
    {
        return $item['kind'] === 'file'
            && $this->clipboard['mode'] === 'cut'
            && in_array((int) $item['key'], $this->clipboard['files'], true);
    }

    // ----------------------------------------------------------- clipboard

    public function clipboardCopy(): void
    {
        $this->fillClipboard('copy');
    }

    public function clipboardCut(): void
    {
        $this->fillClipboard('cut');
    }

    private function fillClipboard(string $mode): void
    {
        if ($this->selected === [] || $this->showTrash) {
            return;
        }

        $this->clipboard = ['mode' => $mode, 'files' => array_values($this->selected)];
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: trans_choice('filemanager::labels.clipboard_'.$mode, count($this->selected)));
    }

    /**
     * Paste the clipboard into $target (a sub-path of the current root), or
     * into the current folder when null. Files only — folder copy/cut is UI-only.
     */
    public function paste(?string $target = null): void
    {
        $user = auth()->user();
        $ids = array_map('intval', $this->clipboard['files']);
        $targetSubPath = $target === null ? $this->subPath : FmPath::clean($target);

        if (! $user || $ids === [] || $targetSubPath === null || $this->showTrash) {
            return;
        }

        $isCut = $this->clipboard['mode'] === 'cut';
        $action = $isCut ? FmAction::Move : FmAction::Copy;
        $files = FmFile::active()->whereIn('id', $ids)->get();

        $allowed = FmConfig::canUserDo($user, $this->currentFolder, $action)
            && $files->pluck('folder_path')->unique()->every(
                fn (string $source): bool => FmConfig::canUserDo($user, $source, $action)
            );

        if (! $allowed) {
            $this->dispatch('fm-notify', variant: 'danger', title: __('filemanager::labels.error'), message: __('filemanager::labels.no_permission'));

            return;
        }

        if ($isCut) {
            // Cutting into the folder a file already lives in is a no-op.
            $files = $files->reject(fn (FmFile $file): bool => $file->folder_path === $this->currentFolder
                && FmPath::parentOf($file->relative_path) === $targetSubPath);
            $this->clipboard = ['mode' => '', 'files' => []];
        }

        if ($files->isEmpty()) {
            $this->clipboard = $isCut ? $this->clipboard : ['mode' => '', 'files' => []];

            return;
        }

        $service = app(FileManagerService::class);

        if ($isCut) {
            $service->move($files->modelKeys(), $this->currentFolder, $targetSubPath);
        } else {
            $service->copy($files->modelKeys(), $this->currentFolder, $targetSubPath);
        }

        $this->clearSelection();
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: trans_choice('filemanager::labels.paste_success', $files->count()));
        $this->listKey++;
    }

    // ------------------------------------------------------------ compress

    /**
     * Zip the selected files and folders into the current folder. Creating a
     * file, so it needs the Upload permission.
     */
    public function compressSelected(): void
    {
        if ($this->showTrash || ! $this->authorizeAction(FmAction::Upload)) {
            return;
        }

        $dirs = array_values(array_filter(
            array_map(fn (string $dir): ?string => FmPath::clean($dir), $this->selectedDirs),
            fn (?string $dir): bool => $dir !== null && $dir !== ''
        ));
        $ids = $this->filesInRoot(array_map('intval', $this->selected))->modelKeys();

        if ($ids === [] && $dirs === []) {
            return;
        }

        $zip = app(FileManagerService::class)->compress($ids, $dirs, $this->currentFolder, $this->subPath, auth()->id());

        if ($zip === null) {
            $this->dispatch('fm-notify', variant: 'warning', title: __('filemanager::labels.error'), message: __('filemanager::labels.compress_empty'));

            return;
        }

        $this->clearSelection();
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.compress_success', ['name' => $zip->filename]));
        $this->listKey++;
    }

    // ------------------------------------------------------------- details

    public function showDetails(int $id): void
    {
        if (FmFile::whereKey($id)->where('folder_path', $this->currentFolder)->doesntExist()) {
            return;
        }

        $this->detailsId = $id;
        $this->selected = [$id];
        $this->selectedDirs = [];
    }

    public function closeDetails(): void
    {
        $this->detailsId = null;
    }

    // -------------------------------------------------------------- upload

    public function updatedUploadFiles(): void
    {
        if (empty($this->uploadFiles)) {
            return;
        }

        $this->upload();
    }

    public function upload(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Upload)) {
            $this->dispatch('fm-notify', variant: 'danger', title: __('filemanager::labels.error'), message: __('filemanager::labels.no_permission'));

            return;
        }

        $uploadConfig = FmConfig::getUploadConfig();
        $maxKb = (int) ($uploadConfig['max_size_kb'] ?? 10240);
        $exts = implode(',', $uploadConfig['allowed_extensions'] ?? []);

        try {
            $this->validate(['uploadFiles.*' => "file|max:{$maxKb}|mimes:{$exts}"]);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('filemanager::labels.upload_failed');
            $this->dispatch('fm-notify', variant: 'danger', title: __('filemanager::labels.error'), message: $message);
            $this->uploadFiles = [];

            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->uploadFiles as $file) {
            $service->upload($file, $this->currentFolder, $this->subPath, $user->id);
        }

        $this->uploadFiles = [];
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.upload_success'));
        $this->listKey++;
    }

    // ------------------------------------------------- trash / restore / purge

    public function trash(int $id): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->delete($file);
        $this->forget($id);
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.trashed_success'));
        $this->dispatch('fm-confirmed');
        $this->listKey++;
    }

    public function trashSelected(): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->delete($file);
        }

        $this->clearSelection();
        $this->detailsId = null;
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.trashed_success'));
        $this->dispatch('fm-confirmed');
        $this->listKey++;
    }

    public function restore(int $id): void
    {
        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->restore($file);
        $this->forget($id);
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.restore_success'));
        $this->dispatch('fm-confirmed');
        $this->listKey++;
    }

    public function restoreSelected(): void
    {
        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->restore($file);
        }

        $this->clearSelection();
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.restore_success'));
        $this->listKey++;
    }

    public function purge(int $id): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->purge($file);
        $this->forget($id);
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.purge_success'));
        $this->dispatch('fm-confirmed');
        $this->listKey++;
    }

    public function purgeSelected(): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->purge($file);
        }

        $this->clearSelection();
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.purge_success'));
        $this->dispatch('fm-confirmed');
        $this->listKey++;
    }

    /**
     * Files by id that live in the current storage root. Every id-based action
     * goes through this: permissions are checked against $currentFolder and
     * public Livewire properties (selected, detailsId) are client-writable, so
     * an unscoped lookup would let a crafted id reach another root's files.
     *
     * @param  array<int, int>  $ids
     * @return EloquentCollection<int, FmFile>
     */
    private function filesInRoot(array $ids): EloquentCollection
    {
        if ($ids === []) {
            return new EloquentCollection;
        }

        return FmFile::whereIn('id', $ids)->where('folder_path', $this->currentFolder)->get();
    }

    private function forget(int $id): void
    {
        $this->selected = array_values(array_diff($this->selected, [$id]));

        if ($this->detailsId === $id) {
            $this->detailsId = null;
        }
    }

    private function authorizeAction(FmAction $action): bool
    {
        $user = auth()->user();

        if ($user && FmConfig::canUserDo($user, $this->currentFolder, $action)) {
            return true;
        }

        $this->dispatch('fm-notify', variant: 'danger', title: __('filemanager::labels.error'), message: __('filemanager::labels.no_permission'));

        return false;
    }

    // ------------------------------------------------------- rename / mkdir

    public function startRename(int $id): void
    {
        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        $this->renameFileId = (string) $id;
        $this->renameName = $file->filename;
        $this->dispatch('open-modal-fm-rename');
    }

    public function confirmRename(): void
    {
        if (! $this->authorizeAction(FmAction::Rename)) {
            return;
        }

        $this->validate(['renameName' => 'required|string|max:255']);

        $file = $this->filesInRoot([(int) $this->renameFileId])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->rename($file, $this->renameName);
        $this->renameFileId = '';
        $this->renameName = '';
        $this->dispatch('close-modal-fm-rename');
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.rename_success'));
        $this->listKey++;
    }

    public function createFolder(): void
    {
        if (! $this->authorizeAction(FmAction::Mkdir)) {
            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:100|alpha_dash']);

        $parent = FmPath::clean($this->newFolderParent ?? $this->subPath);

        if ($parent === null) {
            return;
        }

        app(FileManagerService::class)->createFolder($this->currentFolder, $parent, $this->newFolderName);

        $this->expanded = array_values(array_unique([...$this->expanded, ...FmPath::ancestors($parent)]));
        $this->newFolderName = '';
        $this->newFolderParent = null;
        $this->dispatch('close-modal-fm-new-folder');
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.folder_created'));
        $this->listKey++;
    }

    // ------------------------------------------------------------ computed

    /**
     * @return array{dirs: array<int, string>, files: Collection<int, FmFile>}
     */
    #[Computed()]
    public function fileList(): array
    {
        // No configured root (empty folders config): never fall back to listing the disk root.
        if ($this->currentFolderConfig === null) {
            return ['dirs' => [], 'files' => new EloquentCollection, 'truncated' => false];
        }

        $service = app(FileManagerService::class);

        if ($this->showTrash) {
            return [
                'dirs' => [],
                'files' => $service->listTrash($this->currentFolder, $this->search, $this->sortBy, $this->sortDir),
                'truncated' => false,
            ];
        }

        return $service->listDirectory($this->currentFolder, $this->subPath, false, $this->search, $this->sortBy, $this->sortDir);
    }

    /**
     * Folders first, then files, as FmItem arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed()]
    public function items(): array
    {
        $dirs = array_map(fn (string $dir): array => FmItem::dir($this->subPath, $dir), $this->fileList['dirs']);
        $files = $this->fileList['files']->map(fn (FmFile $file): array => FmItem::file($file))->all();

        return [...$dirs, ...$files];
    }

    /**
     * @return array{files: int, dirs: int, bytes: int, single: FmFile|null}
     */
    #[Computed()]
    public function selectionSummary(): array
    {
        $files = $this->filesInRoot($this->selected);

        return [
            'files' => $files->count(),
            'dirs' => count($this->selectedDirs),
            'bytes' => (int) $files->sum('size'),
            'single' => $files->count() === 1 && $this->selectedDirs === [] ? $files->first() : null,
        ];
    }

    #[Computed()]
    public function detailsFile(): ?FmFile
    {
        return $this->detailsId === null ? null : $this->filesInRoot([$this->detailsId])->load('creator')->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed()]
    public function folders(): array
    {
        return FmConfig::getFolders();
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed()]
    public function currentFolderConfig(): ?array
    {
        return FmConfig::getFolderByPath($this->currentFolder);
    }

    /**
     * @return array<FmAction>
     */
    #[Computed()]
    public function allowedActions(): array
    {
        $user = auth()->user();

        if (! $user || $this->currentFolder === '') {
            return [];
        }

        return FmConfig::getAllowedActions($user, $this->currentFolder);
    }

    public function canDo(string $action): bool
    {
        $fmAction = FmAction::tryFrom($action);

        return $fmAction !== null && in_array($fmAction, $this->allowedActions, true);
    }

    /**
     * @return array{upload: bool, mkdir: bool, rename: bool, move: bool, copy: bool, delete: bool}
     */
    #[Computed()]
    public function permissions(): array
    {
        return [
            'upload' => $this->canDo('upload'),
            'mkdir' => $this->canDo('mkdir'),
            'rename' => $this->canDo('rename'),
            'move' => $this->canDo('move'),
            'copy' => $this->canDo('copy'),
            'delete' => $this->canDo('delete'),
        ];
    }

    /**
     * Config handed to Alpine.data('fmBrowser') via x-data.
     *
     * @return array<string, mixed>
     */
    #[Computed()]
    public function jsConfig(): array
    {
        $upload = FmConfig::getUploadConfig();
        $confirm = fn (string $prefix, string $yes, string $color, string $icon): array => [
            'title' => __('filemanager::labels.'.$prefix.'_title'),
            'message' => __('filemanager::labels.'.$prefix.'_message'),
            'confirm' => __('filemanager::labels.'.$yes),
            'color' => $color,
            'icon' => $icon,
        ];

        return [
            'mode' => 'manager',
            'canUpload' => $this->canDo('upload'),
            'maxSizeKb' => (int) ($upload['max_size_kb'] ?? 10240),
            'allowedExtensions' => array_values($upload['allowed_extensions'] ?? []),
            'thumbs' => $this->thumbnailJsConfig(),
            'accept' => '*',
            'labels' => [
                'cancel' => __('filemanager::labels.cancel'),
                'error' => __('filemanager::labels.error'),
                'success' => __('filemanager::labels.success'),
                'uploadFailed' => __('filemanager::labels.upload_failed'),
                'pageCounter' => __('filemanager::labels.page_counter'),
                'uploadTooLarge' => __('filemanager::labels.upload_too_large'),
                'uploadInvalidType' => __('filemanager::labels.upload_invalid_type'),
                'urlCopied' => __('filemanager::labels.url_copied'),
            ],
            'confirm' => [
                'trash' => $confirm('trash', 'yes_trash', 'danger', 'ph ph-trash'),
                'trashSelected' => $confirm('trash_selected', 'yes_trash', 'danger', 'ph ph-trash'),
                'restore' => $confirm('restore', 'yes_restore', 'info', 'ph ph-arrow-counter-clockwise'),
                'purge' => $confirm('purge', 'yes_purge', 'danger', 'ph ph-fire'),
                'purgeSelected' => $confirm('purge_selected', 'yes_purge', 'danger', 'ph ph-fire'),
            ],
        ];
    }

    /**
     * Icon/tone of the file shown in the details drawer.
     *
     * @return array{tone: string, icon: string}|null
     */
    #[Computed()]
    public function detailsStyle(): ?array
    {
        return $this->detailsFile ? FileTypeStyle::for($this->detailsFile->extension) : null;
    }

    /**
     * Absolute URL of the file shown in the details drawer.
     */
    #[Computed()]
    public function detailsUrl(): ?string
    {
        return $this->detailsFile ? url()->to($this->detailsFile->getUrl()) : null;
    }

    /**
     * Display name of the current root folder.
     */
    #[Computed()]
    public function rootName(): string
    {
        return (string) ($this->currentFolderConfig['name'] ?? $this->currentFolder);
    }

    #[Computed()]
    public function uploadInputId(): string
    {
        return 'fm-upload-'.$this->getId();
    }

    /**
     * Fill remembered properties from the session when the URL doesn't set them.
     */
    private function restorePosition(): void
    {
        $stored = session(self::POSITION_KEY, []);

        if (! is_array($stored)) {
            return;
        }

        foreach (self::REMEMBERED as $property) {
            if (! request()->query->has($property) && is_string($stored[$property] ?? null)) {
                $this->{$property} = $stored[$property];
            }
        }
    }

    public function render(): View
    {
        return $this->view()
            ->layout((string) config('filemanager.layout', 'filemanager::layouts.app'))
            ->title($this->title());
    }
};
