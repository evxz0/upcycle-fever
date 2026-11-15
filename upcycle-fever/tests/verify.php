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
echo "\n7. PBI-004: Waste Materials Catalog (Master Data Bahan Limbah)\n";
$invService = new \App\Services\Game\InventoryService();
$allMaterials = $invService->getMaterials();
it("Master data materials populated (count >= 8)", $allMaterials->count() >= 8);

$plasticItems = $invService->getMaterials(['category' => 'Plastik']);
it("Category filtering returns only matching category", $plasticItems->isNotEmpty() && $plasticItems->every(fn($m) => $m->category === 'Plastik'));

$searchItems = $invService->getMaterials(['search' => 'Aluminium']);
it("Search by keyword finds target material", $searchItems->count() >= 1 && str_contains($searchItems->first()->name, 'Aluminium'));

// Technical Task 1: Create material data with validation
$createdMat = $invService->createMaterial([
    'name'        => 'Gelas Plastik PP Bekas',
    'category'    => 'Plastik',
    'type'        => 'Anorganik',
    'description' => 'Gelas minuman boba/kopi sekali pakai.',
    'icon'        => '🥤',
    'eco_impact'  => 18,
]);
it("Technical Task: Dapat menambah data sampah baru", $createdMat !== null && $createdMat->exists);
it("Bahan limbah memiliki atribut lengkap (name, category, icon, eco_impact)", 
    $createdMat->name === 'Gelas Plastik PP Bekas' && $createdMat->eco_impact === 18 && $createdMat->icon === '🥤');

// -------------------------------------------------------------
echo "\n8. PBI-004: Inventory & Workbench Slot Selection (Acceptance Criteria 1 & 2)\n";
$playerInventory = $invService->getUserInventory($player->id);
it("Pemain memiliki inventaris bahan limbah (AC 1)", $playerInventory->isNotEmpty());

$firstInv = $playerInventory->first();
$initialQty = $firstInv->quantity;

// Simulasi AC 1: Pemain memindahkan bahan baku dari inventaris ke slot workbench
$deducted = $invService->deductFromInventory($player->id, $firstInv->material_id, 1);
it("Pemain dapat memindahkan bahan baku dari inventaris (stok berkurang) (AC 1)", $deducted);

$updatedInv = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $firstInv->material_id)->first();
it("Stok inventaris terverifikasi berkurang setelah dipindahkan", ($updatedInv?->quantity ?? 0) === ($initialQty - 1));

// Simulasi AC 2: Slot workbench menampilkan preview bahan terpilih sebelum tindakan perakitan
$workbenchValidation = $invService->validateWorkbench([$firstInv->material_id, $createdMat->id]);
it("Slot workbench menampilkan preview bahan terpilih (AC 2)", 
    $workbenchValidation['valid'] === true && 
    $workbenchValidation['count'] === 2 && 
    $workbenchValidation['total_eco_impact'] > 0);

// Kembalikan stok inventaris
$invService->addToInventory($player->id, $firstInv->material_id, 1);

// -------------------------------------------------------------
echo "\n9. Blade Views Compiling (including Materials Catalog & Workbench)\n";
$views = [
    'home', 
    'auth.login', 
    'auth.register', 
    'game.lobby', 
    'game.trial', 
    'admin.dashboard', 
    'admin.players',
    'materials.index',
    'game.workbench'
];
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
        } elseif ($v === 'materials.index') {
            view($v, [
                'materials' => $allMaterials,
                'categories' => ['Semua', 'Plastik', 'Kertas/Karton', 'Logam', 'Tekstil', 'Kaca'],
                'category' => null,
                'search' => null,
            ])->render();
        } elseif ($v === 'game.workbench') {
            view($v, [
                'inventory' => $playerInventory,
                'tools' => \App\Models\Tool::all(),
                'recipes' => \App\Models\Recipe::with(['tool', 'ingredients'])->get(),
            ])->render();
        } else {
            view($v)->render();
        }
    } catch (\Throwable $e) {
        $viewsOk = false;
        echo "  [FAIL View: $v]: " . $e->getMessage() . "\n";
    }
}
it("All 9 Blade views (including materials catalog & workbench) render without errors", $viewsOk);

// -------------------------------------------------------------
echo "\n10. PBI-005: Recipe Engine & Tool Selection (Acceptance Criteria 1 & 2)\n";
$craftingService = new \App\Services\Game\CraftingService($invService);

$tools = $craftingService->getTools();
it("Master data tools populated (count >= 4)", $tools->count() >= 4);

$recipes = $craftingService->getRecipes();
it("Master data recipes populated with tools and ingredients (count >= 5)", $recipes->count() >= 5);

// Test tool & materials
$scissors = \App\Models\Tool::where('slug', 'gunting-serbaguna')->first();
$glueGun  = \App\Models\Tool::where('slug', 'lem-tembak-panas')->first();
$botolPet = \App\Models\Material::where('slug', 'botol-plastik-pet')->first();
$kain     = \App\Models\Material::where('slug', 'kain-perca-denim-katun')->first();
$kardus   = \App\Models\Material::where('slug', 'kardus-karton-bekas')->first();

