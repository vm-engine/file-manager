<div x-data="{ pageName: {{ json_encode($this->title()) }} }">
    @assets
        <link
            rel="stylesheet"
            href="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('phosphor.css') }}"
        >
        <link
            rel="stylesheet"
            href="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm.css') }}"
        >
        <script src="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm.js') }}"></script>
    @endassets

    <h1 class="fm-page-title">
        <i
            class="ph ph-folder-open"
            aria-hidden="true"
        ></i>{{ $this->title() }}
    </h1>

    <x-filemanager::confirm />
    <x-filemanager::toasts />

    @if ($this->currentFolderConfig === null)
        @include('filemanager::partials.not-configured', ['class' => 'fm'])
    @else
        <div
            class="fm"
            tabindex="-1"
            x-data="fmBrowser({{ json_encode($this->jsConfig) }})"
            x-on:keydown="onKeydown($event)"
        >
            @include('filemanager::partials.toolbar', [
                'mode' => 'manager',
                'folders' => $this->folders,
                'currentFolder' => $currentFolder,
                'rootName' => $this->rootName,
                'segments' => $this->pathSegments,
                'locked' => false,
                'showTrash' => $showTrash,
                'sortBy' => $sortBy,
                'sortDir' => $sortDir,
                'viewMode' => $viewMode,
                'can' => $this->permissions,
                'clipboardCount' => count($clipboard['files']),
                'uploadInputId' => $this->uploadInputId,
            ])

            <div class="fm-body">
                @include('filemanager::partials.tree', [
                    'tree' => $this->folderTree,
                    'rootName' => $this->rootName,
                    'subPath' => $subPath,
                    'showTrash' => $showTrash,
                    'withTrash' => true,
                ])

                <div
                    class="fm-main"
                    x-on:dragenter.prevent="onDragEnter()"
                    x-on:dragleave.prevent="onDragLeave()"
                    x-on:dragover.prevent
                    x-on:drop.prevent="onDrop($event)"
                >
                    <div
                        class="fm-progress"
                        x-show="uploading"
                        x-cloak
                    >
                        <span
                            class="fm-progress-bar"
                            :style="progressStyle"
                        ></span>
                    </div>

                    @if ($this->permissions['upload'] && !$showTrash)
                        <div
                            class="fm-drop"
                            x-show="dragging"
                            x-cloak
                        >
                            <i
                                class="ph ph-cloud-arrow-up fm-drop-icon"
                                aria-hidden="true"
                            ></i>
                            <p class="fm-drop-text">{{ __('filemanager::labels.drop_to_upload') }}</p>
                        </div>
                    @endif

                    <div
                        class="fm-content"
                        wire:key="fm-list-{{ $listKey }}"
                        x-on:click="onBlankClick($event)"
                        x-on:contextmenu.prevent="openBlankMenu($event)"
                        x-on:scroll="closeMenu()"
                    >
                        @if ($this->fileList['truncated'])
                            <p
                                class="fm-search-note"
                                role="status"
                            >{{ __('filemanager::labels.search_truncated', ['count' => \VmEngine\FileManager\Services\FileManagerService::SEARCH_LIMIT]) }}
                            </p>
                        @endif
                        @if ($this->items === [])
                            @include('filemanager::partials.empty', [
                                'search' => $search,
                                'showTrash' => $showTrash,
                                'can' => $this->permissions,
                                'uploadInputId' => $this->uploadInputId,
                                'mode' => 'manager',
                            ])
                        @elseif ($viewMode === 'list')
                            <div class="fm-table-wrap">
                                <table
                                    class="fm-table"
                                    role="grid"
                                    aria-label="{{ __('filemanager::labels.files') }}"
                                >
                                    <thead>
                                        <tr>
                                            <th class="fm-col-check"><span
                                                    class="sr-only">{{ __('filemanager::labels.selection') }}</span>
                                            </th>
                                            @foreach (['filename' => 'name', 'extension' => 'type', 'size' => 'size', 'created_at' => 'date'] as $column => $labelKey)
                                                <th
                                                    class="{{ $column === 'extension' ? 'fm-col-type' : '' }}{{ $column === 'created_at' ? 'fm-col-date' : '' }}"
                                                    aria-sort="{{ $sortBy === $column ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                                >
                                                    <button
                                                        class="fm-sort"
                                                        type="button"
                                                        wire:click="applySort('{{ $column }}')"
                                                    >
                                                        {{ __('filemanager::labels.' . $labelKey) }}
                                                        @if ($sortBy === $column)
                                                            <i
                                                                class="ph {{ $sortDir === 'asc' ? 'ph-caret-up' : 'ph-caret-down' }}"
                                                                aria-hidden="true"
                                                            ></i>
                                                        @endif
                                                    </button>
                                                </th>
                                            @endforeach
                                            <th class="fm-col-more"><span
                                                    class="sr-only">{{ __('filemanager::labels.actions') }}</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->items as $item)
                                            @include('filemanager::partials.item-row', [
                                                'item' => $item,
                                                'selected' => $this->isSelected($item),
                                                'cut' => $this->isCut($item),
                                                'showLocation' => $showTrash || $search !== '',
                                            ])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div
                                class="fm-grid"
                                role="listbox"
                                aria-multiselectable="true"
                                aria-label="{{ __('filemanager::labels.files') }}"
                            >
                                @foreach ($this->items as $item)
                                    @include('filemanager::partials.item-tile', [
                                        'item' => $item,
                                        'mode' => 'manager',
                                        'selected' => $this->isSelected($item),
                                        'cut' => $this->isCut($item),
                                        'showLocation' => $showTrash || $search !== '',
                                    ])
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($selected !== [] || $selectedDirs !== [])
                @include('filemanager::partials.pill', [
                    'summary' => $this->selectionSummary,
                    'noFiles' => $this->selectionSummary['files'] === 0,
                    'showTrash' => $showTrash,
                    'can' => $this->permissions,
                ])
            @endif

            @include('filemanager::partials.context-menu', [
                'showTrash' => $showTrash,
                'can' => $this->permissions,
            ])
            @include('filemanager::partials.details-drawer', [
                'file' => $this->detailsFile,
                'detailsStyle' => $this->detailsStyle,
                'detailsUrl' => $this->detailsUrl,
                'can' => $this->permissions,
            ])

            <input
                class="fm-hidden-input"
                id="{{ $this->uploadInputId }}"
                type="file"
                multiple
                x-ref="uploadInput"
                x-on:change="onFileInput($event)"
            >

            {{-- Image / video / PDF viewer (teleported; keeps the fmBrowser scope) --}}
            @include('filemanager::partials.viewer')
        </div>
    @endif

    {{-- Rename Modal --}}
    <x-filemanager::modal
        name="fm-rename"
        maxWidth="md"
    >
        <div class="fm-modal-header">
            <h3 class="fm-modal-title">{{ __('filemanager::labels.rename_file') }}</h3>
            <button
                class="fm-modal-close"
                type="button"
                aria-label="{{ __('filemanager::labels.close') }}"
                @click="$dispatch('close-modal-fm-rename')"
            >
                <i
                    class="ph ph-x"
                    aria-hidden="true"
                ></i>
            </button>
        </div>
        <div class="fm-modal-body">
            <div class="form-box required">
                <label for="fm-rename-input">{{ __('filemanager::labels.new_name') }}</label>
                <input
                    id="fm-rename-input"
                    class="form-input @error('renameName') has-error @enderror"
                    type="text"
                    wire:model="renameName"
                    @keydown.enter="$wire.confirmRename()"
                    autofocus
                >
                @error('renameName')
                    <p class="form-error-message">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="fm-modal-footer">
            <button
                class="btn secondary"
                type="button"
                @click="$dispatch('close-modal-fm-rename')"
            >{{ __('filemanager::labels.cancel') }}</button>
            <button
                class="btn primary"
                type="button"
                wire:click="confirmRename"
            >
                <i
                    class="ph ph-floppy-disk"
                    aria-hidden="true"
                ></i>{{ __('filemanager::labels.save') }}
            </button>
        </div>
    </x-filemanager::modal>

    {{-- New Folder Modal --}}
    <x-filemanager::modal
        name="fm-new-folder"
        maxWidth="md"
    >
        <div class="fm-modal-header">
            <h3 class="fm-modal-title">{{ __('filemanager::labels.new_folder') }}</h3>
            <button
                class="fm-modal-close"
                type="button"
                aria-label="{{ __('filemanager::labels.close') }}"
                @click="$dispatch('close-modal-fm-new-folder')"
            >
                <i
                    class="ph ph-x"
                    aria-hidden="true"
                ></i>
            </button>
        </div>
        <div class="fm-modal-body">
            <div class="form-box required">
                <label for="fm-new-folder-input">{{ __('filemanager::labels.folder_name') }}</label>
                <input
                    id="fm-new-folder-input"
                    class="form-input @error('newFolderName') has-error @enderror"
                    type="text"
                    wire:model="newFolderName"
                    @keydown.enter="$wire.createFolder()"
                    placeholder="{{ __('filemanager::labels.folder_name_placeholder') }}"
                    autofocus
                >
                @error('newFolderName')
                    <p class="form-error-message">{{ $message }}</p>
                @enderror
                <p class="form-help">{{ __('filemanager::labels.folder_name_help') }}</p>
            </div>
        </div>
        <div class="fm-modal-footer">
            <button
                class="btn secondary"
                type="button"
                @click="$dispatch('close-modal-fm-new-folder')"
            >{{ __('filemanager::labels.cancel') }}</button>
            <button
                class="btn primary"
                type="button"
                wire:click="createFolder"
            >
                <i
                    class="ph ph-folder-plus"
                    aria-hidden="true"
                ></i>{{ __('filemanager::labels.create') }}
            </button>
        </div>
    </x-filemanager::modal>
</div>
