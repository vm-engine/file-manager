<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use VmEngine\FileManager\Config\FmConfig;
use VmEngine\FileManager\Enums\FmAction;
use VmEngine\FileManager\Livewire\Concerns\BrowsesFolders;
use VmEngine\FileManager\Models\FmFile;
use VmEngine\FileManager\Services\FileManagerService;
use VmEngine\FileManager\Support\FmItem;

new class extends Component
{
    use BrowsesFolders;
    use WithFileUploads;

    /** The value synced with the parent via wire:model */
    #[Modelable]
    public string $value = '';

    /** Accepted MIME type filter, e.g. "image/*" or "*" */
    public string $accept = '*';

    /** Unique key to scope open/close events when multiple pickers exist on the same page */
    public string $pickerKey = '';

    /** Label displayed above the input */
    public string $label = '';

    /**
     * Optional: lock the picker to a specific configured folder path.
     * If invalid, the picker shows an error instead of the file browser.
     */
    public string $folder = '';

    /** Currently browsed root */
    public string $currentFolder = '';

    /** Sub-path inside the root */
    public string $subPath = '';

    public string $search = '';

    /** File highlighted by a single click; confirmed by choose() */
    public ?int $pickedId = null;

    /** Name typed into the inline "New folder" popover */
    public string $newFolderName = '';

    /** @var mixed */
    public $uploadFiles = [];

    /** True when the given $folder is not in the config */
    public bool $folderError = false;

    public function mount(): void
    {
        if ($this->folder !== '') {
            if (FmConfig::getFolderByPath($this->folder) === null) {
                $this->folderError = true;

                return;
            }

            $this->currentFolder = $this->folder;
        } else {
            $this->currentFolder = FmConfig::getFolders()[0]['path'] ?? '';
        }

        $this->restorePosition();
    }

    /**
     * Persist the position on every response (Livewire lifecycle hook, not callable).
     */
    public function dehydrate(): void
    {
        if (! $this->folderError) {
            session([$this->memoryKey() => ['currentFolder' => $this->currentFolder, 'subPath' => $this->subPath]]);
        }
    }

    private function memoryKey(): string
    {
        return 'fm:picker:'.($this->folder !== '' ? $this->folder : 'any');
    }

    private function restorePosition(): void
    {
        $stored = session($this->memoryKey());

        if (! is_array($stored)) {
            return;
        }

        $folder = (string) ($stored['currentFolder'] ?? '');

        if ($this->folder === '' && FmConfig::getFolderByPath($folder) !== null) {
            $this->currentFolder = $folder;
        }

        if ($folder === $this->currentFolder) {
            $this->enterPath((string) ($stored['subPath'] ?? ''));
        }
    }

    #[On('fm:open-picker')]
    public function openPicker(string $accept = '*', string $key = ''): void
    {
        if ($key !== '' && $key !== $this->pickerKey) {
            return;
        }

        $this->accept = $accept;
        $eventName = $this->pickerKey !== '' ? 'fm-picker-open-'.$this->pickerKey : 'fm-picker-open';
        $this->dispatch($eventName);
    }

    public function navigateTo(string $path): void
    {
        if ($this->enterPath($path)) {
            $this->pickedId = null;
        }
    }

    public function switchFolder(string $path): void
    {
        if ($this->isLocked) {
            return;
        }

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
        $this->pickedId = null;
    }

    public function pick(int $id): void
    {
        $this->pickedId = $id;
    }

    public function choose(): void
    {
        if ($this->pickedId !== null) {
            $this->selectFile($this->pickedId);
        }
    }

    public function selectFile(int $id): void
    {
        $file = FmFile::active()->where('folder_path', $this->currentFolder)->find($id);

        if (! $file) {
            return;
        }

        $url = $file->getUrl();
        $this->value = $url;
        $this->pickedId = null;

        // Dispatch to JS — TinyMCE listener and Alpine.js form fields pick this up
        $this->dispatch('fm:file-selected', url: $url, path: $file->getStoragePath(), fileId: $file->id, key: $this->pickerKey);
        $this->dispatch('fm-picker-close');
    }

    /**
     * @param  array{kind: string, key: string}  $item
     */
    public function isSelected(array $item): bool
    {
        return $item['kind'] === 'file' && (int) $item['key'] === $this->pickedId;
    }

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
        unset($this->fileList, $this->items);
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.upload_success'));
    }

    /**
     * Inline "New folder" popover: create in the current folder, then tell
     * fmBrowser to close the popover (fm-folder-created).
     */
    public function createFolder(): void
    {
        if (! $this->canMkdir) {
            $this->dispatch('fm-notify', variant: 'danger', title: __('filemanager::labels.error'), message: __('filemanager::labels.no_permission'));

            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:100|alpha_dash']);

        app(FileManagerService::class)->createFolder($this->currentFolder, $this->subPath, $this->newFolderName);

        $this->newFolderName = '';
        unset($this->fileList, $this->items, $this->folderTree);
        $this->dispatch('fm-folder-created');
        $this->dispatch('fm-notify', variant: 'success', title: __('filemanager::labels.success'), message: __('filemanager::labels.folder_created'));
    }

    #[Computed()]
    public function canUpload(): bool
    {
        $user = auth()->user();

        return $user !== null && FmConfig::canUserDo($user, $this->currentFolder, FmAction::Upload);
    }

    #[Computed()]
    public function canMkdir(): bool
    {
        $user = auth()->user();

        return $user !== null && FmConfig::canUserDo($user, $this->currentFolder, FmAction::Mkdir);
    }

    /**
     * @return array{dirs: array<string>, files: EloquentCollection<int, FmFile>, truncated: bool}
     */
    #[Computed()]
    public function fileList(): array
    {
        // No configured root (empty folders config): never fall back to listing the disk root.
        if ($this->currentFolderConfig === null) {
            return ['dirs' => [], 'files' => new EloquentCollection, 'truncated' => false];
        }

        $result = app(FileManagerService::class)->listDirectory($this->currentFolder, $this->subPath, false, $this->search);

        if ($this->accept !== '*' && $this->accept !== '') {
            $result['files'] = $result['files']
                ->filter(fn (FmFile $file) => $this->matchesAccept($file))
                ->values();
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed()]
    public function items(): array
    {
        $dirs = array_map(fn (string $dir): array => FmItem::dir($this->subPath, $dir), $this->fileList['dirs']);
        $files = $this->fileList['files']->map(fn (FmFile $file): array => FmItem::file($file))->all();

        return [...$dirs, ...$files];
    }

    #[Computed()]
    public function pickedFile(): ?FmFile
    {
        return $this->pickedId === null ? null : FmFile::active()->where('folder_path', $this->currentFolder)->find($this->pickedId);
    }

    private function matchesAccept(FmFile $file): bool
    {
        foreach (explode(',', $this->accept) as $part) {
            $part = trim($part);

            if ($part === '*') {
                return true;
            }
            if (str_ends_with($part, '/*') && str_starts_with($file->mime_type, str_replace('*', '', $part))) {
                return true;
            }
            if (str_starts_with($part, '.') && $file->extension === ltrim($part, '.')) {
                return true;
            }
            if ($file->mime_type === $part) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed()]
    public function folders(): array
    {
        if ($this->folder !== '') {
            $folderConfig = FmConfig::getFolderByPath($this->folder);

            return $folderConfig !== null ? [$folderConfig] : [];
        }

        return FmConfig::getFolders();
    }

    #[Computed()]
    public function isLocked(): bool
    {
        return $this->folder !== '' && ! $this->folderError;
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
     * The picker never offers rename/move/copy/delete.
     *
     * @return array{upload: bool, mkdir: bool, rename: bool, move: bool, copy: bool, delete: bool}
     */
    #[Computed()]
    public function pickerPermissions(): array
    {
        return [
            'upload' => $this->canUpload,
            'mkdir' => $this->canMkdir,
            'rename' => false,
            'move' => false,
            'copy' => false,
            'delete' => false,
        ];
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
     * Modal name for this picker. Lowercased because HTML lowercases attribute
     * names, so the package modal's open-modal-{name} listener is bound in
     * lowercase — a mixed-case Livewire id would never match the dispatch.
     */
    #[Computed()]
    public function modalName(): string
    {
        return 'fm-picker-'.strtolower($this->getId());
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed()]
    public function jsConfig(): array
    {
        $upload = FmConfig::getUploadConfig();

        return [
            'mode' => 'picker',
            'modalName' => $this->modalName,
            'pickerKey' => $this->pickerKey,
            'canUpload' => $this->canUpload,
            'maxSizeKb' => (int) ($upload['max_size_kb'] ?? 10240),
            'allowedExtensions' => array_values($upload['allowed_extensions'] ?? []),
            'accept' => $this->accept,
            'thumbs' => $this->thumbnailJsConfig(),
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
        ];
    }

    public function render(): View
    {
        return $this->view();
    }
};