it("Required test tools and materials exist", $scissors && $glueGun && $botolPet && $kain && $kardus);

// Valid recipe formula: Botol PET + Kain Perca + Gunting => Pot Gantung
$matchedRecipe = $craftingService->findMatchingRecipe([$botolPet->id, $kain->id], $scissors->id);
it("Recipe engine matches valid combination: Botol PET + Kain Perca + Gunting", 
    $matchedRecipe !== null && $matchedRecipe->slug === 'pot-gantung-tanaman-hias');

// Invalid tool with same materials
$wrongToolRecipe = $craftingService->findMatchingRecipe([$botolPet->id, $kain->id], $glueGun->id);
it("Recipe engine rejects valid materials with WRONG tool (AC 2)", $wrongToolRecipe === null);

// Incomplete materials with correct tool
$incompleteRecipe = $craftingService->findMatchingRecipe([$botolPet->id], $scissors->id);
it("Recipe engine rejects incomplete ingredients (AC 2)", $incompleteRecipe === null);

// Give player plenty of materials for crafting test
$invService->addToInventory($player->id, $botolPet->id, 5);
$invService->addToInventory($player->id, $kain->id, 5);

$qtyBotolBefore = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $botolPet->id)->value('quantity');
$qtyKainBefore  = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $kain->id)->value('quantity');

// AC 2 Test: Failed crafting does NOT deduct inventory
$failResult = $craftingService->craft($player->id, [$botolPet->id, $kardus->id], $scissors->id);
$qtyBotolAfterFail = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $botolPet->id)->value('quantity');
$qtyKainAfterFail  = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $kain->id)->value('quantity');

it("AC 2: Gagal merakit menghasilkan feedback kegagalan (success: false)", 
    $failResult['success'] === false && !empty($failResult['message']));
it("AC 2: Gagal merakit TIDAK mengurangi stok bahan inventaris pemain", 
    $qtyBotolAfterFail === $qtyBotolBefore && $qtyKainAfterFail === $qtyKainBefore);

// AC 1 Test: Successful crafting produces valid product and deducts inventory
$successResult = $craftingService->craft($player->id, [$botolPet->id, $kain->id], $scissors->id);
$qtyBotolAfterSuccess = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $botolPet->id)->value('quantity');
$qtyKainAfterSuccess  = \App\Models\Inventory::where('user_id', $player->id)->where('material_id', $kain->id)->value('quantity');

it("AC 1: Berhasil merakit menghasilkan status produk valid (success: true)", 
    $successResult['success'] === true && isset($successResult['product']['name']));
it("AC 1: Produk memiliki detail lengkap (name, eco_points, coin_reward, tool_name)", 
    $successResult['product']['name'] === 'Pot Gantung Tanaman Hias' &&
    $successResult['product']['eco_points'] === 35 &&
    $successResult['product']['coin_reward'] === 60 &&
    $successResult['product']['tool_name'] === $scissors->name);
it("AC 1: Bahan yang dirakit terpotong tepat dari inventaris pemain", 
    $qtyBotolAfterSuccess === ($qtyBotolBefore - 1) && 
    $qtyKainAfterSuccess === ($qtyKainBefore - 1));

// Controller API endpoint check
$craftController = new \App\Http\Controllers\CraftingController($craftingService);

$apiReqValid = Request::create('/api/workbench/craft', 'POST', [
    'material_ids' => [$botolPet->id, $kain->id],
    'tool_id'      => $scissors->id,
]);
$apiReqValid->setUserResolver(fn() => $player);
$responseValid = $craftController->apiCraft($apiReqValid);
it("API POST /api/workbench/craft returns JSON response with status 200", 
    $responseValid->getStatusCode() === 200 && $responseValid->getData(true)['success'] === true);

$apiReqInvalid = Request::create('/api/workbench/craft', 'POST', [
    'material_ids' => [$botolPet->id],
    'tool_id'      => $scissors->id,
]);
$apiReqInvalid->setUserResolver(fn() => $player);
$responseInvalid = $craftController->apiCraft($apiReqInvalid);
it("API POST /api/workbench/craft handles invalid recipe gracefully with HTTP 422", 
    $responseInvalid->getStatusCode() === 422 && $responseInvalid->getData(true)['success'] === false);

// -------------------------------------------------------------
echo "\n11. PBI-006: Menyerahkan Produk ke Pelanggan (Acceptance Criteria 1 & 2)\n";
$servingService = new \App\Services\Game\ServingService();

// Generate active customers
$customers = $servingService->getActiveCustomers($player->id);
it("Customer queue spawns active customers (count = 3)", count($customers) === 3);

