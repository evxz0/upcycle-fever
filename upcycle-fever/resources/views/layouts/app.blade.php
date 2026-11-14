<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Upcycle Fever') }} - @yield('title', 'Eco-Crafting Studio')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <nav class="bg-green-700 text-white px-6 py-3 flex items-center justify-between shadow">
        <div class="flex items-center gap-6">
            <a href="{{ route('home') }}" class="font-bold text-xl tracking-wide flex items-center gap-2">
                <span>🌿</span> Upcycle Fever
            </a>
            <div class="hidden sm:flex items-center gap-4 text-sm font-medium">
                <a href="{{ route('materials.index') }}" class="hover:text-green-200 transition {{ request()->routeIs('materials.*') ? 'text-amber-300 font-bold' : '' }}">
                    📋 Katalog Bahan
                </a>
                <a href="{{ route('game.workbench') }}" class="hover:text-green-200 transition {{ request()->routeIs('game.workbench') ? 'text-amber-300 font-bold' : '' }}">
                    🛠️ Meja Kerja
                </a>
            </div>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <div class="sm:hidden flex items-center gap-2">
                <a href="{{ route('materials.index') }}" class="text-xs bg-green-800 px-2 py-1 rounded">Katalog</a>
                <a href="{{ route('game.workbench') }}" class="text-xs bg-green-800 px-2 py-1 rounded">Meja Kerja</a>
            </div>
            @auth
                <span class="opacity-75">{{ auth()->user()->name }}</span>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="hover:underline">Dashboard Admin</a>
                @else
                    <a href="{{ route('game.lobby') }}" class="hover:underline">Lobi Game</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-green-900 px-3 py-1 rounded hover:bg-green-800">Keluar</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="hover:underline">Masuk</a>
                <a href="{{ route('register') }}" class="bg-amber-400 text-gray-900 px-3 py-1 rounded font-semibold hover:bg-amber-300">Daftar</a>
            @endauth
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-2 rounded">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
