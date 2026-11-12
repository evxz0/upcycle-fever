@extends('layouts.app')
@section('title', 'Trial Tamu')
@section('content')
<div class="text-center py-12" x-data="{ completed: false }">
    <div x-show="!completed">
        <h1 class="text-3xl font-bold text-amber-600 mb-2">🎮 Mode Tamu – Stage Trial</h1>
        <p class="text-gray-500 mb-6">Kamu sedang memainkan stage trial. Ini adalah satu-satunya stage yang dapat dimainkan tanpa akun.</p>
        <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 max-w-sm mx-auto mb-6">
            <div class="text-6xl mb-4">🏭</div>
            <p class="text-gray-600 mb-4">Konten game akan hadir di sini. Ini placeholder stage trial.</p>
            <button @click="completed = true; completeTrialApi()"
                class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-2 rounded-lg transition">
                Selesaikan Stage
            </button>
        </div>
    </div>

    <div x-show="completed" x-cloak class="max-w-md mx-auto">
        <h1 class="text-3xl font-bold text-green-700 mb-2">🎉 Stage Trial Selesai!</h1>
        <p class="text-gray-600 mb-6">Daftar akun gratis untuk lanjut ke semua stage dan simpan progress kamu.</p>
        <div class="flex flex-col gap-3">
            <a href="{{ route('register') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl text-lg transition">
                Daftar Sekarang – Gratis
            </a>
            <a href="{{ route('login') }}" class="border border-green-600 text-green-600 hover:bg-green-50 font-semibold px-6 py-3 rounded-xl transition">
                Sudah punya akun? Masuk
            </a>
            <a href="{{ route('home') }}" class="text-sm text-gray-400 hover:underline mt-2">Kembali ke beranda</a>
        </div>
    </div>
</div>

<script>
function completeTrialApi() {
    fetch('{{ route("guest.trial.complete") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' }
    });
}
</script>
@endsection
