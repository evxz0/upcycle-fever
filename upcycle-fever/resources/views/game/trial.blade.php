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
        { id: 3, name: 'Kaleng Minuman Aluminium', category: 'Logam', icon: '🥫', quantity: 0, eco_impact: 20 },
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
        }, 3500);
    },

    init() {
        const savedData = sessionStorage.getItem('upcycle_trial_state');
        if (savedData) {
            try {
                const parsed = JSON.parse(savedData);
                if (parsed.completed) {
                    this.completed = true;
                    this.trialStep = 5;
                }
            } catch (e) {}
        }
    },

    saveTemporaryState() {
        sessionStorage.setItem('upcycle_trial_state', JSON.stringify({
            completed: this.completed,
            step: this.trialStep,
            customer_status: this.customer.status
        }));
    },

    moveToWorkbench(item) {
        if (item.quantity <= 0) {
            this.showNotification('Stok bahan ini habis!', 'warning');
            return;
        }

        const emptyIdx = this.workbenchSlots.findIndex(s => s === null);
        if (emptyIdx === -1) {
            this.showNotification('Slot meja kerja penuh (maks 4 bahan)!', 'warning');
            return;
        }

        item.quantity--;
        this.workbenchSlots[emptyIdx] = { ...item };

        const hasBottle = this.workbenchSlots.some(s => s && s.id === 1);
        const hasFabric = this.workbenchSlots.some(s => s && s.id === 4);
        if (hasBottle && hasFabric && this.trialStep === 1) {
            this.trialStep = 2;
        }

        this.showNotification(`${item.name} dipasang di Slot #${emptyIdx + 1}!`, 'success');
    },

    removeFromWorkbench(index) {
        const item = this.workbenchSlots[index];
        if (!item) return;

        const inv = this.inventory.find(i => i.id === item.id);
        if (inv) inv.quantity++;

        this.workbenchSlots[index] = null;
    },

    selectTool(toolId) {
        this.selectedToolId = toolId;
        if (this.trialStep === 2 && toolId === 1) {
            this.trialStep = 3;
        }
    },

    executeCrafting() {
        if (!this.selectedToolId) {
            this.showNotification('Pilih perkakas Gunting Serbaguna!', 'warning');
            return;
        }

        const matIds = this.workbenchSlots.filter(s => s !== null).map(s => s.id);
        const hasBottle = matIds.includes(1);
        const hasFabric = matIds.includes(4);

        if (!hasBottle || !hasFabric || this.selectedToolId !== 1 || matIds.length !== 2) {
            this.showNotification('Resep Pot Gantung: 1x Botol Plastik + 1x Kain Perca + Gunting.', 'error');
            return;
        }

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
                this.showNotification('🎉 Sukses merakit Pot Gantung!', 'success');
            }
        }, 200);
    },

    serveCustomer() {
        if (!this.servingPlate) return;

        this.customer.status = 'served';
        this.servingPlate = null;
        this.trialStep = 5;
        this.completed = true;
        this.saveTemporaryState();

        fetch('{{ route('guest.trial.complete') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
    }
}" class="h-full flex flex-col justify-between gap-2 overflow-hidden select-none">

    {{-- BARIS 1: TOP BAR COMPACT (Banner Ringkas & Langkah Panduan) --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 shrink-0">
        {{-- Banner Info Tamu --}}
        <div class="md:col-span-4 bg-gradient-to-r from-amber-500 to-amber-600 text-white rounded-xl px-3 py-1.5 shadow flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xl">🎮</span>
                <div>
                    <h1 class="text-xs font-black leading-tight">Trial: Stage 1</h1>
                    <p class="text-[10px] text-amber-100">Mode Belajar Tanpa Login</p>
                </div>
            </div>
            <a href="{{ route('register') }}" class="bg-white text-gray-900 text-[10px] font-black px-2.5 py-1 rounded-lg shadow hover:bg-gray-100 transition">
                Daftar ➔
            </a>
        </div>

        {{-- Petunjuk Langkah Terpandu (Guided Steps) --}}
        <div class="md:col-span-8 bg-white dark:bg-gray-800 rounded-xl px-3 py-1.5 shadow border border-emerald-100 dark:border-gray-700 flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 flex items-center justify-center text-xs font-black shrink-0"
                x-text="trialStep <= 4 ? trialStep : '✓'"></span>
            <div class="flex-1 truncate">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-green-700 dark:text-green-400">Petunjuk Terpandu</span>
                    <span class="text-[9px] bg-green-100 text-green-800 px-1.5 py-0.2 rounded font-bold" x-text="`Langkah ${Math.min(trialStep, 4)} / 4`"></span>
                </div>
                <p class="text-xs font-bold text-gray-800 dark:text-white truncate">
                    <span x-show="trialStep === 1">👉 Klik <b>Botol Plastik PET</b> & <b>Kain Perca</b> di rak inventaris bawah.</span>
                    <span x-show="trialStep === 2">👉 Klik perkakas <b>Gunting Serbaguna (✂️)</b> di rak perkakas.</span>
                    <span x-show="trialStep === 3">👉 Bahan & alat siap! Klik <b>Rakit Produk (Craft)</b>.</span>
                    <span x-show="trialStep === 4">👉 Klik tombol <b>🤝 Sajikan</b> di Meja Saji!</span>
                    <span x-show="trialStep >= 5">🎉 Selamat! Demo pesanan selesai.</span>
                </p>
            </div>
            {{-- Toast mini jika ada pesan --}}
            <template x-if="toastMessage">
                <span class="text-[10px] px-2 py-0.5 rounded font-bold animate-pulse shrink-0"
                    :class="{
                        'bg-emerald-600 text-white': toastType === 'success',
                        'bg-amber-100 text-amber-900': toastType === 'warning',
                        'bg-red-500 text-white': toastType === 'error',
                        'bg-blue-600 text-white': toastType === 'info'
                    }" x-text="toastMessage"></span>
            </template>
        </div>
    </div>

    {{-- BARIS 2: AREA PELANGGAN (COUNTER) & MEJA SAJI --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 shrink-0">
        {{-- Antrean Pelanggan --}}
        <div class="md:col-span-7 bg-white dark:bg-gray-800 rounded-2xl p-2.5 shadow-sm border border-emerald-100 dark:border-gray-700 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="text-3xl p-1 bg-green-50 dark:bg-gray-700 rounded-xl" x-text="customer.avatar"></div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-black text-xs text-gray-800 dark:text-white" x-text="customer.name"></h4>
                        <span class="text-[9px] bg-green-100 text-green-800 px-1.5 py-0.2 rounded font-bold">100% Patience</span>
                    </div>
                    <p class="text-[11px] text-gray-500 italic mt-0.5" x-text="`“${customer.dialog}”`"></p>
                </div>
            </div>
            <div class="bg-green-50 dark:bg-gray-700 border border-green-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-center shrink-0">
                <span class="text-[9px] text-gray-400 block font-bold uppercase">Pesanan</span>
                <span class="text-xs font-black text-green-700 dark:text-green-300 flex items-center gap-1">
                    <span x-text="customer.recipe_icon"></span> <span x-text="customer.recipe_name"></span>
                </span>
            </div>
        </div>

        {{-- Meja Saji (Serving Area) --}}
        <div class="md:col-span-5 bg-white dark:bg-gray-800 rounded-2xl p-2.5 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-xl">🍽️</span>
                <div>
                    <span class="text-[10px] text-gray-400 font-bold block uppercase leading-none">Meja Saji</span>
                    <span class="text-xs font-bold text-gray-800 dark:text-white" x-text="servingPlate ? servingPlate.name : 'Piring Saji Kosong'"></span>
                </div>
            </div>
            <template x-if="servingPlate">
                <button @click="serveCustomer()" class="bg-green-600 hover:bg-green-700 text-white font-black px-4 py-1.5 rounded-xl text-xs shadow transition animate-bounce flex items-center gap-1">
                    <span>🤝</span> Sajikan!
                </button>
            </template>
            <template x-if="!servingPlate">
                <span class="text-[10px] text-gray-400 italic">Rakit di bawah</span>
            </template>
        </div>
    </div>

    {{-- BARIS 3: WORKBENCH (MEJA KERJA) & RAK PERKAKAS (TOOLS) --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 flex-1 min-h-0">
        {{-- Slot Meja Kerja --}}
        <div class="md:col-span-8 bg-white dark:bg-gray-800 rounded-2xl p-3 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-1 pb-1 border-b border-gray-100 dark:border-gray-700">
                <span class="text-xs font-black text-gray-800 dark:text-white flex items-center gap-1.5">
                    <span>🛠️</span> Slot Meja Kerja
                    <span class="text-[9px] bg-green-100 text-green-800 px-1.5 py-0.2 rounded font-bold" x-text="`${filledSlotsCount}/4`"></span>
                </span>
                <button @click="workbenchSlots.forEach((s, idx) => removeFromWorkbench(idx))" :disabled="filledSlotsCount === 0" class="text-[10px] text-gray-500 hover:text-red-500 disabled:opacity-30 font-bold">
                    Kosongkan
                </button>
            </div>

            {{-- 4 Slots Meja Kerja --}}
            <div class="grid grid-cols-4 gap-2 my-auto">
                <template x-for="(slot, idx) in workbenchSlots" :key="idx">
                    <div class="h-20 sm:h-24 rounded-xl border-2 p-1.5 flex flex-col justify-between transition-all text-center relative"
                        :class="slot ? 'border-green-500 bg-green-50/60 dark:bg-gray-700 shadow-sm' : 'border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/50'">
                        <div class="flex justify-between items-center text-[9px] text-gray-400">
                            <span x-text="`#${idx+1}`"></span>
                            <template x-if="slot">
                                <button @click="removeFromWorkbench(idx)" class="w-4 h-4 bg-red-100 text-red-600 rounded-full font-bold">✕</button>
                            </template>
                        </div>
                        <template x-if="slot">
                            <div class="my-auto">
                                <div class="text-2xl" x-text="slot.icon"></div>
                                <span class="text-[10px] font-black block leading-tight text-gray-800 dark:text-white truncate" x-text="slot.name"></span>
                            </div>
                        </template>
                        <template x-if="!slot">
                            <span class="text-gray-300 text-sm my-auto">＋</span>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Tombol Action Crafting & Progress --}}
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 truncate">
                    <span class="text-lg" x-text="selectedTool ? selectedTool.icon : '⚙️'"></span>
                    <span class="text-[11px] font-bold text-gray-600 dark:text-gray-300 truncate" x-text="selectedTool ? selectedTool.name : 'Pilih Perkakas di Kanan'"></span>
                </div>
                <button @click="executeCrafting()" :disabled="!canAssemble || isCrafting"
                    class="bg-green-600 hover:bg-green-700 disabled:bg-gray-200 disabled:text-gray-400 text-white font-black px-5 py-2 rounded-xl text-xs shadow transition shrink-0 flex items-center gap-1.5">
                    <span x-show="!isCrafting">🔨 Rakit Produk</span>
                    <span x-show="isCrafting" x-cloak x-text="`Merakit ${craftProgress}%`"></span>
                </button>
            </div>
        </div>

        {{-- Rak Perkakas (Tools) --}}
        <div class="md:col-span-4 bg-white dark:bg-gray-800 rounded-2xl p-3 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <span class="text-xs font-black text-gray-800 dark:text-white mb-1.5 flex items-center gap-1.5">
                <span>🧰</span> Rak Perkakas
            </span>
            <div class="space-y-1.5 my-auto">
                <template x-for="tool in tools" :key="tool.id">
                    <div @click="selectTool(tool.id)"
                        :class="selectedToolId === tool.id ? 'border-green-600 bg-green-50 dark:bg-gray-700 ring-2 ring-green-400' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                        class="p-2 rounded-xl border cursor-pointer transition flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl" x-text="tool.icon"></span>
                            <div>
                                <h5 class="text-xs font-black text-gray-800 dark:text-white" x-text="tool.name"></h5>
                                <span class="text-[9px] text-gray-400" x-text="tool.action_type"></span>
                            </div>
                        </div>
                        <span :class="selectedToolId === tool.id ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-300'" class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold">✓</span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- BARIS 4: INVENTARIS BAHAN SAMPAH --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-2.5 shadow-sm border border-gray-100 dark:border-gray-700 shrink-0">
        <div class="flex items-center justify-between mb-2 pb-1 border-b border-gray-100 dark:border-gray-700">
            <span class="text-xs font-black text-gray-800 dark:text-white flex items-center gap-1.5">
                <span>🎒</span> Rak Inventaris Bahan
            </span>
            <span class="text-[10px] text-gray-400">💡 Klik bahan untuk meletakkan ke slot meja kerja</span>
        </div>

        <div class="grid grid-cols-4 gap-2">
            <template x-for="item in inventory" :key="item.id">
                <div @click="moveToWorkbench(item)"
                    :class="item.quantity > 0 ? 'cursor-pointer hover:border-green-500 bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600' : 'opacity-40 cursor-not-allowed pointer-events-none bg-gray-100 dark:bg-gray-800 border-dashed border-gray-300'"
                    class="rounded-xl border p-2 text-center transition flex items-center justify-between gap-2 relative">
                    <div class="text-2xl" x-text="item.icon"></div>
                    <div class="text-left flex-1 min-w-0">
                        <h4 class="font-black text-xs text-gray-800 dark:text-white truncate" x-text="item.name"></h4>
                        <span class="text-[9px] text-gray-400 block" x-text="item.category"></span>
                    </div>
                    <span :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900' : 'bg-gray-200 text-gray-400'" class="text-[10px] font-black px-2 py-0.5 rounded-lg shrink-0" x-text="item.quantity > 0 ? `x${item.quantity}` : 'Habis'"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- MODAL STAGE TRIAL COMPLETED --}}
    <div x-show="completed" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center space-y-3 border-2 border-green-500">
            <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center text-3xl">🎉</div>
            <h3 class="text-xl font-black text-gray-800 dark:text-white">Luar Biasa, Eco-Crafter!</h3>
            <p class="text-xs text-gray-500">Babak demo selesai! Produk pesanan pelanggan telah berhasil dirakit & disajikan.</p>
            <div class="p-3 bg-green-50 dark:bg-gray-700 rounded-xl grid grid-cols-2 gap-2 text-center">
                <span class="text-xs font-black text-amber-500">💰 +60 Koin</span>
                <span class="text-xs font-black text-emerald-600">🌱 +35 Eco-Points</span>
            </div>
            <div class="pt-2 space-y-2">
                <a href="{{ route('register') }}" class="w-full block bg-green-600 hover:bg-green-700 text-white font-black py-2.5 rounded-xl text-xs shadow">
                    ✨ Daftar Akun & Buka Semua Stage
                </a>
                <a href="{{ route('home') }}" class="text-xs text-gray-400 hover:underline block">Kembali ke Beranda</a>
            </div>
        </div>
    </div>

</div>
@endsection
