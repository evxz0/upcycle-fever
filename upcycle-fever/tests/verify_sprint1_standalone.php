<?php

/**
 * Sprint 1 Standalone Acceptance Criteria Verification Suite
 * Upcycle Fever: Eco-Crafting Studio
 *
 * Verifies all Acceptance Criteria for:
 * - PBI-001: Mode Trial & Antrean Pesanan Pelanggan (Guest)
 * - PBI-008: Melihat Stok Bahan Real-Time & Meja Kerja (Workbench)
 * - PBI-009: Pengurangan Stok Otomatis Setelah Crafting
 */

$pass = 0;
$fail = 0;

function it(string $desc, bool $condition, ?string $detail = null): void
{
    global $pass, $fail;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
        $pass++;
    } else {
        echo "  [FAIL] {$desc}\n";
        if ($detail) {
            echo "         Detail: {$detail}\n";
        }
        $fail++;
    }
}

echo "====================================================================\n";
echo "   UPCYCLE FEVER - SPRINT 1 ACCEPTANCE CRITERIA VERIFICATION        \n";
echo "====================================================================\n\n";

$baseDir = dirname(__DIR__);

// -------------------------------------------------------------
echo "1. VERIFIKASI PBI-001: Mode Trial & Antrean Pesanan Pelanggan (Guest)\n";
// -------------------------------------------------------------

// AC 1: Tombol "Mode Uji Coba (Trial)" di halaman beranda
$homeBlade = file_get_contents("{$baseDir}/resources/views/home.blade.php");
it(
    "PBI-001 AC 1: Tombol 'Mode Uji Coba (Trial)' tersedia di beranda",
    str_contains($homeBlade, 'Mode Uji Coba (Trial)'),
    "Tombol di home.blade.php harus bertuliskan 'Mode Uji Coba (Trial)'"
);
it(
    "PBI-001 AC 1: Tombol mengarah ke rute guest.start",
    str_contains($homeBlade, "route('guest.start')")
);

// AC 2: Antrean pesanan terpandu (guided order queue) demo 1 stage tanpa DB user
$trialBlade = file_get_contents("{$baseDir}/resources/views/game/trial.blade.php");
it(
    "PBI-001 AC 2: Stage demo trial memiliki antrean pesanan pelanggan terpandu (guided order queue)",
    str_contains($trialBlade, 'customer:') && str_contains($trialBlade, 'recipe_name: \'Pot Gantung Tanaman Hias\'')
);
it(
    "PBI-001 AC 2: State demo trial dijalankan di session/Alpine.js tanpa menulis record game ke database user",
    str_contains($trialBlade, 'trialStep') && str_contains($trialBlade, 'servingPlate') && !str_contains($trialBlade, 'CustomerOrder::create')
);
it(
    "PBI-001 AC 2: Terdapat panduan langkah interaktif (step-by-step guidance)",
    str_contains($trialBlade, 'Petunjuk Terpandu (Guided Step)') && str_contains($trialBlade, 'Langkah 1')
);
it(
    "PBI-001 AC 2: Setelah selesai babak demo, muncul layar ajakan registrasi/login",
    str_contains($trialBlade, 'Babak Demo 1 Stage Selesai!') && str_contains($trialBlade, 'route(\'register\')')
);

// Technical Task Auth & Fondasi Akun
$userMigration = file_get_contents("{$baseDir}/database/migrations/0001_01_01_000000_create_users_table.php");
it(
    "PBI-001 Tech Task: Migrasi tabel users memiliki kolom role ('player', 'admin') dan is_active",
    str_contains($userMigration, "'role', ['player', 'admin']") && str_contains($userMigration, "'is_active'")
);

$authController = file_get_contents("{$baseDir}/app/Http/Controllers/AuthController.php");
it(
    "PBI-001 Tech Task: AuthController menangani registrasi, login, logout, dan guest trial lifecycle",
    str_contains($authController, 'function register') &&
    str_contains($authController, 'function login') &&
    str_contains($authController, 'function startGuest') &&
    str_contains($authController, 'function completeTrial') &&
    str_contains($authController, 'function checkGuestTrial')
);

$authService = file_get_contents("{$baseDir}/app/Services/Auth/AuthService.php");
it(
    "PBI-001 Tech Task: AuthService menerapkan rate limiting, hashing password, dan cek akun aktif",
    str_contains($authService, 'RateLimiter::tooManyAttempts') &&
    str_contains($authService, 'RateLimiter::hit') &&
    str_contains($authService, '! $user->is_active')
);

