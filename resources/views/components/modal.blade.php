{{--
    Package modal. Open/close with window events:
    $dispatch('open-modal-{name}') / $dispatch('close-modal-{name}').
    maxWidth: sm | md | xl | 6xl
--}}
@props(['name', 'maxWidth' => 'md'])

<div
    style="display: none;"
    x-data="fmModal(@js($name))"
    x-on:open-modal-{{ $name }}.window="open()"
    x-on:close-modal-{{ $name }}.window="close()"
    x-on:keydown.escape.window="closeOnEscape()"
    x-show="show"
    {{ $attributes->class(['fm-modal-root']) }}
>
    <div
        class="fm-modal-backdrop"
        x-show="show"
        x-transition.opacity
        x-on:click="close()"
    ></div>
    <div
        class="fm-modal-container"
        x-show="show"
        x-on:click.self="close()"
    >
        <div
            class="fm-modal-panel fm-modal-{{ $maxWidth }}"
            role="dialog"
            aria-modal="true"
            x-trap.noscroll="show"
            x-transition
        >
            {{ $slot }}
        </div>
    </div>
</div>
