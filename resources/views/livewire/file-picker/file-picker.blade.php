<div class="fm-picker-field" x-data="fmBrowser({{ json_encode($this->jsConfig) }})" x-on:keydown="onPickerKeydown($event)">
    @assets
        <link rel="stylesheet" href="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('phosphor.css') }}">
        <link rel="stylesheet" href="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm.css') }}">
        <script src="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm.js') }}"></script>
    @endassets

    {{-- Form Field --}}
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <div class="input-group">
        <input class="form-input grow" type="text" value="{{ $value }}" readonly placeholder="{{ __('filemanager::labels.no_file_selected') }}">
        <button class="input-group-item right btn primary" type="button" x-on:click="openPickerModal()">
            <i class="ph ph-folder-open" aria-hidden="true"></i>{{ __('filemanager::labels.browse') }}
        </button>
        @if ($value)
            <button class="input-group-item right btn" type="button" wire:click="$set('value', '')" title="{{ __('filemanager::labels.clear') }}" aria-label="{{ __('filemanager::labels.clear') }}">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    @if ($value && in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], true))
        <img class="fm-picker-preview" src="{{ $value }}" alt="{{ __('filemanager::labels.selected_file') }}">
    @endif

    {{-- Picker Modal --}}
    <x-filemanager::modal name="{{ $this->modalName }}" maxWidth="6xl">
        <div class="fm-modal-header">
            <h3 class="fm-modal-title">{{ __('filemanager::labels.select_file') }}</h3>
            <button class="fm-modal-close" type="button" aria-label="{{ __('filemanager::labels.close') }}" x-on:click="closePickerModal()">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </div>

        @if ($folderError)
            <div class="fm-empty-state">
                <i class="ph ph-warning fm-empty-state-icon" aria-hidden="true"></i>
                <p class="fm-empty-state-text">{{ __('filemanager::labels.invalid_folder') }}</p>
                <p class="fm-empty-state-text"><code>{{ $folder }}</code></p>
            </div>
        @elseif ($this->currentFolderConfig === null)
            @include('filemanager::partials.not-configured', ['class' => 'fm fm-in-picker'])
        @else
            <div class="fm fm-in-picker" x-on:fm-folder-created.window="closeDropdown()">
                @include('filemanager::partials.toolbar', [
                    'mode' => 'picker',
                    'folders' => $this->folders,
                    'currentFolder' => $currentFolder,
                    'rootName' => $this->rootName,
                    'segments' => $this->pathSegments,
                    'locked' => $this->isLocked,
                    'showTrash' => false,
                    'sortBy' => 'filename',
                    'sortDir' => 'asc',
                    'viewMode' => 'grid',
                    'can' => $this->pickerPermissions,
                    'clipboardCount' => 0,
                    'uploadInputId' => $this->uploadInputId,
                ])

                <div class="fm-body">
                    @include('filemanager::partials.tree', [
                        'tree' => $this->folderTree,
                        'rootName' => $this->rootName,
                        'subPath' => $subPath,
                        'showTrash' => false,
                        'withTrash' => false,
                    ])

                    <div
                        class="fm-main"
                        x-on:dragenter.prevent="onDragEnter()"
                        x-on:dragleave.prevent="onDragLeave()"
                        x-on:dragover.prevent
                        x-on:drop.prevent="onDrop($event)"
                    >
                        <div class="fm-progress" x-show="uploading" x-cloak>
                            <span class="fm-progress-bar" :style="progressStyle"></span>
                        </div>

                        @if ($this->pickerPermissions['upload'])
                            <div class="fm-drop" x-show="dragging" x-cloak>
                                <i class="ph ph-cloud-arrow-up fm-drop-icon" aria-hidden="true"></i>
                                <p class="fm-drop-text">{{ __('filemanager::labels.drop_to_upload') }}</p>
                            </div>
                        @endif

                        <div class="fm-content">
                            @if ($this->fileList['truncated'])
                                <p class="fm-search-note" role="status">{{ __('filemanager::labels.search_truncated', ['count' => \VmEngine\FileManager\Services\FileManagerService::SEARCH_LIMIT]) }}</p>
                            @endif
                            @if ($this->items === [])
                                @include('filemanager::partials.empty', [
                                    'search' => $search,
                                    'showTrash' => false,
                                    'can' => $this->pickerPermissions,
                                    'uploadInputId' => $this->uploadInputId,
                                    'mode' => 'picker',
                                ])
                            @else
                                <div class="fm-grid" role="listbox" aria-label="{{ __('filemanager::labels.files') }}">
                                    @foreach ($this->items as $item)
                                        @include('filemanager::partials.item-tile', [
                                            'item' => $item,
                                            'mode' => 'picker',
                                            'selected' => $this->isSelected($item),
                                            'cut' => false,
                                            'showLocation' => $search !== '',
                                        ])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <footer class="fm-picker-footer">
                    <div class="fm-picker-info" aria-live="polite">
                        @if ($this->pickedFile)
                            <strong title="{{ $this->pickedFile->filename }}">{{ $this->pickedFile->filename }}</strong>
                            <span>· {{ $this->pickedFile->getHumanSize() }}</span>
                            @if ($this->pickedFile->dimensions())
                                <span>· {{ $this->pickedFile->dimensions() }}</span>
                            @endif
                        @else
                            <span>{{ __('filemanager::labels.click_to_select') }}</span>
                        @endif
                    </div>
                    <div class="fm-picker-actions">
                        @if ($this->pickedFile && $this->pickedFile->previewKind())
                            <button class="fm-btn" type="button"
                                data-key="{{ $this->pickedFile->id }}"
                                data-name="{{ $this->pickedFile->filename }}"
                                data-url="{{ $this->pickedFile->getUrl() }}"
                                data-preview="{{ $this->pickedFile->previewKind() }}"
                                x-on:click="previewFrom($event)"
                            ><i class="ph ph-eye" aria-hidden="true"></i>{{ __('filemanager::labels.preview') }}</button>
                        @endif
                        <button class="fm-btn" type="button" x-on:click="closePickerModal()">{{ __('filemanager::labels.cancel') }}</button>
                        <button class="fm-btn is-primary" type="button" wire:click="choose" @disabled($this->pickedFile === null)>
                            <i class="ph ph-check" aria-hidden="true"></i>{{ __('filemanager::labels.choose') }}
                        </button>
                    </div>
                </footer>

                <input
                    class="fm-hidden-input"
                    id="{{ $this->uploadInputId }}"
                    type="file"
                    multiple
                    @if ($accept !== '*') accept="{{ $accept }}" @endif
                    x-ref="uploadInput"
                    x-on:change="onFileInput($event)"
                >
            </div>
        @endif
    </x-filemanager::modal>

    <x-filemanager::toasts />

    {{-- Image / video / PDF viewer (teleported above the modal) --}}
    @include('filemanager::partials.viewer')
</div>
