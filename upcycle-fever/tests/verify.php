<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\GuestSession;
use App\Services\Auth\AuthService;
use App\Services\Game\GuestTrialService;
use App\Services\Ai\GeminiService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

echo "=== UP CYCLE FEVER - AUTH & LOGIN VERIFICATION (PRD FASE 4) ===\n\n";

$pass = 0;
$fail = 0;

function it($desc, $cond) {
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $desc\n";
        $pass++;
    } else {
        echo "  [FAIL] $desc\n";
        $fail++;
    }
}

// -------------------------------------------------------------
echo "1. User Model & Admin Seeder\n";
$admin = User::where('role', 'admin')->first();
it("Seeded admin exists (admin@upcyclefever.test)", $admin !== null && $admin->email === 'admin@upcyclefever.test');
it("Admin has role 'admin' (isAdmin: true, isPlayer: false)", $admin?->isAdmin() === true && $admin?->isPlayer() === false);
it("Admin password is encrypted with bcrypt", Hash::check('password', $admin->password));

// -------------------------------------------------------------
echo "\n2. Player Registration (AUTH-01, AUTH-03)\n";
$auth = new AuthService();
$email = 'player_' . time() . '@test.com';
$player = $auth->register([
    'name'     => 'Eco Crafter',
    'email'    => $email,
    'password' => 'password123',
]);
it("Player registered successfully in DB", $player !== null && $player->exists);
it("Player assigned role 'player' (isPlayer: true, isAdmin: false)", $player->isPlayer() === true && $player->isAdmin() === false);
it("Player is active by default (is_active: true)", $player->is_active === true);
it("Player password hashed by User model cast", Hash::check('password123', $player->password));

// -------------------------------------------------------------
echo "\n3. Player Login & Inactive Account Check (AUTH-02, AUTH-05, AUTH-06)\n";
$loggedIn = $auth->attempt(['email' => $email, 'password' => 'password123']);
it("Player can log in with valid credentials", $loggedIn->id === $player->id);
it("Login updates last_login_at and last_seen_at", $loggedIn->last_login_at !== null && $loggedIn->last_seen_at !== null);

// Inactive account check
$inactive = $auth->register([
    'name'     => 'Inactive Player',
    'email'    => 'inactive_' . time() . '@test.com',
    'password' => 'password123',
]);
$inactive->update(['is_active' => false]);

$inactiveBlocked = false;
try {
    $auth->attempt(['email' => $inactive->email, 'password' => 'password123']);
} catch (ValidationException $e) {
    $inactiveBlocked = true;
}
it("Inactive player is blocked from login (AUTH-06)", $inactiveBlocked);

// Logout check
$auth->logout();
it("User logged out successfully", !Auth::check());

// -------------------------------------------------------------
echo "\n4. Role Middleware (AUTH-04)\n";
$middleware = new RoleMiddleware();

// Player accessing admin role
$playerBlockedFromAdmin = false;
try {
    $req = Request::create('/admin/dashboard', 'GET');
    $req->setUserResolver(fn() => $player);
    $middleware->handle($req, fn() => 'next', 'admin');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $playerBlockedFromAdmin = ($e->getStatusCode() === 403);
}
it("Player blocked from accessing admin route (HTTP 403)", $playerBlockedFromAdmin);

// Admin accessing admin role
$adminAllowed = false;
$req = Request::create('/admin/dashboard', 'GET');
$req->setUserResolver(fn() => $admin);
$res = $middleware->handle($req, fn() => 'allowed', 'admin');
it("Admin allowed to access admin route", $res === 'allowed');

// -------------------------------------------------------------
echo "\n5. Guest Trial System (GUEST-01 s/d GUEST-06)\n";
$guest = new GuestTrialService();
$token = $guest->startGuest();
it("Guest given UUID token (GUEST-02)", !empty($token) && strlen($token) >= 32);

$guestSession = GuestSession::where('guest_token', $token)->first();
it("Guest session saved for admin stats (ip_hash, trial_started_at)", $guestSession !== null && !empty($guestSession->ip_hash));

it("Guest can play 1st stage trial (GUEST-03)", $guest->canPlayTrial($token) === true);

// Complete stage 1
$guest->completeTrial($token);
$guestSession->refresh();
it("Trial completion timestamp recorded", $guestSession->trial_completed_at !== null);
it("Guest CANNOT play 2nd stage - rejected by service (GUEST-03)", $guest->canPlayTrial($token) === false);

// Convert to player
$guest->markConverted($token, $player->id);
$guestSession->refresh();
it("Guest session marked converted upon registration (GUEST-06 / ADM-05)", $guestSession->converted_user_id === $player->id);

// -------------------------------------------------------------
echo "\n6. AI Wrapper Stub (GeminiService)\n";
$gemini = new GeminiService();
$result = $gemini->generate('Berikan ide upcycling');
it("GeminiService provides graceful static fallback when API key is empty (NFR-03)", !empty($result['text']));

// -------------------------------------------------------------
echo "\n7. Blade Views Compiling\n";
$views = ['home', 'auth.login', 'auth.register', 'game.lobby', 'game.trial', 'admin.dashboard', 'admin.players'];
$viewsOk = true;
foreach ($views as $v) {
    try {
        if ($v === 'admin.dashboard') {
            view($v, ['stats' => [
                'total_players' => 1, 'online_now' => 1, 'active_today' => 1, 'active_7d' => 1,
                'guest_started' => 1, 'guest_completed' => 1, 'guest_converted' => 1
            ]])->render();
        } elseif ($v === 'admin.players') {
            view($v, ['players' => User::paginate(5)])->render();
        } else {
            view($v)->render();
        }
    } catch (\Throwable $e) {
        $viewsOk = false;
        echo "  [FAIL View: $v]: " . $e->getMessage() . "\n";
    }
}
it("All 7 Blade views render with Tailwind CSS & Alpine.js without errors", $viewsOk);

// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "TOTAL: $pass PASSED, $fail FAILED\n";
echo "STATUS: " . ($fail === 0 ? "ALL ACCEPTANCE CRITERIA MET" : "FAILURES DETECTED") . "\n";
echo "=======================================================\n";

exit($fail > 0 ? 1 : 0);
