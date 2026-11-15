@extends('layouts.app')
@section('title', 'Meja Kerja (Workbench)')

@section('content')
<div x-data="{
    // Data Inventaris Pemain (reactive)
    inventory: @js($inventory ?? []).map(item => ({
        id: item.material_id || item.id,
        name: item.material ? item.material.name : (item.name || 'Bahan'),
        category: item.material ? item.material.category : (item.category || 'Limbah'),
        icon: item.material ? item.material.icon : (item.icon || '📦'),
        eco_impact: item.material ? item.material.eco_impact : (item.eco_impact || 10),
        description: item.material ? item.material.description : (item.description || ''),
        quantity: item.quantity !== undefined ? item.quantity : 1
    })),

    // Alat Kerja & Resep
    tools: @js($tools ?? []),
    recipes: @js($recipes ?? []),
    selectedToolId: null,
    isCrafting: false,
    showRecipeModal: false,
    craftedProduct: null,

    // 4 Slot Meja Kerja (Workbench)
    workbenchSlots: [null, null, null, null],
    assemblyMessage: '',
    assemblyStatus: '',

    // PBI-006: Pelanggan NPC & Meja Saji
    customers: @js($customers ?? []),
    servingPlates: [null, null],
    playerCoins: {{ ($user->coins ?? 265) }},
    playerEcoPoints: {{ ($user->eco_points ?? 10) }},
    playerXp: {{ ($user->xp ?? 0) }},
    rewardPopup: null,

    // Computed properties
    get filledSlotsCount() {
        return this.workbenchSlots.filter(s => s !== null).length;
    },

    get totalEcoPoints() {
        return this.workbenchSlots.reduce((acc, s) => acc + (s ? s.eco_impact : 0), 0);
    },

    get canAssemble() {
        return this.filledSlotsCount >= 1 && this.selectedToolId !== null;
    },

    get selectedTool() {
        return this.tools.find(t => t.id === this.selectedToolId) || null;
    },

    // PBI-006: Timer kesabaran pelanggan
    init() {
        this.startPatienceTimers();
    },

    startPatienceTimers() {
        setInterval(() => {
            this.customers.forEach(c => {
                if (c.status === 'waiting' && c.patience_remaining > 0) {
                    c.patience_remaining--;
                    if (c.patience_remaining <= 0) {
                        this.handleExpiredCustomer(c);
                    }
                }
            });
        }, 1000);
    },

    async handleExpiredCustomer(customer) {
        try {
            const res = await fetch('{{ route('api.game.customers.expire') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ order_id: customer.id })
            });
            const data = await res.json();
            if (data.success && data.new_customer) {
                const idx = this.customers.findIndex(c => c.id === customer.id);
                if (idx !== -1) this.customers[idx] = data.new_customer;
            }
            this.showNotification(`${customer.customer_name} pergi karena tidak sabar!`, 'warning');
        } catch (e) {
            customer.status = 'expired';
        }
    },

    // Aksi: Memindahkan bahan dari inventaris ke slot meja kerja yang kosong (PBI-004 AC 1)
    moveToWorkbench(item) {
        if (item.quantity <= 0) {
            this.showNotification('Stok bahan ini di inventaris sudah habis!', 'error');
            return;
        }

        const emptySlotIndex = this.workbenchSlots.findIndex(s => s === null);
        if (emptySlotIndex === -1) {
            this.showNotification('Semua slot meja kerja penuh! Kosongkan slot terlebih dahulu.', 'warning');
            return;
        }

        item.quantity--;
        this.workbenchSlots[emptySlotIndex] = {
            id: item.id,
            name: item.name,
            category: item.category,
            icon: item.icon,
            eco_impact: item.eco_impact,
            description: item.description,
            inventoryItemRef: item
        };

        this.showNotification(`${item.name} diletakkan di Slot #${emptySlotIndex + 1}!`, 'success');
    },

    removeFromWorkbench(slotIndex) {
        const slotItem = this.workbenchSlots[slotIndex];
        if (!slotItem) return;

        const invItem = this.inventory.find(i => i.id === slotItem.id);
        if (invItem) invItem.quantity++;

        this.workbenchSlots[slotIndex] = null;
        this.showNotification(`${slotItem.name} dikembalikan ke inventaris.`, 'info');
    },

    clearWorkbench() {
        this.workbenchSlots.forEach((slotItem, idx) => {
            if (slotItem) {
                const invItem = this.inventory.find(i => i.id === slotItem.id);
                if (invItem) invItem.quantity++;
                this.workbenchSlots[idx] = null;
            }
        });
        this.showNotification('Seluruh slot meja kerja telah dikosongkan.', 'info');
    },

    // Aksi PBI-005: Eksekusi Perakitan Produk
    async executeCrafting() {
        if (!this.selectedToolId) {
            this.showNotification('Pilih salah satu alat kerja terlebih dahulu!', 'warning');
            return;
        }

        const materialIds = this.workbenchSlots
            .filter(s => s !== null)
            .map(s => s.id);

        if (materialIds.length === 0) {
            this.showNotification('Meja kerja masih kosong. Pilih bahan dari inventaris!', 'warning');
            return;
        }

        this.isCrafting = true;
        try {
            const res = await fetch('{{ route('api.workbench.craft') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    material_ids: materialIds,
                    tool_id: this.selectedToolId
                })
            });

            const data = await res.json();

            if (res.ok && data.success) {
                // Produk selesai dirakit → letakkan di meja saji
                const emptyPlate = this.servingPlates.findIndex(p => p === null);
                if (emptyPlate !== -1) {
                    this.servingPlates[emptyPlate] = data.product;
                } else {
                    this.craftedProduct = data.product;
                }
                this.workbenchSlots = [null, null, null, null];
                this.showNotification(data.message, 'assembly-success');
            } else {
                this.showNotification(data.message || 'Kombinasi salah!', 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi kesalahan jaringan saat perakitan.', 'error');
        } finally {
            this.isCrafting = false;
        }
    },

    // PBI-006: Serahkan produk dari meja saji ke pelanggan
    async serveToCustomer(plateIndex, customer) {
        const product = this.servingPlates[plateIndex];
        if (!product) {
            this.showNotification('Tidak ada produk di piring ini!', 'warning');
            return;
        }

        if (product.id !== customer.recipe_id) {
            this.showNotification(`Produk ini bukan pesanan ${customer.customer_name}!`, 'error');
            return;
        }

        try {
            const res = await fetch('{{ route('api.game.serve') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    order_id: customer.id,
                    recipe_id: product.id
                })
            });

            const data = await res.json();

            if (data.success) {
                // Kosongkan piring saji
                this.servingPlates[plateIndex] = null;

                // Tampilkan popup reward
                this.rewardPopup = data.reward;
                this.playerCoins += data.reward.coins;
                this.playerEcoPoints += data.reward.eco_points;
                this.playerXp += data.reward.xp;
                setTimeout(() => this.rewardPopup = null, 3000);

                // AC 2: Ganti pelanggan dengan yang baru
                if (data.new_customer) {
                    const idx = this.customers.findIndex(c => c.id === customer.id);
                    if (idx !== -1) this.customers[idx] = data.new_customer;
                }

                this.showNotification(data.message, 'assembly-success');
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi kesalahan jaringan.', 'error');
        }
    },

    showNotification(msg, type = 'info') {
        this.assemblyMessage = msg;
        this.assemblyStatus = type;
        setTimeout(() => {
            if (this.assemblyMessage === msg) {
                this.assemblyMessage = '';
            }
        }, 5000);
    }
}" class="space-y-6">

    {{-- ═══ TOP BAR: Status Pemain (Mockup Header) ═══ --}}
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 text-white rounded-2xl px-5 py-3 shadow-xl flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('game.lobby') }}" class="bg-gray-700 hover:bg-gray-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition">☰ MENU</a>
            <span class="bg-blue-600 text-white text-xs font-black px-3 py-1 rounded-lg flex items-center gap-1">
                👥 <span x-text="customers.filter(c => c.status === 'waiting').length">3</span>
            </span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-amber-400 font-black text-sm flex items-center gap-1">💰 <span x-text="playerCoins">265</span></span>
            <span class="text-emerald-400 font-black text-sm flex items-center gap-1">🌱 <span x-text="playerEcoPoints">10</span></span>
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-gray-400 font-bold">XP</span>
                <div class="w-24 h-2.5 bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full transition-all"
                        :style="`width: ${Math.min(playerXp % 100, 100)}%`"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ AREA 1: PELANGGAN NPC (3 Slot Counter) — PBI-006 ═══ --}}
    <div class="bg-gradient-to-b from-emerald-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-3xl p-6 shadow-sm border border-emerald-100 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-4">
            <span>👥 Antrian Pelanggan Studio</span>
            <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 px-2.5 py-0.5 rounded-full font-extrabold"
                x-text="`${customers.filter(c => c.status === 'waiting').length} menunggu`"></span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <template x-for="customer in customers" :key="customer.id">
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl border-2 p-4 text-center transition-all"
                    :class="customer.patience_remaining > 0 && customer.status === 'waiting'
                        ? 'border-green-300 dark:border-green-700 shadow-md'
                        : 'border-gray-200 dark:border-gray-700 opacity-50'">

                    {{-- Balon Dialog Pesanan --}}
                    <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 mb-3 shadow-sm relative">
                        <div class="flex items-center justify-center gap-2">
                            <span class="text-2xl" x-text="customer.recipe_icon"></span>
                            <span class="text-xs font-bold text-gray-800 dark:text-white" x-text="customer.recipe_name"></span>
                        </div>
                        {{-- Arrow bawah --}}
                        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white dark:bg-gray-700 border-r border-b border-gray-200 dark:border-gray-600 rotate-45"></div>
                    </div>

                    {{-- Avatar NPC --}}
                    <div class="text-4xl mb-1" x-text="customer.customer_avatar"></div>
                    <h4 class="font-bold text-sm text-gray-800 dark:text-white" x-text="customer.customer_name"></h4>

                    {{-- Patience Bar Vertikal (PBI-006 AC 1) --}}
                    <div class="mt-3 mx-auto w-full max-w-[140px]">
                        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000"
                                :class="{
                                    'bg-green-500': (customer.patience_remaining / customer.patience_max) > 0.5,
                                    'bg-amber-500': (customer.patience_remaining / customer.patience_max) > 0.25 && (customer.patience_remaining / customer.patience_max) <= 0.5,
                                    'bg-red-500 animate-pulse': (customer.patience_remaining / customer.patience_max) <= 0.25
                                }"
                                :style="`width: ${(customer.patience_remaining / customer.patience_max) * 100}%`"></div>
                        </div>
                        <span class="text-[10px] font-mono font-bold mt-1 block"
                            :class="customer.patience_remaining <= 10 ? 'text-red-600' : 'text-gray-500'"
                            x-text="`${customer.patience_remaining}s`"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ═══ AREA 2: MEJA SAJI (Serving Plates) — PBI-006 ═══ --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-4">
            <span>🍽️ Meja Saji (Serving Area)</span>
            <span class="text-xs text-gray-500">Klik produk → klik pelanggan untuk menyerahkan</span>
        </h2>

        <div class="grid grid-cols-2 gap-4">
            <template x-for="(plate, pIdx) in servingPlates" :key="pIdx">
                <div class="min-h-[120px] rounded-2xl border-2 flex flex-col items-center justify-center p-4 transition-all"
                    :class="plate ? 'border-amber-400 bg-amber-50/50 dark:bg-gray-700 shadow-sm' : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50/30'">

                    <template x-if="plate">
                        <div class="text-center space-y-2">
                            <div class="text-4xl" x-text="plate.output_icon"></div>
                            <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="plate.name"></h4>
                            <div class="flex flex-wrap gap-1 justify-center">
                                <template x-for="customer in customers.filter(c => c.status === 'waiting' && c.recipe_id === plate.id)" :key="customer.id">
                                    <button
                                        @click="serveToCustomer(pIdx, customer)"
                                        class="bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition flex items-center gap-1">
                                        🤝 Sajikan ke <span x-text="customer.customer_name"></span>
                                    </button>
                                </template>
                                <template x-if="customers.filter(c => c.status === 'waiting' && c.recipe_id === plate.id).length === 0">
                                    <span class="text-[10px] text-gray-400 italic">Tidak ada pelanggan yang memesan ini</span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="!plate">
                        <div class="text-center text-gray-400">
                            <div class="text-3xl mb-1">🍽️</div>
                            <span class="text-xs">Piring Kosong</span>
                            <span class="text-[10px] block text-gray-400">Rakit produk untuk mengisi</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Reward Popup --}}
    <div x-show="rewardPopup" x-transition class="fixed top-20 right-6 z-50 bg-gradient-to-br from-amber-400 to-amber-500 text-gray-900 rounded-2xl p-4 shadow-2xl max-w-xs animate-bounce">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🎉</span>
            <div>
                <span class="font-black text-sm block">Reward Diterima!</span>
                <span class="text-xs font-bold" x-text="rewardPopup ? `💰 +${rewardPopup.coins} Koin  🌱 +${rewardPopup.eco_points} Eco` : ''"></span>
            </div>
        </div>
    </div>

    {{-- Flash Notification Toast --}}
    <div x-show="assemblyMessage" x-transition class="p-4 rounded-2xl text-sm font-semibold flex items-center justify-between shadow-md"
        :class="{
            'bg-green-100 text-green-900 border border-green-300': assemblyStatus === 'success',
            'bg-emerald-600 text-white border border-emerald-700': assemblyStatus === 'assembly-success',
            'bg-amber-100 text-amber-900 border border-amber-300': assemblyStatus === 'warning',
            'bg-red-100 text-red-900 border border-red-300': assemblyStatus === 'error',
            'bg-blue-100 text-blue-900 border border-blue-300': assemblyStatus === 'info'
        }">
        <div class="flex items-center gap-2">
            <span x-show="assemblyStatus === 'success'">✅</span>
            <span x-show="assemblyStatus === 'assembly-success'">🎉</span>
            <span x-show="assemblyStatus === 'warning'">⚠️</span>
            <span x-show="assemblyStatus === 'error'">⛔</span>
            <span x-show="assemblyStatus === 'info'">ℹ️</span>
            <span x-text="assemblyMessage"></span>
        </div>
        <button @click="assemblyMessage = ''" class="opacity-60 hover:opacity-100">✕</button>
    </div>

    {{-- ═══ AREA 3: MEJA KERJA + RAK ALAT KERJA (PBI-004 & PBI-005) ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- Kolom Kiri (3 Kolom): Meja Kerja Slots --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <span>🛠️ Workbench Slots</span>
                            <span class="text-xs bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-300 font-extrabold px-2.5 py-0.5 rounded-full"
                                x-text="`${filledSlotsCount} / 4 Terisi`"></span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Preview bahan terpilih sebelum tindakan perakitan dijalankan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="showRecipeModal = true" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold px-3 py-1.5 rounded-xl text-xs shadow transition flex items-center gap-1">
                            📖 Resep
                        </button>
                        <button
                            @click="clearWorkbench()"
                            :disabled="filledSlotsCount === 0"
                            class="bg-red-500/80 hover:bg-red-600 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1">
                            🔄 Reset
                        </button>
                    </div>
                </div>

                {{-- 4 Slots Grid --}}
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                    <template x-for="(slot, index) in workbenchSlots" :key="index">
                        <div class="relative min-h-[160px] rounded-2xl border-2 transition-all flex flex-col justify-between p-4"
                            :class="slot
                                ? 'border-green-500 bg-gradient-to-b from-green-50 to-white dark:from-gray-700 dark:to-gray-800 shadow-md'
                                : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-800/40'">

                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold tracking-wider uppercase"
                                    :class="slot ? 'text-green-700 dark:text-green-400' : 'text-gray-400'"
                                    x-text="`Slot #${index + 1}`"></span>

                                <template x-if="slot">
                                    <button
                                        @click="removeFromWorkbench(index)"
                                        class="w-6 h-6 bg-red-100 hover:bg-red-200 text-red-600 rounded-full flex items-center justify-center text-xs font-bold transition">
                                        ✕
                                    </button>
                                </template>
                            </div>

                            <div class="my-auto text-center py-2">
                                <template x-if="slot">
                                    <div class="space-y-1.5">
                                        <div class="w-12 h-12 mx-auto bg-white dark:bg-gray-700 rounded-2xl shadow-inner border border-green-200 dark:border-green-800 flex items-center justify-center text-2xl">
                                            <span x-text="slot.icon"></span>
                                        </div>
                                        <h4 class="font-bold text-gray-800 dark:text-white text-xs leading-snug line-clamp-1" x-text="slot.name"></h4>
                                        <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800"
                                            x-text="`+${slot.eco_impact} Eco`"></span>
                                    </div>
                                </template>

                                <template x-if="!slot">
                                    <div class="py-3 flex flex-col items-center text-gray-400">
                                        <div class="w-10 h-10 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center text-lg mb-1">＋</div>
                                        <span class="text-xs">Slot Kosong</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Action Banner: Rakit Produk --}}
            <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-green-50/50 dark:from-gray-700/50 dark:to-gray-800 rounded-2xl border border-green-100 dark:border-gray-600 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-green-600 text-white flex items-center justify-center text-2xl font-bold shadow-md shrink-0">
                        <span x-text="selectedTool ? selectedTool.icon : '⚙️'"></span>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
                            <span>Perakitan Resep</span>
                            <template x-if="selectedTool">
                                <span class="text-xs text-green-700 font-semibold bg-green-100 px-2 py-0.5 rounded-full" x-text="`Alat: ${selectedTool.name}`"></span>
                            </template>
                        </h4>
                        <p class="text-xs text-gray-500" x-show="!canAssemble">
                            <span x-show="filledSlotsCount === 0">Pilih minimal 1 bahan dan 1 alat kerja.</span>
                            <span x-show="filledSlotsCount > 0 && !selectedToolId">Pilih alat kerja di rak kanan ➔</span>
                        </p>
                    </div>
                </div>

                <button
                    @click="executeCrafting()"
                    :disabled="!canAssemble || isCrafting"
                    class="w-full sm:w-auto bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-extrabold px-6 py-3 rounded-xl text-sm shadow-md transition flex items-center justify-center gap-2">
                    <span x-show="!isCrafting">🔨 Rakit Produk (Craft)</span>
                    <span x-show="isCrafting">Memproses...</span>
                </button>
            </div>
        </div>

        {{-- Kolom Kanan: RAK ALAT KERJA --}}
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-4">
                <span>🧰 Rak Perkakas</span>
            </h3>

            <div class="space-y-3">
                <template x-for="tool in tools" :key="tool.id">
                    <div
                        @click="selectedToolId = tool.id"
                        :class="selectedToolId === tool.id
                            ? 'border-green-600 bg-green-50/80 dark:bg-gray-700 ring-2 ring-green-500/30 shadow-sm'
                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'"
                        class="p-3 rounded-2xl border cursor-pointer transition flex items-center justify-between">

                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-xl border border-gray-100"
                                x-text="tool.icon"></div>
                            <div>
                                <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="tool.name"></h4>
                                <span class="text-[10px] text-gray-500 block" x-text="`Tipe: ${tool.action_type}`"></span>
                            </div>
                        </div>

                        <span
                            :class="selectedToolId === tool.id ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400'"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold transition">
                            ✓
                        </span>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- ═══ AREA 4: INVENTARIS PEMAIN ═══ --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span>🎒 Inventaris Bahan Sampah</span>
            </h2>
            <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-3 py-1.5 rounded-xl font-medium">
                💡 Klik bahan untuk memindahkan ke meja kerja
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <template x-for="item in inventory" :key="item.id">
                <div
                    @click="moveToWorkbench(item)"
                    :class="item.quantity > 0
                        ? 'cursor-pointer hover:border-green-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-gray-700 border-gray-200'
                        : 'opacity-40 cursor-not-allowed bg-gray-100 border-dashed border-gray-200'"
                    class="rounded-2xl border p-3 transition-all flex flex-col justify-between text-center relative">

                    <div class="absolute top-2 right-2">
                        <span
                            :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900 font-black' : 'bg-gray-300 text-gray-600'"
                            class="text-[11px] px-2 py-0.5 rounded-full shadow-sm"
                            x-text="`x${item.quantity}`">
                        </span>
                    </div>

                    <div class="py-2">
                        <div class="text-3xl mb-1" x-text="item.icon"></div>
                        <h4 class="font-bold text-xs text-gray-800 dark:text-white leading-tight line-clamp-2" x-text="item.name"></h4>
                        <span class="text-[10px] text-gray-400 block mt-1" x-text="item.category"></span>
                    </div>

                    <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-600">
                        <button
                            type="button"
                            :disabled="item.quantity <= 0"
                            class="w-full text-[11px] font-bold py-1 px-2 rounded-lg transition"
                            :class="item.quantity > 0 ? 'bg-green-50 text-green-700 hover:bg-green-600 hover:text-white' : 'text-gray-400'">
                            <span x-text="item.quantity > 0 ? '+ Pilih' : 'Habis'"></span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- MODAL SUKSES CRAFTING --}}
    <div x-show="craftedProduct" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="craftedProduct = null" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center space-y-4 border border-green-200">
            <div class="w-20 h-20 mx-auto bg-green-100 rounded-3xl flex items-center justify-center text-5xl shadow-inner border border-green-300 animate-bounce">
                <span x-text="craftedProduct ? craftedProduct.output_icon : '✨'"></span>
            </div>

            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-green-600">Produk Selesai!</span>
                <h3 class="text-xl font-black text-gray-800 dark:text-white mt-1" x-text="craftedProduct ? craftedProduct.name : ''"></h3>
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-2xl border border-gray-100">
                <div>
                    <span class="text-[10px] text-gray-400 block font-semibold">Reward Koin</span>
                    <span class="text-sm font-black text-amber-500">💰 +<span x-text="craftedProduct ? craftedProduct.coin_reward : 0"></span></span>
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 block font-semibold">Eco-Impact</span>
                    <span class="text-sm font-black text-emerald-600">🌱 +<span x-text="craftedProduct ? craftedProduct.eco_points : 0"></span></span>
                </div>
            </div>

            <p class="text-xs text-gray-500">Produk ini siap diserahkan ke pelanggan apabila meja saji penuh.</p>

            <button
                @click="craftedProduct = null"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-extrabold py-3 rounded-xl text-sm transition shadow-md">
                OK →
            </button>
        </div>
    </div>

    {{-- MODAL BUKU RESEP --}}
    <div x-show="showRecipeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showRecipeModal = false" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                    <span>📖 Panduan Resep</span>
                </h3>
                <button @click="showRecipeModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="space-y-3">
                <template x-for="r in recipes" :key="r.id">
                    <div class="p-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-xl shadow-sm border border-gray-100"
                                x-text="r.output_icon"></div>
                            <div>
                                <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="r.name"></h4>
                                <div class="flex items-center gap-1 mt-0.5 text-[11px] text-gray-600">
                                    <span class="font-semibold text-green-700">Bahan:</span>
                                    <template x-for="(ing, idx) in r.ingredients" :key="ing.id">
                                        <span x-text="`${ing.icon} ${ing.name}${idx < r.ingredients.length - 1 ? ' + ' : ''}`"></span>
                                    </template>
                                </div>
                                <span class="text-[10px] text-gray-500 block" x-text="`Alat: ${r.tool ? r.tool.name : '-'}`"></span>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="text-xs font-black text-emerald-600 block" x-text="`+${r.eco_points} Eco`"></span>
                            <span class="text-[11px] font-bold text-amber-500 block" x-text="`💰 ${r.coin_reward}`"></span>
                        </div>
                    </div>
                </template>
            </div>

            <button
                @click="showRecipeModal = false"
                class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl text-xs transition">
                Tutup
            </button>
        </div>
    </div>

</div>
@endsection
