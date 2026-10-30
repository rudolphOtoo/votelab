<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @isset($theme) data-theme="{{ $theme }}" @endisset>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-ink font-sans antialiased">
    {{ $slot }}

    <x-ui.toast />

    @livewireScriptConfig
</body>
</html>
