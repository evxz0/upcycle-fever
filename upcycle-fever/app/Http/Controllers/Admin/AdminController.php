<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_players'   => User::where('role', 'player')->count(),
            'active_today'    => User::where('role', 'player')->whereDate('last_seen_at', today())->count(),
            'active_7d'       => User::where('role', 'player')->where('last_seen_at', '>=', now()->subDays(7))->count(),
            'active_30d'      => User::where('role', 'player')->where('last_seen_at', '>=', now()->subDays(30))->count(),
            'guest_started'   => GuestSession::count(),
            'guest_completed' => GuestSession::whereNotNull('trial_completed_at')->count(),
            'guest_converted' => GuestSession::whereNotNull('converted_user_id')->count(),
            // shortcut: online count from DB sessions, upgrade to Reverb presence when WebSocket is set up
            'online_now'      => DB::table('sessions')->whereNotNull('user_id')
                                    ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    public function players(Request $request)
    {
        $players = User::where('role', 'player')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                   ->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(20);

        return view('admin.players', compact('players'));
    }

    public function toggleActive(User $user)
    {
        abort_if($user->isAdmin(), 403, 'Cannot deactivate admin.');
        $user->update(['is_active' => ! $user->is_active]);
        return back()->with('success', 'Status akun diperbarui.');
    }
}
