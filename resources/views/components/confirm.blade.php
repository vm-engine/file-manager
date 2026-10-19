{{--
    Package confirmation dialog. Open with:
    $dispatch('fm-confirm', { title, message, confirmText, cancelText, confirmColor, icon,
                              wireMethod, wireParams, wireComponent })
    The Livewire method closes it by dispatching 'fm-confirmed'.
--}}
<div
    style="display: none;"
    wire:ignore
    x-data="fmConfirm(@js(['confirmText' => __('filemanager::labels.confirm'), 'cancelText' => __('filemanager::labels.cancel')]))"
    x-on:fm-confirm.window="open($event.detail)"
    x-on:fm-confirmed.window="close()"
    x-on:keydown.escape.window="closeOnEscape()"
    x-show="show"
    class="fm-modal-root"
>
    <div
        class="fm-modal-backdrop"
        x-show="show"
        x-transition.opacity
    ></div>
    <div
        class="fm-modal-container"
        x-show="show"
        x-on:click.self="close()"
    >
        <div
            class="fm-modal-panel fm-modal-sm"
            role="alertdialog"
            x-bind:aria-label="title"
            aria-modal="true"
            x-trap.noscroll="show"
            x-transition
        >
            <div class="fm-modal-header">
                <span x-bind:class="iconWrapClass"><i
                        x-bind:class="icon"
                        aria-hidden="true"
                    ></i></span>
                <h3
                    class="fm-modal-title"
                    x-text="title"
                ></h3>
            </div>
            <div class="fm-modal-body">
                <p
                    class="fm-confirm-message"
                    x-text="message"
                ></p>
            </div>
            <div class="fm-modal-footer">
                <button
                    class="btn secondary"
                    type="button"
                    x-on:click="close()"
                    x-text="cancelText"
                ></button>
                <button
                    x-bind:class="confirmButtonClass"
                    type="button"
                    x-on:click="confirm()"
                >
                    <i
                        class="ph ph-spinner fm-spin"
                        wire:loading
                        aria-hidden="true"
                    ></i>
                    <span x-text="confirmText"></span>
                </button>
            </div>
        </div>
    </div>
</div>
