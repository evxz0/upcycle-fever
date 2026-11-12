<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuthService;
use App\Services\Game\GuestTrialService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private GuestTrialService $guest,
    ) {}

    public function showLogin() { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $user = $this->auth->attempt(
            ['email' => $data['email'], 'password' => $data['password']],
            $request->boolean('remember')
        );

        // mark guest converted if applicable
        if ($token = session('guest_token')) {
            $this->guest->markConverted($token, $user->id);
            session()->forget('guest_token');
        }

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('game.lobby'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $this->auth->register($data);
        \Illuminate\Support\Facades\Auth::login($user);

        if ($token = session('guest_token')) {
            $this->guest->markConverted($token, $user->id);
            session()->forget('guest_token');
        }

        return redirect()->route('game.lobby');
    }

    public function logout(Request $request)
    {
        $this->auth->logout();
        return redirect()->route('login');
    }

    // Guest start
    public function startGuest()
    {
        $token = $this->guest->startGuest();
        return redirect()->route('game.trial');
    }

    // Guest complete trial (dipanggil dari game controller nanti)
    public function completeTrial(Request $request)
    {
        $token = session('guest_token');
        if ($token && $this->guest->canPlayTrial($token)) {
            $this->guest->completeTrial($token);
        }
        return response()->json(['status' => 'completed']);
    }

    // Check if guest can play (dipanggil dari game controller)
    public function checkGuestTrial(Request $request)
    {
        $token = session('guest_token');
        if (! $token || ! $this->guest->canPlayTrial($token)) {
            return response()->json(['error' => 'LOGIN_REQUIRED'], 403);
        }
        return response()->json(['status' => 'ok']);
    }
}
