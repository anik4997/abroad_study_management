@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎓</text></svg>">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur" x-data="{ open: false }">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-brand-900">
                <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white">🎓</span>
                <span>{{ config('app.name') }}</span>
            </a>

            @php
                $links = [
                    ['explore', 'Explore programs'],
                    ['universities.index', 'Universities'],
                    ['lists.index', 'Public lists'],
                ];
            @endphp

            <div class="hidden items-center gap-1 md:flex">
                @foreach ($links as [$route, $label])
                    <a href="{{ route($route) }}" @class(['rounded-lg px-3 py-2 text-sm font-medium', 'bg-brand-50 text-brand-700' => request()->routeIs($route), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs($route)])>{{ $label }}</a>
                @endforeach
            </div>

            <div class="hidden items-center gap-2 md:flex">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-ghost">My lists</a>
                    @can('admin')
                        <a href="{{ route('admin.institutions') }}" class="text-sm text-slate-600 hover:text-slate-900">Admin</a>
                    @endcan
                    <a href="{{ route('account') }}" class="text-sm text-slate-600 hover:text-slate-900">{{ auth()->user()->username }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm text-slate-500 hover:text-slate-900">Log out</button></form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Create account</a>
                @endauth
            </div>

            <button class="rounded-lg p-2 md:hidden" @click="open = !open" aria-label="Menu">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </nav>

        <div x-show="open" x-cloak class="space-y-1 border-t border-slate-200 px-4 py-3 md:hidden">
            @foreach ($links as [$route, $label])
                <a href="{{ route($route) }}" class="block rounded-lg px-3 py-2 text-sm">{{ $label }}</a>
            @endforeach
            @auth
                <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 text-sm">My lists</a>
                <a href="{{ route('account') }}" class="block rounded-lg px-3 py-2 text-sm">Account ({{ auth()->user()->username }})</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full rounded-lg px-3 py-2 text-left text-sm">Log out</button></form>
            @else
                <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm">Log in</a>
                <a href="{{ route('register') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-brand-700">Create account</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:py-8">
        @if (session('status'))
            <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700" x-data="{ show: true }" x-show="show">
                <div class="flex items-start justify-between gap-3">
                    <span>{{ session('status') }}</span>
                    <button @click="show = false" class="text-brand-700/70 hover:text-brand-700" aria-label="Dismiss">✕</button>
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        {{ config('app.name') }} — the public directory is built from lists people chose to make public. Always verify details on the official website.
    </footer>

    @livewireScripts
</body>
</html>
