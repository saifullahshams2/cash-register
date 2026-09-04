<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white text-slate-900 antialiased select-none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    @php
        $siteTitle = \App\Models\Setting::get('site_title', config('app.name', 'Cash Register POS'));
        $siteFavicon = \App\Models\Setting::get('site_favicon');
    @endphp
    <title>{{ $siteTitle }}</title>
    @if($siteFavicon)
        <link rel="icon" href="{{ $siteFavicon }}">
    @endif

    <!-- Tailwind Play CDN for instant, zero-build, bulletproof styling -->
    <script src="https://cdn.tailwindcss.com"></script>

    @if(file_exists(public_path('css/app.css')))
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles

    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .font-mono {
            font-family: Arial, sans-serif;
        }
        /* Custom scrollbars for light theme */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="min-h-dvh w-full bg-white text-slate-900 flex flex-col">
    {{ $slot }}

    @livewireScripts
</body>
</html>
