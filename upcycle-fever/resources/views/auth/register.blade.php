@extends('layouts.auth')
@section('title', 'Daftar')
@section('content')
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 text-center">Buat Akun</h1>

    @if(isset($errors) && $errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
            <input type="password" name="password" required
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" required
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
        </div>
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-lg transition">
            Buat Akun
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-green-600 hover:underline font-medium">Masuk</a>
    </p>
</div>
@endsection