$firstCustomer = $customers[0];
it("Customer has complete attributes (id, name, avatar, recipe_id, patience)", 
    !empty($firstCustomer['customer_name']) && 
    !empty($firstCustomer['customer_avatar']) && 
    !empty($firstCustomer['recipe_id']) && 
    $firstCustomer['patience_max'] > 0);

// AC 1: Negative test - serving wrong product
$wrongRecipeId = ($firstCustomer['recipe_id'] === $matchedRecipe->id) ? ($matchedRecipe->id + 999) : 99999;
$wrongServeResult = $servingService->serveProduct($player->id, $firstCustomer['id'], $wrongRecipeId);
it("AC 1: Menyerahkan produk yang salah ditolak sistem (success: false)", 
    $wrongServeResult['success'] === false && !empty($wrongServeResult['message']));

// AC 1: Positive test - serving matching product before patience runs out
$targetRecipeId = $firstCustomer['recipe_id'];
$coinsBefore = $player->fresh()->coins;
$ecoBefore   = $player->fresh()->eco_points;

$serveSuccess = $servingService->serveProduct($player->id, $firstCustomer['id'], $targetRecipeId);
it("AC 1: Produk jadi dapat diserahkan ke pelanggan sebelum batas kesabaran habis (success: true)", 
    $serveSuccess['success'] === true && !empty($serveSuccess['reward']));

$playerAfter = $player->fresh();
it("AC 1: Penyerahan sukses menambah koin dan eco-points pemain", 
    $playerAfter->coins > $coinsBefore && $playerAfter->eco_points > $ecoBefore);

// AC 2: Order marked completed & customer replaced by new customer from queue
$completedOrder = \App\Models\CustomerOrder::find($firstCustomer['id']);
it("AC 2: Pesanan ditandai selesai (status: completed)", $completedOrder->status === 'completed');
it("AC 2: Pelanggan meninggalkan antrean dan digantikan pelanggan berikutnya", 
    !empty($serveSuccess['new_customer']) && 
    $serveSuccess['new_customer']['id'] !== $firstCustomer['id'] &&
    $serveSuccess['new_customer']['status'] === 'waiting');

// Active customers count remains 3 after replacement
$customersAfterServe = $servingService->getActiveCustomers($player->id);
it("Slot antrean studio tetap terisi 3 pelanggan aktif", count($customersAfterServe) === 3);

// AC 2 Expire test: customer leaves when patience runs out and slot is replaced
$secondCustomer = $customersAfterServe[1];
$expireResult = $servingService->expireCustomer($player->id, $secondCustomer['id']);
it("AC 2: Pelanggan kehabisan waktu meninggalkan antrean dan digantikan baru", 
    $expireResult['success'] === true && 
    !empty($expireResult['new_customer']) && 
    $expireResult['new_customer']['id'] !== $secondCustomer['id']);

$expiredOrder = \App\Models\CustomerOrder::find($secondCustomer['id']);
it("Status pelanggan yang ditinggal berubah menjadi expired", $expiredOrder->status === 'expired');

// Controller API endpoint tests for PBI-006
$servingController = new \App\Http\Controllers\ServingController($servingService);

$apiCustomersReq = Request::create('/api/game/customers', 'GET');
$apiCustomersReq->setUserResolver(fn() => $player);
$resCustomers = $servingController->apiCustomers();
it("API GET /api/game/customers returns 200 with active customers array", 
    $resCustomers->getStatusCode() === 200 && count($resCustomers->getData(true)['data']) === 3);

$activeCustNow = $resCustomers->getData(true)['data'][0];
$apiServeReq = Request::create('/api/game/serve', 'POST', [
    'order_id'  => $activeCustNow['id'],
    'recipe_id' => $activeCustNow['recipe_id'],
]);
$apiServeReq->setUserResolver(fn() => $player);
$resServe = $servingController->apiServe($apiServeReq);
it("API POST /api/game/serve processes valid serving with HTTP 200", 
    $resServe->getStatusCode() === 200 && $resServe->getData(true)['success'] === true);

// Verify workbench blade view compiles with new customer variables
$workbenchViewCompiled = true;
try {
    view('game.workbench', [
        'inventory' => $playerInventory,
        'tools'     => \App\Models\Tool::all(),
        'recipes'   => \App\Models\Recipe::with(['tool', 'ingredients'])->get(),
        'customers' => $servingService->getActiveCustomers($player->id),
        'user'      => $player,
    ])->render();
} catch (\Throwable $e) {
    $workbenchViewCompiled = false;
    echo "  [FAIL Workbench View]: " . $e->getMessage() . "\n";
}
it("Workbench Blade view compiles and renders successfully with customers and serving plates", $workbenchViewCompiled);

// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "TOTAL: $pass PASSED, $fail FAILED\n";
echo "STATUS: " . ($fail === 0 ? "ALL ACCEPTANCE CRITERIA MET" : "FAILURES DETECTED") . "\n";
echo "=======================================================\n";

exit($fail > 0 ? 1 : 0);
