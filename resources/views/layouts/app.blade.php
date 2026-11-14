<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @isset($theme) data-theme="{{ $theme }}" @endisset>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? 'Vote for your favourite nominees in Ghana\'s awards, with mobile money or card. Fast, secure and counted once.' }}">
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    @isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
    <meta name="theme-color" content="{{ ($theme ?? null) === 'stage' ? '#121316' : '#faf9f6' }}">
    @foreach ($meta ?? [] as $property => $content)
        <meta property="{{ $property }}" content="{{ $content }}">
    @endforeach

    @fonts
    <x-ui.brand-style :organization="$brandOrganization ?? null" />

    {{-- JS bundle per audience (D-124): public (default), auth, admin, stage, or the plain-JavaScript bundles with no
         Livewire or Alpine, where Livewire's assets and the Alpine toast are left out: result (the payment result
         page, D-144) and home (the platform homepage and site pages). --}}
    @php($bundle = in_array($bundle ?? 'public', ['public', 'auth', 'admin', 'stage', 'result', 'home'], true) ? ($bundle ?? 'public') : 'public')
    @php($plain = in_array($bundle, ['result', 'home'], true))
    @vite(['resources/css/app.css', 'resources/js/entries/'.$bundle.'.js'])
    @unless ($plain)
        @livewireStyles
    @endunless
</head>
<body class="min-h-screen bg-canvas text-ink font-sans antialiased">
    {{ $slot }}

    @unless ($plain)
        <x-ui.toast />

        @livewireScriptConfig
    @endunless
</body>
</html>
