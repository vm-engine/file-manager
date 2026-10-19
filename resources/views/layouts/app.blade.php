<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >
    <title>{{ $title ?? config('app.name') }}</title>
    <link
        rel="stylesheet"
        href="{{ \VmEngine\FileManager\Http\Controllers\AssetController::url('fm.css') }}"
    >
    @livewireStyles
</head>

<body
    class="fm-layout"
    data-filemanager-layout
>
    <main class="fm-layout-main">
        {{ $slot }}
    </main>
    @livewireScripts
</body>

</html>
