<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\CraftingController;
use App\Http\Controllers\ServingController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', fn() => view('home'))->name('home');

// Katalog & Master Data Bahan Limbah (PBI-004)
Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');

// Meja Kerja (Workbench Crafting) (PBI-004 & PBI-005)
Route::get('/game/workbench', [MaterialController::class, 'workbench'])->name('game.workbench');

// API Endpoints Pengelolaan Bahan, Inventaris, dan Crafting (PBI-004 & PBI-005)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/materials', [MaterialController::class, 'apiIndex'])->name('materials.index');
    Route::post('/materials', [MaterialController::class, 'apiStore'])->name('materials.store');
    Route::get('/inventory', [MaterialController::class, 'apiInventory'])->name('inventory.index');
    Route::post('/workbench/validate', [MaterialController::class, 'apiValidateWorkbench'])->name('workbench.validate');
    Route::post('/workbench/craft', [CraftingController::class, 'apiCraft'])->name('workbench.craft');
    Route::get('/recipes', [CraftingController::class, 'apiRecipes'])->name('recipes.index');
    Route::get('/tools', [CraftingController::class, 'apiTools'])->name('tools.index');

    // Penyerahan Produk ke Pelanggan (PBI-006)
    Route::get('/game/customers', [ServingController::class, 'apiCustomers'])->name('game.customers');
    Route::post('/game/serve', [ServingController::class, 'apiServe'])->name('game.serve');
    Route::post('/game/customers/expire', [ServingController::class, 'apiExpire'])->name('game.customers.expire');

    // Sistem Manajemen Waktu Stage & Spawn Pesanan Berkala (PBI-010)
    Route::post('/game/stage/start', [StageController::class, 'apiStart'])->name('game.stage.start');
    Route::post('/game/stage/spawn', [StageController::class, 'apiSpawnOrder'])->name('game.stage.spawn');
    Route::post('/game/stage/finish', [StageController::class, 'apiFinish'])->name('game.stage.finish');
});

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
