{{--
    Package toast stack. Push with:
    $dispatch('fm-notify', { variant: 'success'|'danger'|'warning'|'info', title, message })
    or from Livewire: $this->dispatch('fm-notify', variant: ..., title: ..., message: ...)
--}}
<div
    class="fm-toasts"
    x-data="fmToasts"
    x-on:fm-notify.window="push($event.detail)"
    aria-live="polite"
>
    <template
        x-for="toast in toasts"
        x-bind:key="toast.id"
    >
        <div
            x-bind:class="toast.className"
            role="status"
        >
            <strong
                class="fm-toast-title"
                x-text="toast.title"
            ></strong>
            <p
                class="fm-toast-message"
                x-text="toast.message"
            ></p>
            <button
                class="fm-toast-close"
                type="button"
                aria-label="{{ __('filemanager::labels.close') }}"
                x-on:click="dismiss(toast.id)"
            >
                <i
                    class="ph ph-x"
                    aria-hidden="true"
                ></i>
            </button>
        </div>
    </template>
</div>
