<?php

namespace App\Http\Middleware;

use App\Services\Game\GuestTrialService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: Pastikan guest masih boleh bermain trial (1 stage).
 * Dipasang di rute yang hanya boleh diakses guest ATAU player yang sudah login.
 * Jika guest sudah habis trial-nya, kembalikan 403 + kode LOGIN_REQUIRED.
 */
class EnsureGuestTrialAllowed
{
    public function __construct(private GuestTrialService $guestService) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Jika sudah login sebagai player/admin — boleh lanjut
        if (auth()->check()) {
            return $next($request);
        }

        $token = session('guest_token');

        if (!$token || !$this->guestService->canPlayTrial($token)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'LOGIN_REQUIRED',
                    'message' => 'Trial selesai. Silakan login atau daftar untuk melanjutkan.',
                ], 403);
            }

            return redirect()->route('register')
                ->with('info', 'Trial selesai! Daftar akun gratis untuk menikmati semua stage.');
        }

        return $next($request);
    }
}