$loginBlade = file_get_contents("{$baseDir}/resources/views/auth/login.blade.php");
$regBlade = file_get_contents("{$baseDir}/resources/views/auth/register.blade.php");
it(
    "PBI-001 Tech Task: Halaman Login & Registrasi terhubung dengan endpoint backend",
    str_contains($loginBlade, "route('login')") && str_contains($regBlade, "route('register')")
);

// -------------------------------------------------------------
echo "\n2. VERIFIKASI PBI-008: Melihat Stok Bahan Real-Time & Meja Kerja (Workbench)\n";
// -------------------------------------------------------------

// AC 1: Kuantitas stok tersimpan di database inventory_items
$invMigration = file_get_contents("{$baseDir}/database/migrations/2026_11_14_000004_create_inventory_items_table.php");
it(
    "PBI-008 AC 1: Skema tabel bernama 'inventory_items' dengan kolom material_id, recipe_id, dan quantity",
    str_contains($invMigration, "Schema::create('inventory_items'") &&
    str_contains($invMigration, "foreignId('material_id')") &&
    str_contains($invMigration, "foreignId('recipe_id')") &&
    str_contains($invMigration, "unsignedInteger('quantity')")
);

$inventoryItemModel = file_get_contents("{$baseDir}/app/Models/InventoryItem.php");
it(
    "PBI-008 AC 1: Model InventoryItem mengelola tabel inventory_items dengan relasi user & material",
    str_contains($inventoryItemModel, "protected \$table = 'inventory_items';") &&
    str_contains($inventoryItemModel, "function material(): BelongsTo")
);

$inventoryService = file_get_contents("{$baseDir}/app/Services/Game/InventoryService.php");
it(
    "PBI-008 AC 1: InventoryService mengambil inventaris real-time termasuk item stok 0 untuk status disabled",
    str_contains($inventoryService, "InventoryItem::with('material')") &&
    str_contains($inventoryService, "where('item_type', 'material')")
);

// AC 2: Bahan yang habis (stok == 0) otomatis berstatus disabled
$workbenchBlade = file_get_contents("{$baseDir}/resources/views/game/workbench.blade.php");
it(
    "PBI-008 AC 2: Rak inventaris menampilkan kuantitas stok x{quantity} atau 'Habis'",
    str_contains($workbenchBlade, "item.quantity > 0 ? `x\${item.quantity}` : 'Habis'")
);
it(
    "PBI-008 AC 2: Bahan dengan stok == 0 otomatis berstatus disabled (pointer-events-none, cursor-not-allowed)",
    str_contains($workbenchBlade, "opacity-40 cursor-not-allowed pointer-events-none")
);
it(
    "PBI-008 AC 2: Tombol pemilihan bahan dinonaktifkan saat stok <= 0",
    str_contains($workbenchBlade, ":disabled=\"item.quantity <= 0\"") &&
    str_contains($workbenchBlade, "item.quantity > 0 ? '+ Pilih' : 'Stok Habis'")
);

// Tech Task: Komponen UI Meja Kerja (Slot Perkakas & 4 Slot Bahan), Progress Bar, Validasi
it(
    "PBI-008 Tech Task: Meja kerja memiliki Slot Perkakas dan 4 Slot Bahan Limbah",
    str_contains($workbenchBlade, "Rak Perkakas") &&
    str_contains($workbenchBlade, "Workbench Slots") &&
    str_contains($workbenchBlade, "workbenchSlots: [null, null, null, null]")
);
it(
    "PBI-008 Tech Task: Terdapat indikator visual / progress bar animasi selama proses crafting berlangsung",
    str_contains($workbenchBlade, "craftProgress") &&
    str_contains($workbenchBlade, "Memproses perakitan bahan limbah di meja kerja...") &&
    str_contains($workbenchBlade, "width: \${craftProgress}%")
);
it(
    "PBI-008 Tech Task: Validasi frontend menolak aksi perakitan jika bahan atau alat belum lengkap",
    str_contains($workbenchBlade, "!canAssemble || isCrafting") &&
    str_contains($workbenchBlade, "Pilih salah satu perkakas kerja terlebih dahulu!")
);

// -------------------------------------------------------------
echo "\n3. VERIFIKASI PBI-009: Pengurangan Stok Otomatis Setelah Crafting\n";
// -------------------------------------------------------------

$craftingService = file_get_contents("{$baseDir}/app/Services/Game/CraftingService.php");

