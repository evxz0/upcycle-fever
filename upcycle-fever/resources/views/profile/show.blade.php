@extends('layouts.app')
@section('title', 'Profil Pemain')

@section('content')
<div class="max-w-2xl mx-auto py-10 px-4" x-data="profilePage()">

    {{-- Header Profil --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center text-3xl border-2 border-green-400"
                 x-text="selectedAvatar || '{{ $user->preferences['avatar'] ?? '🧑‍🌾' }}'">
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $user->name }}</h1>
                <p class="text-sm text-gray-500">{{ $user->email }}</p>
                <div class="flex gap-3 mt-1">
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">
                        {{ $user->role === 'admin' ? '🛡️ Admin' : '🎮 Player' }}
                    </span>
                    <span class="text-xs text-amber-600 font-semibold">💰 {{ $user->coins ?? 0 }} Koin</span>
                    <span class="text-xs text-emerald-600 font-semibold">🌱 {{ $user->eco_points ?? 0 }} Eco</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-300 text-green-800 rounded-xl px-4 py-3 mb-4 text-sm flex items-center gap-2">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-xl px-4 py-3 mb-4 text-sm">
        @foreach($errors->all() as $error)
            <p>⚠️ {{ $error }}</p>
        @endforeach
    </div>
    @endif

    {{-- Form Update Profil --}}
    <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Informasi Dasar --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-bold text-gray-700 dark:text-white mb-4 text-sm uppercase tracking-wide">📝 Informasi Dasar</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username / Nama Tampilan</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-green-400 outline-none transition"
                           placeholder="Nama tampilan kamu" required minlength="2" maxlength="50">
                </div>
            </div>
        </div>

        {{-- Pilih Avatar --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-bold text-gray-700 dark:text-white mb-4 text-sm uppercase tracking-wide">🎭 Avatar</h2>
            <input type="hidden" name="avatar" x-model="selectedAvatar">

            <div class="grid grid-cols-6 gap-2">
                @foreach(['🧑‍🌾','👨','👩','🧒','👵','🧔','👧','🧑‍🏫','👦','🦸','🧑‍🔬','🧑‍🎨','🧑‍💻','🧑‍🚀','🌿','♻️'] as $emoji)
                <button type="button"
                    @click="selectedAvatar = '{{ $emoji }}'"
                    :class="selectedAvatar === '{{ $emoji }}' ? 'ring-2 ring-green-500 bg-green-50' : 'bg-gray-50 hover:bg-green-50'"
                    class="text-2xl p-2 rounded-xl border border-gray-200 transition text-center">
                    {{ $emoji }}
                </button>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-2">Avatar dipilih: <span x-text="selectedAvatar"></span></p>
        </div>

        {{-- Pengaturan Suara & Grafis --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-bold text-gray-700 dark:text-white mb-4 text-sm uppercase tracking-wide">⚙️ Pengaturan Game</h2>

            <div class="space-y-4">
                {{-- Suara --}}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">🔊 Suara / Efek Suara</p>
                        <p class="text-xs text-gray-400">Aktifkan atau matikan efek suara game</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sound_enabled" value="1" class="sr-only peer"
                               {{ (($user->preferences['sound_enabled'] ?? true) ? 'checked' : '') }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-green-400 dark:peer-focus:ring-green-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-500"></div>
                    </label>
                </div>

                {{-- Kualitas Grafis --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">🖥️ Kualitas Grafis</label>
                    <select name="graphic_quality"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-green-400 outline-none transition">
                        <option value="low"    {{ ($user->preferences['graphic_quality'] ?? 'medium') === 'low'    ? 'selected' : '' }}>⬇️ Rendah (Low) — Hemat Baterai</option>
                        <option value="medium" {{ ($user->preferences['graphic_quality'] ?? 'medium') === 'medium' ? 'selected' : '' }}>➡️ Sedang (Medium) — Rekomendasi</option>
                        <option value="high"   {{ ($user->preferences['graphic_quality'] ?? 'medium') === 'high'   ? 'selected' : '' }}>⬆️ Tinggi (High) — Kualitas Penuh</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Ganti Password (Opsional) --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-bold text-gray-700 dark:text-white mb-4 text-sm uppercase tracking-wide">🔒 Ganti Password <span class="font-normal text-gray-400">(opsional)</span></h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password Saat Ini</label>
                    <input type="password" name="current_password"
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-green-400 outline-none transition"
                           placeholder="Kosongkan jika tidak ingin ubah password">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password Baru</label>
                    <input type="password" name="password"
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-green-400 outline-none transition"
                           placeholder="Minimal 8 karakter">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation"
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-green-400 outline-none transition"
                           placeholder="Ulangi password baru">
                </div>
            </div>
        </div>

        {{-- Tombol Simpan --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl text-sm transition shadow-md">
                💾 Simpan Perubahan
            </button>
            <a href="{{ route('game.lobby') }}"
               class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium rounded-xl text-sm transition">
                Kembali
            </a>
        </div>
    </form>
</div>

<script>
function profilePage() {
    return {
        selectedAvatar: '{{ $user->preferences['avatar'] ?? '🧑‍🌾' }}',
    }
}
</script>
@endsection
