@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
<div class="text-center py-16">
    <h1 class="text-5xl font-bold text-green-700 mb-4">🌿 Upcycle Fever</h1>
    <p class="text-xl text-gray-600 mb-2">Eco-Crafting Studio</p>
    <p class="text-gray-500 max-w-lg mx-auto mb-10">Kelola studio kerajinan, daur ulang barang bekas, dan selesaikan pesanan sebelum pelanggan kehabisan kesabaran!</p>

    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        @auth
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl text-lg transition">
                    Ke Dashboard Admin
                </a>
            @else
                <a href="{{ route('game.lobby') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl text-lg transition">
                    Lanjutkan Game
                </a>
            @endif
        @else
            <a href="{{ route('guest.start') }}" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold px-8 py-3 rounded-xl text-lg transition shadow-md flex items-center justify-center gap-2">
                <span>🎮</span> Mode Uji Coba (Trial)
            </a>
            <a href="{{ route('register') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl text-lg transition">
                Daftar & Main Penuh
            </a>
        @endauth
    </div>

    <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-6 text-left max-w-3xl mx-auto">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="text-3xl mb-2">♻️</div>
            <h3 class="font-bold text-gray-800">Upcycle</h3>
            <p class="text-sm text-gray-500 mt-1">Ubah barang bekas jadi karya bernilai tinggi</p>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="text-3xl mb-2">⏱️</div>
            <h3 class="font-bold text-gray-800">Time Management</h3>
            <p class="text-sm text-gray-500 mt-1">Layani pelanggan sebelum kesabaran mereka habis</p>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="text-3xl mb-2">🌍</div>
            <h3 class="font-bold text-gray-800">SDG 12</h3>
            <p class="text-sm text-gray-500 mt-1">Belajar ekonomi sirkular sambil bermain</p>
        </div>
    </div>
</div>
@endsection
