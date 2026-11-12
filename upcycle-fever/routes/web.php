<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', fn() => view('home'))->name('home');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Guest trial
Route::get('/guest/start', [AuthController::class, 'startGuest'])->name('guest.start');
Route::get('/game/trial', fn() => view('game.trial'))->name('game.trial');
Route::post('/game/trial/complete', [AuthController::class, 'completeTrial'])->name('guest.trial.complete');
Route::get('/game/check-trial', [AuthController::class, 'checkGuestTrial'])->name('guest.check');

// Player
Route::middleware('auth')->group(function () {
    Route::get('/game/lobby', fn() => view('game.lobby'))->name('game.lobby');
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/players', [AdminController::class, 'players'])->name('players');
    Route::patch('/players/{user}/toggle', [AdminController::class, 'toggleActive'])->name('players.toggle');
});