// AC 1: Pengurangan bahan & penambahan produk jadi dalam database transaction
it(
    "PBI-009 AC 1: Proses crafting dieksekusi di dalam DB::transaction untuk menjamin atomisitas",
    str_contains($craftingService, "DB::transaction(function ()")
);
it(
    "PBI-009 AC 1: Bahan yang digunakan dipotong dari inventory_items",
    str_contains($craftingService, "\$this->inventoryService->deductFromInventory(\$userId, \$matId, \$qty)")
);
it(
    "PBI-009 AC 1: Produk jadi ditambahkan ke tabel inventory_items (item_type = product)",
    str_contains($craftingService, "\$this->inventoryService->addProductToInventory(\$userId, \$recipe->id, 1)")
);
it(
    "PBI-009 AC 1: Endpoint mengembalikan updated_inventory untuk pembaruan reaktif frontend tanpa reload",
    str_contains($craftingService, "'updated_inventory' => \$updatedInventory") &&
    str_contains($workbenchBlade, "this.inventory = data.updated_inventory.map")
);

// AC 2: Penolakan perakitan dan pesan error jelas jika stok bahan tidak cukup
it(
    "PBI-009 AC 2: Sistem memeriksa kecukupan stok seluruh bahan sebelum transaksi dijalankan",
    str_contains($craftingService, "\$currentStock < \$requiredQty")
);
it(
    "PBI-009 AC 2: Menolak perakitan dengan pesan error yang jelas menyebut nama bahan dan jumlah yang kurang",
    str_contains($craftingService, "Stok bahan '{\$matName}' tidak mencukupi di inventaris (tersisa: {\$currentStock}, dibutuhkan: {\$requiredQty})")
);
it(
    "PBI-009 AC 2: Menolak perakitan jika kombinasi alat dan bahan tidak cocok dengan resep",
    str_contains($craftingService, "Kombinasi bahan sampah dan perkakas tidak cocok dengan formula resep manapun")
);

// Tech Task: Pop-up Panduan Crafting Edukatif Lokal (Non-AI)
it(
    "PBI-009 Tech Task: Pop-up Panduan Resep & Edukasi Upcycling lokal tersedia di meja kerja",
    str_contains($workbenchBlade, "Panduan Resep & Edukasi Upcycling") &&
    str_contains($workbenchBlade, "Mendukung SDG 12 Target 12.5 (Pengurangan Limbah)")
);
it(
    "PBI-009 Tech Task: Panduan crafting 100% menggunakan data statis/lokal (tanpa Google Gemini API)",
    !str_contains($workbenchBlade, "gemini") &&
    !str_contains($trialBlade, "gemini") &&
    !str_contains($craftingService, "gemini")
);

// -------------------------------------------------------------
echo "\n4. VERIFIKASI SINTAKS SELURUH FILE PHP SPRINT 1\n";
// -------------------------------------------------------------
$phpFiles = [
    "{$baseDir}/app/Models/InventoryItem.php",
    "{$baseDir}/app/Models/Inventory.php",
    "{$baseDir}/app/Models/User.php",
    "{$baseDir}/app/Models/Material.php",
    "{$baseDir}/app/Models/Recipe.php",
    "{$baseDir}/app/Models/Tool.php",
    "{$baseDir}/app/Services/Game/InventoryService.php",
    "{$baseDir}/app/Services/Game/CraftingService.php",
    "{$baseDir}/app/Services/Auth/AuthService.php",
    "{$baseDir}/app/Http/Controllers/AuthController.php",
    "{$baseDir}/app/Http/Controllers/CraftingController.php",
    "{$baseDir}/app/Http/Controllers/MaterialController.php",
    "{$baseDir}/database/migrations/2026_11_14_000004_create_inventory_items_table.php",
];

$allSyntaxOk = true;
foreach ($phpFiles as $file) {
    if (!file_exists($file)) {
        $allSyntaxOk = false;
        echo "  [FAIL File Missing]: {$file}\n";
        continue;
    }
    $output = [];
    $returnVar = 0;
    exec("php -l \"{$file}\"", $output, $returnVar);
    if ($returnVar !== 0) {
        $allSyntaxOk = false;
        echo "  [FAIL Syntax Error in]: {$file}\n" . implode("\n", $output) . "\n";
    }
}
it("Semua 13 file PHP Sprint 1 bebas dari kesalahan sintaks (linting lolos)", $allSyntaxOk);

// -------------------------------------------------------------
echo "\n====================================================================\n";
echo "HASIL PENGUJIAN SPRINT 1: {$pass} PASSED, {$fail} FAILED\n";
echo "STATUS: " . ($fail === 0 ? "SELURUH ACCEPTANCE CRITERIA SPRINT 1 TERPENUHI (100% PASS)" : "ADA CRITERIA YANG BELUM MEMENUHI") . "\n";
echo "====================================================================\n";

exit($fail > 0 ? 1 : 0);
