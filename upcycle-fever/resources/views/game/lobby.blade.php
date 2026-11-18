@extends('layouts.app')
@section('title', 'Lobi Game')
@section('content')
<div class="text-center py-12">
    <h1 class="text-3xl font-bold text-green-700 mb-2">Lobi Game</h1>
    <p class="text-gray-500 mb-8">Selamat datang, {{ auth()->user()?->name ?? 'Pemain' }}! Siap mengelola studio kerajinan ramah lingkungan?</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto text-left">
        <a href="{{ route('game.workbench') }}" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-green-500 transition group">
            <div class="text-4xl mb-3 group-hover:scale-110 transition transform">🛠️</div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white mb-1">Meja Kerja (Workbench)</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Pilih bahan limbah dari inventaris Anda dan letakkan ke slot meja kerja untuk perakitan produk.</p>
            <span class="inline-block mt-4 text-xs font-bold text-green-600 dark:text-green-400 group-hover:underline">Buka Meja Kerja →</span>
        </a>

        <a href="{{ route('inventory.warehouse') }}" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-emerald-500 transition group">
            <div class="text-4xl mb-3 group-hover:scale-110 transition transform">♻️</div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white mb-1">Pemilahan Sampah (F-03)</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Pilah sampah plastik, kardus, kaleng, dan kain perca ke rak kategori gudang.</p>
            <span class="inline-block mt-4 text-xs font-bold text-emerald-600 dark:text-emerald-400 group-hover:underline">Buka Gudang →</span>
        </a>

        <a href="{{ route('materials.index') }}" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-green-500 transition group">
            <div class="text-4xl mb-3 group-hover:scale-110 transition transform">📋</div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white mb-1">Katalog Bahan Limbah</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Lihat daftar master data bahan limbah, klasifikasi sampah, dan edukasi seputar target SDG 12.</p>
            <span class="inline-block mt-4 text-xs font-bold text-green-600 dark:text-green-400 group-hover:underline">Buka Katalog →</span>
        </a>

        <a href="{{ route('profile.show') }}" class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md hover:border-amber-400 transition group">
            <div class="text-4xl mb-3 group-hover:scale-110 transition transform">👤</div>
            <h3 class="font-bold text-lg text-gray-800 dark:text-white mb-1">Profil & Pengaturan</h3>
            <p class="text-xs text-gray-500 leading-relaxed">Ubah username, avatar, dan pengaturan suara/grafis game Anda.</p>
            <span class="inline-block mt-4 text-xs font-bold text-amber-600 dark:text-amber-400 group-hover:underline">Buka Profil →</span>
        </a>
    </div>
</div>
@endsection
