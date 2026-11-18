@extends('layouts.app')
@section('title', 'Mode Uji Coba (Trial)')

@section('content')
<div x-data="{
    // State Demo Trial (Transient / Tanpa Database)
    completed: false,
    trialStep: 1, // 1: Pilih Bahan, 2: Pilih Perkakas, 3: Rakit, 4: Sajikan, 5: Selesai
    isCrafting: false,
    craftProgress: 0,
    toastMessage: '',
    toastType: 'info',

    // Antrean Pesanan Pelanggan Terpandu (Guided Order Queue - 1 Stage Demo)
    customer: {
        id: 'demo-1',
        name: 'Budi (Eco Enthusiast)',
        avatar: '👨‍🌾',
        recipe_id: 1,
        recipe_name: 'Pot Gantung Tanaman Hias',
        recipe_icon: '🪴',
        dialog: 'Halo! Saya butuh 1x Pot Gantung Tanaman Hias untuk taman eco-friendly saya.',
        patience: 100,
        status: 'waiting' // 'waiting' | 'served'
    },

    // Inventaris Sementara Demo
    inventory: [
        { id: 1, name: 'Botol Plastik PET', category: 'Plastik', icon: '🧴', quantity: 2, eco_impact: 15 },
        { id: 4, name: 'Kain Perca Denim/Katun', category: 'Tekstil', icon: '🧵', quantity: 2, eco_impact: 12 },
        { id: 2, name: 'Kardus Karton Bekas', category: 'Kertas/Karton', icon: '📦', quantity: 1, eco_impact: 10 },
        { id: 3, name: 'Kaleng Minuman Aluminium', category: 'Logam', icon: '🥫', quantity: 0, eco_impact: 20 }, // Stok habis untuk demo disabled
    ],

    // Perkakas Demo
    tools: [
        { id: 1, name: 'Gunting Serbaguna', icon: '✂️', action_type: 'Memotong' },
        { id: 2, name: 'Lem Tembak Panas', icon: '🔫', action_type: 'Merekatkan' },
        { id: 3, name: 'Kuas Cat Warna', icon: '🖌️', action_type: 'Mewarnai' },
    ],

    selectedToolId: null,
    workbenchSlots: [null, null, null, null],
    servingPlate: null,

    get filledSlotsCount() {
        return this.workbenchSlots.filter(s => s !== null).length;
    },

    get selectedTool() {
        return this.tools.find(t => t.id === this.selectedToolId) || null;
    },

    get canAssemble() {
        return this.filledSlotsCount >= 1 && this.selectedToolId !== null;
    },

    showNotification(msg, type = 'info') {
        this.toastMessage = msg;
        this.toastType = type;
        setTimeout(() => {
            if (this.toastMessage === msg) this.toastMessage = '';
        }, 4000);
    },

    // Aksi: Memindahkan bahan ke slot meja kerja
    moveToWorkbench(item) {
        if (item.quantity <= 0) {
            this.showNotification('Stok bahan ini habis dan tidak dapat dipilih!', 'warning');
            return;
        }

        const emptyIdx = this.workbenchSlots.findIndex(s => s === null);
        if (emptyIdx === -1) {
            this.showNotification('Slot meja kerja penuh (maksimal 4 bahan)!', 'warning');
            return;
        }

        item.quantity--;
        this.workbenchSlots[emptyIdx] = { ...item };

        // Progres panduan langkah 1
        const hasBottle = this.workbenchSlots.some(s => s && s.id === 1);
        const hasFabric = this.workbenchSlots.some(s => s && s.id === 4);
        if (hasBottle && hasFabric && this.trialStep === 1) {
            this.trialStep = 2;
        }

        this.showNotification(`${item.name} diletakkan di Slot #${emptyIdx + 1}!`, 'success');
    },

    // Aksi: Mengembalikan bahan dari meja kerja ke inventaris
    removeFromWorkbench(index) {
        const item = this.workbenchSlots[index];
        if (!item) return;

        const inv = this.inventory.find(i => i.id === item.id);
        if (inv) inv.quantity++;

        this.workbenchSlots[index] = null;
        this.showNotification(`${item.name} dikembalikan ke inventaris demo.`, 'info');
    },

    selectTool(toolId) {
        this.selectedToolId = toolId;
        if (this.trialStep === 2 && toolId === 1) {
            this.trialStep = 3;
        }
    },

    // Aksi: Rakit Produk Demo dengan Progress Bar Visual
    executeCrafting() {
        if (!this.selectedToolId) {
            this.showNotification('Pilih perkakas Gunting Serbaguna terlebih dahulu!', 'warning');
            return;
        }

        const matIds = this.workbenchSlots.filter(s => s !== null).map(s => s.id);
        const hasBottle = matIds.includes(1);
        const hasFabric = matIds.includes(4);

        if (!hasBottle || !hasFabric || this.selectedToolId !== 1 || matIds.length !== 2) {
            this.showNotification('Kombinasi salah! Resep Pot Gantung membutuhkan: 1x Botol Plastik + 1x Kain Perca + Gunting.', 'error');
            return;
        }

        // Jalankan progress bar visual perakitan
        this.isCrafting = true;
        this.craftProgress = 0;

        const interval = setInterval(() => {
            this.craftProgress += 25;
            if (this.craftProgress >= 100) {
                clearInterval(interval);
                this.isCrafting = false;
                this.servingPlate = {
                    id: 1,
                    name: 'Pot Gantung Tanaman Hias',
                    icon: '🪴',
                    eco_points: 35,
                    coins: 60
                };
                this.workbenchSlots = [null, null, null, null];
                this.trialStep = 4;
                this.showNotification('🎉 Sukses merakit Pot Gantung Tanaman Hias! Letakkan di meja saji.', 'success');
            }
        }, 250);
    },

    // Aksi: Menyerahkan produk demo ke pelanggan
    serveCustomer() {
        if (!this.servingPlate) return;

        this.customer.status = 'served';
        this.servingPlate = null;
        this.trialStep = 5;
        this.completed = true;

        // Tandai trial selesai di backend session tanpa menyimpan data gameplay permanen
        fetch('{{ route('guest.trial.complete') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
    }
}" class="space-y-6">

    {{-- Banner Status Mode Uji Coba --}}
    <div class="bg-gradient-to-r from-amber-500 via-amber-600 to-amber-700 text-white rounded-2xl p-4 shadow-lg flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🎮</span>
            <div>
                <h1 class="text-lg font-black tracking-wide">Mode Uji Coba (Trial) — Demo Stage 1</h1>
                <p class="text-xs text-amber-100">Antrean pesanan pelanggan terpandu tanpa login. Progres disimpan sementara di sesi browser.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="bg-amber-800/60 text-amber-200 text-xs font-bold px-3 py-1.5 rounded-xl border border-amber-400/30">
                ⚡ Sesi Tamu (Guest)
            </span>
            <a href="{{ route('register') }}" class="bg-white text-gray-900 hover:bg-gray-100 text-xs font-black px-4 py-2 rounded-xl shadow transition">
                Daftar Akun Penuh ➔
            </a>
        </div>
    </div>

    {{-- Flash Toast Notification --}}
    <div x-show="toastMessage" x-transition class="p-3.5 rounded-2xl text-xs font-bold flex items-center justify-between shadow-md"
        :class="{
            'bg-emerald-600 text-white': toastType === 'success',
            'bg-amber-100 text-amber-900 border border-amber-300': toastType === 'warning',
            'bg-red-100 text-red-900 border border-red-300': toastType === 'error',
            'bg-blue-100 text-blue-900 border border-blue-300': toastType === 'info'
        }">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = ''" class="opacity-70 hover:opacity-100 ml-2">✕</button>
    </div>

    {{-- Panduan Interaktif Langkah Demi Langkah (Guided Step Indicator) --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-emerald-100 dark:border-gray-700 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 flex items-center justify-center text-xl font-bold shrink-0">
            <span x-text="trialStep <= 4 ? trialStep : '✓'"></span>
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold uppercase tracking-wider text-green-700 dark:text-green-400">Petunjuk Terpandu (Guided Step)</span>
                <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded-full font-bold" x-text="`Langkah ${Math.min(trialStep, 4)} / 4`"></span>
            </div>
            <p class="text-sm font-bold text-gray-800 dark:text-white mt-0.5">
                <span x-show="trialStep === 1">👉 Langkah 1: Klik bahan <b>Botol Plastik PET</b> dan <b>Kain Perca</b> di rak inventaris bawah untuk dipindahkan ke Meja Kerja.</span>
                <span x-show="trialStep === 2">👉 Langkah 2: Klik perkakas <b>Gunting Serbaguna (✂️)</b> di rak perkakas sebelah kanan.</span>
                <span x-show="trialStep === 3">👉 Langkah 3: Semua bahan & alat sudah siap! Tekan tombol <b>Rakit Produk (Craft)</b>.</span>
                <span x-show="trialStep === 4">👉 Langkah 4: Produk selesai dirakit! Klik tombol <b>🤝 Sajikan ke Pelanggan</b> di Meja Saji.</span>
                <span x-show="trialStep >= 5">🎉 Selamat! Demo pesanan selesai.</span>
            </p>
        </div>
    </div>

    {{-- ═══ AREA 1: ANTREAN PESANAN PELANGGAN DEMO (Guided Order Queue) ═══ --}}
    <div class="bg-gradient-to-b from-emerald-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-3xl p-6 shadow-sm border border-emerald-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-extrabold text-gray-800 dark:text-white flex items-center gap-2">
                <span>👥 Antrean Pesanan Pelanggan (Guided Order Queue)</span>
                <span class="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-bold">1 Pesanan Demo</span>
            </h2>
            <span class="text-xs text-gray-500">Patience: Santai (Mode Belajar)</span>
        </div>

        <div class="max-w-md mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border-2 border-green-400 dark:border-green-600 p-5 text-center shadow-md relative">
                {{-- Balon Dialog Pesanan --}}
                <div class="bg-green-50 dark:bg-gray-700 border border-green-200 dark:border-gray-600 rounded-xl p-3 mb-3 shadow-sm relative">
                    <p class="text-xs text-gray-600 dark:text-gray-300 italic mb-1" x-text="`“${customer.dialog}”`"></p>
                    <div class="flex items-center justify-center gap-2 font-black text-sm text-green-800 dark:text-green-300">
                        <span class="text-2xl" x-text="customer.recipe_icon"></span>
                        <span x-text="customer.recipe_name"></span>
                    </div>
                    <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-green-50 dark:bg-gray-700 border-r border-b border-green-200 dark:border-gray-600 rotate-45"></div>
                </div>

                {{-- Avatar Pelanggan --}}
                <div class="text-5xl mb-1" x-text="customer.avatar"></div>
                <h4 class="font-extrabold text-sm text-gray-800 dark:text-white" x-text="customer.name"></h4>
                <span class="text-[11px] text-emerald-600 font-semibold">Toleransi Kesabaran: 100% (Terpandu)</span>

                <div class="mt-2 w-3/4 mx-auto bg-gray-200 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                    <div class="bg-green-500 h-full rounded-full w-full"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ AREA 2: MEJA SAJI (Serving Area Demo) ═══ --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
        <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
            <span>🍽️ Meja Saji (Serving Area)</span>
        </h3>
        <div class="bg-amber-50/50 dark:bg-gray-700/50 border-2 border-dashed border-amber-300 dark:border-gray-600 rounded-2xl p-4 flex items-center justify-between flex-wrap gap-4">
            <template x-if="servingPlate">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <span class="text-4xl" x-text="servingPlate.icon"></span>
                        <div>
                            <h4 class="font-black text-sm text-gray-800 dark:text-white" x-text="servingPlate.name"></h4>
                            <span class="text-xs text-emerald-600 font-bold block">+35 Eco-Points 🌱 | 💰 60 Koin</span>
                        </div>
                    </div>
                    <button
                        @click="serveCustomer()"
                        class="bg-green-600 hover:bg-green-700 text-white font-extrabold px-6 py-2.5 rounded-xl text-xs shadow-md transition animate-bounce">
                        🤝 Sajikan ke Budi!
                    </button>
                </div>
            </template>
            <template x-if="!servingPlate">
                <div class="py-2 text-center w-full text-gray-400">
                    <span class="text-xs">Piring saji masih kosong. Rakit produk pada meja kerja di bawah untuk menyajikannya.</span>
                </div>
            </template>
        </div>
    </div>

    {{-- ═══ AREA 3: MEJA KERJA (WORKBENCH) & RAK PERKAKAS ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- 3 Kolom Kiri: 4 Slot Meja Kerja --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-extrabold text-gray-800 dark:text-white flex items-center gap-2">
                            <span>🛠️ Slot Meja Kerja (Workbench)</span>
                            <span class="text-xs bg-green-100 text-green-800 font-bold px-2 py-0.5 rounded-full" x-text="`${filledSlotsCount} / 4 Terisi`"></span>
                        </h3>
                        <p class="text-xs text-gray-400">Tempatkan bahan limbah daur ulang yang ingin dirakit.</p>
                    </div>
                    <button
                        @click="workbenchSlots.forEach((s, idx) => removeFromWorkbench(idx))"
                        :disabled="filledSlotsCount === 0"
                        class="bg-gray-100 hover:bg-gray-200 disabled:opacity-40 text-gray-600 text-xs font-bold px-3 py-1.5 rounded-xl transition">
                        Kosongkan Meja
                    </button>
                </div>

                {{-- 4 Slots Meja Kerja --}}
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                    <template x-for="(slot, idx) in workbenchSlots" :key="idx">
                        <div class="relative min-h-[140px] rounded-2xl border-2 p-3 flex flex-col justify-between transition-all"
                            :class="slot ? 'border-green-500 bg-green-50/50 dark:bg-gray-700 shadow-sm' : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50/50'">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-gray-400 uppercase" x-text="`Slot #${idx + 1}`"></span>
                                <template x-if="slot">
                                    <button @click="removeFromWorkbench(idx)" class="w-5 h-5 bg-red-100 text-red-600 rounded-full text-xs font-bold flex items-center justify-center">✕</button>
                                </template>
                            </div>
                            <div class="my-auto text-center py-1">
                                <template x-if="slot">
                                    <div>
                                        <div class="text-3xl mb-1" x-text="slot.icon"></div>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-white leading-tight" x-text="slot.name"></h5>
                                        <span class="text-[9px] bg-emerald-100 text-emerald-800 font-extrabold px-1.5 py-0.5 rounded" x-text="`+${slot.eco_impact} Eco`"></span>
                                    </div>
                                </template>
                                <template x-if="!slot">
                                    <div class="text-gray-400 text-xs py-3">
                                        <div class="text-xl mb-1">＋</div>
                                        <span>Slot Kosong</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Progress Bar Visual & Tombol Crafting --}}
            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700/60 rounded-2xl border border-gray-200 dark:border-gray-600 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-green-600 text-white flex items-center justify-center text-xl shadow">
                            <span x-text="selectedTool ? selectedTool.icon : '⚙️'"></span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-800 dark:text-white">
                                Status Perakitan: <span class="text-green-600 font-extrabold" x-text="selectedTool ? selectedTool.name : 'Belum Ada Perkakas Dipilih'"></span>
                            </h4>
                            <p class="text-[11px] text-gray-400">Pilih 1x Botol Plastik + 1x Kain Perca + Gunting Serbaguna.</p>
                        </div>
                    </div>

                    <button
                        @click="executeCrafting()"
                        :disabled="!canAssemble || isCrafting"
                        class="w-full sm:w-auto bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-extrabold px-6 py-2.5 rounded-xl text-xs shadow transition flex items-center justify-center gap-2">
                        <span x-show="!isCrafting">🔨 Rakit Produk (Craft)</span>
                        <span x-show="isCrafting" x-cloak>Sedang Merakit...</span>
                    </button>
                </div>

                {{-- Progress Bar Animasi saat Crafting Berlangsung (PBI-008 Tech Task) --}}
                <div x-show="isCrafting" x-transition class="space-y-1">
                    <div class="flex justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                        <span>Memotong dan merangkai limbah...</span>
                        <span x-text="`${craftProgress}%`"></span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-600 h-2.5 rounded-full overflow-hidden">
                        <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-full rounded-full transition-all duration-300"
                            :style="`width: ${craftProgress}%`"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 1 Kolom Kanan: RAK PERKAKAS (TOOLS) --}}
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <span>🧰 Rak Perkakas (Tools)</span>
            </h3>
            <div class="space-y-2.5">
                <template x-for="tool in tools" :key="tool.id">
                    <div
                        @click="selectTool(tool.id)"
                        :class="selectedToolId === tool.id
                            ? 'border-green-600 bg-green-50/80 dark:bg-gray-700 ring-2 ring-green-500/30 shadow-sm'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                        class="p-2.5 rounded-xl border cursor-pointer transition flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-2xl" x-text="tool.icon"></span>
                            <div>
                                <h5 class="text-xs font-bold text-gray-800 dark:text-white" x-text="tool.name"></h5>
                                <span class="text-[10px] text-gray-400 block" x-text="tool.action_type"></span>
                            </div>
                        </div>
                        <span
                            :class="selectedToolId === tool.id ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400'"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold">
                            ✓
                        </span>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- ═══ AREA 4: INVENTARIS BAHAN DEMO DENGAN DISABLED STATE SAAT STOK == 0 (PBI-008 AC 1 & 2) ═══ --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100 dark:border-gray-700">
            <div>
                <h3 class="text-base font-extrabold text-gray-800 dark:text-white flex items-center gap-2">
                    <span>🎒 Rak Inventaris Bahan Sampah (Trial State)</span>
                </h3>
                <p class="text-xs text-gray-400">Bahan dengan stok 0 otomatis tidak aktif (disabled) dan tidak dapat dipindahkan.</p>
            </div>
            <span class="text-xs bg-amber-100 text-amber-800 px-3 py-1 rounded-xl font-bold">💡 Klik bahan untuk memilih</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <template x-for="item in inventory" :key="item.id">
                <div
                    @click="moveToWorkbench(item)"
                    :class="item.quantity > 0
                        ? 'cursor-pointer hover:border-green-500 hover:shadow-md bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600'
                        : 'opacity-40 cursor-not-allowed pointer-events-none bg-gray-100 dark:bg-gray-800 border-dashed border-gray-300 dark:border-gray-700'"
                    class="rounded-2xl border p-4 transition-all flex flex-col justify-between text-center relative">

                    {{-- Indikator Kuantitas Stok Real-Time (PBI-008 AC 1 & 2) --}}
                    <div class="absolute top-2.5 right-2.5">
                        <span
                            :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900 font-black' : 'bg-gray-300 text-gray-600 font-bold'"
                            class="text-[10px] px-2 py-0.5 rounded-full shadow-sm"
                            x-text="item.quantity > 0 ? `x${item.quantity}` : 'Habis'">
                        </span>
                    </div>

                    <div class="py-2">
                        <div class="text-3xl mb-1" x-text="item.icon"></div>
                        <h4 class="font-extrabold text-xs text-gray-800 dark:text-white leading-tight" x-text="item.name"></h4>
                        <span class="text-[10px] text-gray-400 block mt-0.5" x-text="item.category"></span>
                    </div>

                    <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-600">
                        <button
                            type="button"
                            :disabled="item.quantity <= 0"
                            class="w-full text-[10px] font-extrabold py-1 rounded-lg transition"
                            :class="item.quantity > 0 ? 'bg-green-50 text-green-700 hover:bg-green-600 hover:text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                            <span x-text="item.quantity > 0 ? '+ Pilih Bahan' : 'Stok Habis'"></span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- MODAL STAGE TRIAL COMPLETED DENGAN CALL TO ACTION LOGIN / DAFTAR --}}
    <div x-show="completed" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full p-6 text-center space-y-4 border-2 border-green-500 animate-in fade-in zoom-in duration-300">
            <div class="w-20 h-20 mx-auto bg-green-100 dark:bg-green-900/60 rounded-full flex items-center justify-center text-4xl shadow-inner border border-green-300">
                🎉
            </div>

            <div>
                <span class="text-xs font-black uppercase tracking-wider text-green-600">Babak Demo 1 Stage Selesai!</span>
                <h3 class="text-2xl font-black text-gray-800 dark:text-white mt-1">Luar Biasa, Eco-Crafter!</h3>
                <p class="text-xs text-gray-500 mt-1">Anda telah berhasil merakit pesanan pelanggan pertama dan menyelamatkan sampah dari TPA.</p>
            </div>

            {{-- Ringkasan Simulasi Reward --}}
            <div class="grid grid-cols-2 gap-3 p-3.5 bg-green-50 dark:bg-gray-700 rounded-2xl border border-green-200">
                <div>
                    <span class="text-[10px] text-gray-500 block font-bold">Reward Simulasi</span>
                    <span class="text-base font-black text-amber-500">💰 +60 Koin</span>
                </div>
                <div>
                    <span class="text-[10px] text-gray-500 block font-bold">Eco-Impact</span>
                    <span class="text-base font-black text-emerald-600">🌱 +35 Eco-Points</span>
                </div>
            </div>

            {{-- Fakta Edukasi SDG 12.5 Lokal (Non-AI) --}}
            <div class="bg-emerald-50 dark:bg-gray-700/50 p-3 rounded-xl text-left border border-emerald-200">
                <span class="text-[10px] font-bold text-emerald-800 dark:text-emerald-300 block uppercase">💡 Fakta Upcycling (SDG 12 Target 12.5):</span>
                <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                    Mendaur ulang botol plastik PET menjadi pot tanaman mandiri menghemat 70% energi dibanding memproduksi plastik baru serta mencegah sampah mencemari saluran air.
                </p>
            </div>

            <div class="pt-2 space-y-2">
                <a href="{{ route('register') }}" class="w-full block bg-green-600 hover:bg-green-700 text-white font-extrabold py-3 rounded-xl text-sm transition shadow-lg">
                    ✨ Daftar Akun Gratis & Buka Semua Stage
                </a>
                <a href="{{ route('login') }}" class="w-full block border border-gray-300 text-gray-700 dark:text-gray-200 hover:bg-gray-50 font-bold py-2.5 rounded-xl text-xs transition">
                    Sudah Punya Akun? Masuk
                </a>
                <a href="{{ route('home') }}" class="text-xs text-gray-400 hover:underline block pt-1">
                    Kembali ke Halaman Beranda
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
