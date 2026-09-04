<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'System Setup & Installation' }} - Cash Register POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
    </style>
</head>
<body class="min-h-full flex flex-col justify-center py-8 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-2xl">
        <!-- Logo / Title -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-900 text-white font-extrabold text-xl mb-3 shadow-md">
                KW
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">CASH REGISTER POS</h1>
            <p class="text-xs font-medium text-slate-500 mt-1">Production Setup &amp; Installation Wizard</p>
        </div>

        @yield('content')

        <p class="text-center text-xs text-slate-400 mt-6">
            Cash Register POS &bull; Built for High Reliability &bull; Kuwait POS System
        </p>
    </div>

    @yield('scripts')
</body>
</html>
