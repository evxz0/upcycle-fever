<?php

namespace App\Services\Game;

use App\Models\GuestSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GuestTrialService
{
    private const TTL = 86400; // 24 jam

    public function startGuest(): string
    {
        $token = Str::uuid()->toString();
        Cache::put("guest:{$token}", ['trial_used' => false], self::TTL);

        GuestSession::create([
            'guest_token'      => $token,
            'trial_started_at' => now(),
            'ip_hash'          => hash('sha256', request()->ip()),
        ]);

        if (request()->hasSession()) {
            session(['guest_token' => $token]);
        }
        return $token;
    }

    public function canPlayTrial(string $token): bool
    {
        $data = Cache::get("guest:{$token}");
        return $data && ! $data['trial_used'];
    }

    public function completeTrial(string $token): void
    {
        $data = Cache::get("guest:{$token}", []);
        $data['trial_used'] = true;
        Cache::put("guest:{$token}", $data, self::TTL);

        GuestSession::where('guest_token', $token)
            ->whereNull('trial_completed_at')
            ->update(['trial_completed_at' => now()]);
    }

    public function markConverted(string $token, int $userId): void
    {
        GuestSession::where('guest_token', $token)
            ->whereNull('converted_user_id')
            ->update(['converted_user_id' => $userId]);
    }
}
