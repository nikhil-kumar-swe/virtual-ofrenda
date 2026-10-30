<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Virtual Ofrenda')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="mx-auto flex max-w-2xl items-center justify-between px-5 pt-6">
        <a href="/" class="font-serif text-xl text-amber">Virtual Ofrenda</a>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-teal/50 bg-teal/10 px-3 py-1 text-xs text-teal">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
            </svg>
            Runs on your device
        </span>
    </header>

    <main class="mx-auto max-w-2xl px-5 pb-16 pt-6">
        @yield('content')
    </main>
</body>
</html>