{{-- Right slide-over with file details (manager). @param \VmEngine\FileManager\Models\FmFile|null $file  @param array $can  @param array{tone: string, icon: string}|null $detailsStyle  @param string|null $detailsUrl --}}
@if ($file)
    <aside class="fm-drawer" role="dialog" aria-labelledby="fm-drawer-title" wire:key="fm-drawer-{{ $file->id }}">
        <header class="fm-drawer-head">
            <h3 class="fm-drawer-title" id="fm-drawer-title">{{ __('filemanager::labels.details') }}</h3>
            <button class="fm-icon-btn" type="button" aria-label="{{ __('filemanager::labels.close') }}" title="{{ __('filemanager::labels.close') }} (Esc)" wire:click="closeDetails">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="fm-drawer-scroll">
            <div class="fm-drawer-preview fm-tone-{{ $detailsStyle['tone'] }}">
                @if ($file->isImage())
                    <img src="{{ $file->getUrl() }}" alt="{{ $file->filename }}">
                @elseif ($file->isVideo())
                    <video src="{{ $file->getUrl() }}" controls preload="metadata"></video>
                @elseif ($file->has_thumbnail)
                    <img src="{{ $file->getThumbnailUrl() }}" alt="{{ $file->filename }}">
                @else
                    <i class="ph {{ $detailsStyle['icon'] }}" aria-hidden="true"></i>
                @endif
            </div>

            <div class="fm-drawer-body">
                <p class="fm-drawer-name">{{ $file->filename }}</p>
                <dl class="fm-meta">
                    <dt>{{ __('filemanager::labels.type') }}</dt>
                    <dd>{{ strtoupper($file->extension) }} · {{ $file->mime_type }}</dd>
                    <dt>{{ __('filemanager::labels.size') }}</dt>
                    <dd>{{ $file->getHumanSize() }}</dd>
                    @if ($file->dimensions())
                        <dt>{{ __('filemanager::labels.dimensions') }}</dt>
                        <dd>{{ $file->dimensions() }}</dd>
                    @endif
                    <dt>{{ __('filemanager::labels.created') }}</dt>
                    <dd>{{ $file->created_at->format('d M Y H:i') }}</dd>
                    @if ($file->creator)
                        <dt>{{ __('filemanager::labels.uploaded_by') }}</dt>
                        <dd>{{ $file->creator->name }}</dd>
                    @endif
                    <dt>{{ __('filemanager::labels.location') }}</dt>
                    <dd>{{ $file->getStoragePath() }}</dd>
                </dl>
                <div class="fm-url">
                    <input type="text" readonly value="{{ $detailsUrl }}" aria-label="{{ __('filemanager::labels.url') }}">
                    <button class="fm-icon-btn" type="button" data-url="{{ $detailsUrl }}"
                        aria-label="{{ __('filemanager::labels.copy_url') }}" title="{{ __('filemanager::labels.copy_url') }}"
                        x-on:click="copyUrl($event)"
                    ><i class="ph ph-link" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>

        <footer class="fm-drawer-actions">
            <a class="fm-icon-btn" href="{{ $file->getUrl() }}" download="{{ $file->filename }}"
                aria-label="{{ __('filemanager::labels.download') }}" title="{{ __('filemanager::labels.download') }}"
            ><i class="ph ph-download-simple" aria-hidden="true"></i></a>
            @if (! $file->is_trashed && $can['rename'])
                <button class="fm-icon-btn" type="button" wire:click="startRename({{ $file->id }})"
                    aria-label="{{ __('filemanager::labels.rename') }}" title="{{ __('filemanager::labels.rename') }} (F2)"
                ><i class="ph ph-pencil-simple" aria-hidden="true"></i></button>
            @endif
            @if (! $file->is_trashed && $can['delete'])
                <button class="fm-icon-btn is-danger" type="button" data-id="{{ $file->id }}" data-name="{{ $file->filename }}"
                    aria-label="{{ __('filemanager::labels.trash') }}" title="{{ __('filemanager::labels.trash') }}"
                    x-on:click="confirmTrashFrom($event)"
                ><i class="ph ph-trash" aria-hidden="true"></i></button>
            @endif
        </footer>
    </aside>
@endif
