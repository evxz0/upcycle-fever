@extends('layouts.app')
@section('title', 'Lobi Game')
@section('content')
<div class="text-center py-12">
    <h1 class="text-3xl font-bold text-green-700 mb-2">Lobi Game</h1>
    <p class="text-gray-500 mb-8">Selamat datang, {{ auth()->user()?->name ?? 'Pemain' }}! Konten stage akan hadir berikutnya.</p>
    <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 max-w-sm mx-auto">
        <div class="text-6xl mb-4">🏭</div>
        <p class="text-gray-600">Studio Anda siap! Tahap gameplay sedang dikembangkan.</p>
    </div>
</div>
@endsection
