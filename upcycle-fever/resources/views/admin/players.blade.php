@extends('layouts.app')
@section('title', 'Daftar Pemain')
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Daftar Pemain</h1>
    <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 hover:underline">← Dashboard</a>
</div>

<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau email..."
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-green-500">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">Semua Status</option>
        <option value="active" @selected(request('status') === 'active')>Aktif</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
    </select>
    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-green-700">Filter</button>
</form>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
            <tr>
                <th class="px-4 py-3 text-left">Nama</th>
                <th class="px-4 py-3 text-left">Email</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Login Terakhir</th>
                <th class="px-4 py-3 text-left">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($players as $player)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ $player->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $player->email }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $player->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $player->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $player->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.players.toggle', $player) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs {{ $player->is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' }} hover:underline">
                            {{ $player->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada pemain ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $players->withQueryString()->links() }}</div>
@endsection
