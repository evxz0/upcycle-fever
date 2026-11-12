@extends('layouts.app')
@section('title', 'Dashboard Admin')
@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Dashboard Admin</h1>
    <p class="text-gray-500 text-sm mt-1">Monitoring pemain dan sesi game</p>
</div>

{{-- Stats grid --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach([
        ['label' => 'Total Pemain', 'value' => $stats['total_players'], 'icon' => '👥'],
        ['label' => 'Online Sekarang', 'value' => $stats['online_now'], 'icon' => '🟢'],
        ['label' => 'Aktif Hari Ini', 'value' => $stats['active_today'], 'icon' => '📅'],
        ['label' => 'Aktif 7 Hari', 'value' => $stats['active_7d'], 'icon' => '📆'],
    ] as $stat)
    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="text-2xl mb-1">{{ $stat['icon'] }}</div>
        <div class="text-3xl font-bold text-gray-800 dark:text-white">{{ $stat['value'] }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- Guest stats --}}
<div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
    <h2 class="font-bold text-gray-700 dark:text-gray-200 mb-4">Statistik Trial Tamu</h2>
    <div class="grid grid-cols-3 gap-4 text-center">
        <div>
            <div class="text-2xl font-bold text-amber-600">{{ $stats['guest_started'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Trial Dimulai</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-green-600">{{ $stats['guest_completed'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Trial Selesai</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-blue-600">{{ $stats['guest_converted'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Registrasi (Konversi)</div>
        </div>
    </div>
</div>

<div class="text-right">
    <a href="{{ route('admin.players') }}" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
        Kelola Pemain →
    </a>
</div>
@endsection
