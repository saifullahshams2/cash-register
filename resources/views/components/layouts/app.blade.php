<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white text-slate-900 antialiased select-none">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    @php
        $siteTitle = \App\Models\Setting::get('site_title', config('app.name', 'Cash Register POS'));
        $pwaTitle = \App\Models\Setting::get('pwa_title') ?: $siteTitle;
        $siteFavicon = \App\Models\Setting::get('site_favicon');
        $pwaIcon = \App\Models\Setting::get('pwa_icon');
    @endphp
    <title>{{ $siteTitle }}</title>
    @if($siteFavicon)
        <link rel="icon" href="{{ $siteFavicon }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
    @endif
    <link rel="apple-touch-icon" href="{{ $pwaIcon ?: ($siteFavicon ?: asset('icons/icon-192x192.png')) }}">
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $pwaTitle }}">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch((err) => {
                    console.warn('PWA service worker registration error:', err);
                });
            });
        }
    </script>

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @elseif(file_exists(public_path('css/app.css')))
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
    @livewireStyles

    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .font-mono {
            font-variant-numeric: tabular-nums;
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
