<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): User
    {
        return User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'], // already hashed by cast
            'role'      => 'player',
            'is_active' => true,
        ]);
    }

    public function attempt(array $credentials, bool $remember = false): User
    {
        $key = 'login.' . Str::lower($credentials['email']) . '.' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        RateLimiter::clear($key);
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ]);
        }

        $user->update(['last_login_at' => now(), 'last_seen_at' => now()]);
        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}
