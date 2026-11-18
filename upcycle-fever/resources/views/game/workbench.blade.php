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
    craftProgress: 0,
    showRecipeModal: false,
    craftedProduct: null,

    // 4 Slot Meja Kerja (Workbench)
    workbenchSlots: [null, null, null, null],
    assemblyMessage: '',
    assemblyStatus: '',

    // PBI-006 & PBI-010: Pelanggan NPC, Meja Saji, & Manajemen Waktu Stage
    customers: @js($customers ?? []),
    servingPlates: [null, null],
    playerCoins: {{ ($user->coins ?? 265) }},
    playerEcoPoints: {{ ($user->eco_points ?? 10) }},
    playerXp: {{ ($user->xp ?? 0) }},
    rewardPopup: null,
    penaltyPopup: null,
    speedBonusFloat: null,
    servingOrderId: null,
    slotFeedback: {},

    // PBI-010: State Stage Timer & Rekapitulasi Skor
    stageSessionId: {{ $stage['id'] ?? 'null' }},
    stageNumber: {{ $stage['stage_number'] ?? 1 }},
    stageDuration: {{ $stage['duration_seconds'] ?? 120 }},
    stageTimeRemaining: {{ $stage['remaining_seconds'] ?? 120 }},
    spawnInterval: {{ $stage['spawn_interval_seconds'] ?? 15 }},
    nextSpawnCountdown: {{ $stage['spawn_interval_seconds'] ?? 15 }},
    stageEnded: false,
    stageRecap: null,
    isRestartingStage: false,
    stageTimerInterval: null,
    stageOrdersCompleted: {{ $stage['orders_completed'] ?? 0 }},
    stageOrdersExpired: {{ $stage['orders_expired'] ?? 0 }},
    stageCoinsEarned: {{ $stage['coins_earned'] ?? 0 }},
    stageEcoEarned: {{ $stage['eco_points_earned'] ?? 0 }},
    stageXpEarned: {{ $stage['xp_earned'] ?? 0 }},

    // Computed properties
    get filledSlotsCount() {
        return this.workbenchSlots.filter(s => s !== null).length;
    },

    get totalEcoPoints() {
        return this.workbenchSlots.reduce((acc, s) => acc + (s ? s.eco_impact : 0), 0);
    },

    get canAssemble() {
        return !this.stageEnded && this.filledSlotsCount >= 1 && this.selectedToolId !== null;
    },

    get selectedTool() {
        return this.tools.find(t => t.id === this.selectedToolId) || null;
    },

    get formattedStageTime() {
        const secs = Math.max(0, Math.floor(this.stageTimeRemaining));
        const mins = Math.floor(secs / 60);
        const rem = secs % 60;
        return `${String(mins).padStart(2, '0')}:${String(rem).padStart(2, '0')}`;
    },

    get stageTimePercent() {
        return Math.max(0, Math.min(100, (this.stageTimeRemaining / Math.max(1, this.stageDuration)) * 100));
    },

    get customerSlots() {
        return [0, 1, 2].map(idx => this.customers.find(c => c.slot_index === idx && c.status === 'waiting') || null);
    },

    patiencePercent(customer) {
        if (!customer || !customer.patience_max) return 0;
        return Math.max(0, Math.min(100, (customer.patience_remaining / customer.patience_max) * 100));
    },

    setSlotFeedback(slotIndex, type, text) {
        this.slotFeedback[slotIndex] = { type, text };
        setTimeout(() => {
            if (this.slotFeedback[slotIndex] && this.slotFeedback[slotIndex].text === text) {
                delete this.slotFeedback[slotIndex];
            }
        }, 3000);
    },

    showNotification(msg, status = 'info') {
        this.assemblyMessage = msg;
        this.assemblyStatus = status;
        setTimeout(() => {
            if (this.assemblyMessage === msg) {
                this.assemblyMessage = '';
                this.assemblyStatus = '';
            }
        }, 3500);
    },

    moveToWorkbench(item) {
        if (this.stageEnded) return;
        if (item.quantity <= 0) return;
        const emptyIdx = this.workbenchSlots.findIndex(s => s === null);
        if (emptyIdx === -1) {
            this.showNotification('Slot meja kerja penuh (maksimal 4 bahan)!', 'warning');
            return;
        }
        item.quantity--;
        this.workbenchSlots[emptyIdx] = { ...item };
    },

    removeFromWorkbench(index) {
        const item = this.workbenchSlots[index];
        if (!item) return;
        const inv = this.inventory.find(i => i.id === item.id);
        if (inv) inv.quantity++;
        this.workbenchSlots[index] = null;
    },

    clearWorkbench() {
        this.workbenchSlots.forEach((slot, idx) => {
            if (slot) this.removeFromWorkbench(idx);
        });
    },

    async executeCrafting() {
        if (!this.canAssemble || this.isCrafting) return;
        const materialIds = this.workbenchSlots.filter(s => s !== null).map(s => s.id);

        this.isCrafting = true;
        this.craftProgress = 20;

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
            this.craftProgress = 100;

            if (res.ok && data.success) {
                const emptyPlate = this.servingPlates.findIndex(p => p === null);
                if (emptyPlate !== -1) {
                    this.servingPlates[emptyPlate] = data.product;
                } else {
                    this.craftedProduct = data.product;
                }
                this.workbenchSlots = [null, null, null, null];
                if (data.updated_inventory) {
                    this.inventory = data.updated_inventory.map(item => ({
                        id: item.material_id || item.id,
                        name: item.material ? item.material.name : (item.name || 'Bahan'),
                        category: item.material ? item.material.category : (item.category || 'Limbah'),
                        icon: item.material ? item.material.icon : (item.icon || '📦'),
                        eco_impact: item.material ? item.material.eco_impact : (item.eco_impact || 10),
                        description: item.material ? item.material.description : (item.description || ''),
                        quantity: item.quantity !== undefined ? item.quantity : 0
                    }));
                }
                this.showNotification(data.message, 'success');
            } else {
                this.showNotification(data.message || 'Kombinasi salah!', 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi kesalahan jaringan.', 'error');
        } finally {
            setTimeout(() => {
                this.isCrafting = false;
                this.craftProgress = 0;
            }, 250);
        }
    },

    async serveToCustomer(plateIndex, customer) {
        if (this.stageEnded || this.servingOrderId === customer.id) return;
        const product = this.servingPlates[plateIndex];
        if (!product) return;

        if (customer.patience_remaining <= 0 || customer.status !== 'waiting') {
            this.showNotification(`${customer.customer_name} sudah pergi!`, 'error');
            return;
        }

        if (product.id !== customer.recipe_id) {
            this.showNotification(`Produk ini bukan pesanan ${customer.customer_name}!`, 'error');
            return;
        }

        this.servingOrderId = customer.id;
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
                    recipe_id: product.id,
                    client_patience_remaining: customer.patience_remaining
                })
            });

            const data = await res.json();
            if (data.success) {
                this.servingPlates[plateIndex] = null;
                this.playerCoins += data.reward.coins;
                this.playerEcoPoints += data.reward.eco_points;
                this.playerXp += data.reward.xp;
                this.stageOrdersCompleted++;
                this.stageCoinsEarned += data.reward.coins;
                this.stageEcoEarned += data.reward.eco_points;
                this.stageXpEarned += data.reward.xp;

                if (data.speed_bonus || (data.reward && data.reward.speed_bonus_awarded)) {
                    this.speedBonusFloat = {
                        label: data.reward.speed_bonus_label || '+Speed Bonus!',
                        bonus_coins: data.reward.bonus_coins || 0,
                        bonus_score: data.reward.bonus_score || 0
                    };
                    setTimeout(() => this.speedBonusFloat = null, 2500);
                }

                this.setSlotFeedback(customer.slot_index, 'success', `✅ +${data.reward.coins} Koin`);
                const idx = this.customers.findIndex(c => c.id === customer.id);
                if (idx !== -1) {
                    if (data.new_customer) this.customers[idx] = data.new_customer;
                    else this.customers.splice(idx, 1);
                }
            } else {
                this.showNotification(data.message || 'Gagal menyajikan pesanan.', 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi gangguan jaringan.', 'error');
        } finally {
            this.servingOrderId = null;
        }
    },

    async notifyExpiredOrder(customer) {
        if (!customer || customer.status !== 'waiting') return;
        customer.status = 'expired';
        this.setSlotFeedback(customer.slot_index, 'expired', '⏰ Pelanggan Pergi!');
        this.stageOrdersExpired++;
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
            if (data.success && data.penalty) {
                this.playerCoins = Math.max(0, this.playerCoins - (data.penalty.coins || 0));
                this.playerEcoPoints = Math.max(0, this.playerEcoPoints - (data.penalty.eco_points || 0));
            }
        } catch (e) {}
    },

    async triggerPeriodicSpawn() {
        if (this.stageEnded || !this.stageSessionId) return;
        try {
            const res = await fetch('{{ route('api.game.stage.spawn') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ stage_session_id: this.stageSessionId })
            });
            const data = await res.json();
            if (data.stage_ended) {
                this.endStage(data.recap);
                return;
            }
            if (data.success && data.customers) {
                this.customers = data.customers;
            }
        } catch (e) {}
    },

    async finishStageOnBackend() {
        if (!this.stageSessionId) return;
        try {
            const res = await fetch('{{ route('api.game.stage.finish') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ stage_session_id: this.stageSessionId })
            });
            const data = await res.json();
            if (data.success) {
                this.endStage(data.recap);
            }
        } catch (e) {}
    },

    endStage(recap) {
        this.stageEnded = true;
        this.stageTimeRemaining = 0;
        this.stageRecap = recap;
        if (this.stageTimerInterval) clearInterval(this.stageTimerInterval);
    },

    async restartStage() {
        this.isRestartingStage = true;
        try {
            const res = await fetch('{{ route('api.game.stage.start') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ stage_number: this.stageNumber, duration_seconds: this.stageDuration })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (e) {
            this.isRestartingStage = false;
        }
    },

    init() {
        this.stageTimerInterval = setInterval(() => {
            if (this.stageEnded) return;

            if (this.stageTimeRemaining > 0) {
                this.stageTimeRemaining = Math.max(0, this.stageTimeRemaining - 1);
            } else {
                this.finishStageOnBackend();
            }

            this.customers.forEach(customer => {
                if (customer.status === 'waiting') {
                    if (customer.patience_remaining > 0) {
                        customer.patience_remaining = Math.max(0, customer.patience_remaining - 1);
                    } else {
                        this.notifyExpiredOrder(customer);
                    }
                }
            });

            if (this.nextSpawnCountdown > 0) {
                this.nextSpawnCountdown--;
            } else {
                this.nextSpawnCountdown = this.spawnInterval;
                this.triggerPeriodicSpawn();
            }
        }, 1000);
    }
}" class="h-full flex flex-col justify-between gap-2 overflow-hidden select-none">

    {{-- BARIS 1: STATUS BAR RINGKAS (STAGE, TIMER, COINS, ECO, XP) --}}
    <div class="bg-gray-900 text-white rounded-2xl px-3 py-1.5 shadow-md flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <span class="bg-emerald-600 text-white text-xs font-black px-2.5 py-0.5 rounded-lg" x-text="`Stage ${stageNumber}`">Stage 1</span>
            <div class="flex items-center gap-1 font-mono text-xs font-black"
                :class="stageTimeRemaining <= 15 ? 'text-red-400 animate-pulse' : 'text-emerald-300'">
                <span>⏱️</span> <span x-text="formattedStageTime">02:00</span>
            </div>
            <button @click="showRecipeModal = true" class="bg-amber-400 hover:bg-amber-500 text-gray-900 text-[10px] font-black px-2 py-0.5 rounded-md shadow transition">
                📖 Resep
            </button>
        </div>

        {{-- Toast Notifikasi Inline --}}
        <template x-if="assemblyMessage">
            <span class="text-[10px] px-2 py-0.5 rounded font-bold animate-pulse truncate max-w-xs"
                :class="{
                    'bg-emerald-600 text-white': assemblyStatus === 'success',
                    'bg-red-500 text-white': assemblyStatus === 'error',
                    'bg-amber-100 text-amber-900': assemblyStatus === 'warning',
                    'bg-blue-600 text-white': assemblyStatus === 'info'
                }" x-text="assemblyMessage"></span>
        </template>

        <div class="flex items-center gap-3 text-xs font-black">
            <span class="text-amber-400">💰 <span x-text="playerCoins"></span></span>
            <span class="text-emerald-400">🌱 <span x-text="playerEcoPoints"></span></span>
            <div class="hidden sm:flex items-center gap-1">
                <span class="text-[10px] text-gray-400">XP</span>
                <div class="w-16 h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full" :style="`width: ${playerXp % 100}%`"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 2: ANTREAN PELANGGAN (3 SLOTS) & MEJA SAJI --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 shrink-0">
        {{-- Antrean 3 Pelanggan Counter --}}
        <div class="md:col-span-8 grid grid-cols-3 gap-2">
            <template x-for="(customer, slotIdx) in customerSlots" :key="slotIdx">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-2 border-2 transition-all flex flex-col justify-between relative h-28"
                    :class="{
                        'border-red-400 ring-2 ring-red-400/30': customer && patiencePercent(customer) <= 25,
                        'border-amber-300': customer && patiencePercent(customer) > 25 && patiencePercent(customer) <= 50,
                        'border-green-400': customer && patiencePercent(customer) > 50,
                        'border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/50': !customer
                    }">

                    <template x-if="slotFeedback[slotIdx]">
                        <span class="absolute -top-2 left-1/2 -translate-x-1/2 z-10 text-[9px] font-black px-2 py-0.5 rounded-full shadow text-white"
                            :class="slotFeedback[slotIdx].type === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
                            x-text="slotFeedback[slotIdx].text"></span>
                    </template>

                    <template x-if="customer">
                        <div class="h-full flex flex-col justify-between">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[9px] font-black text-gray-800 dark:text-white truncate" x-text="customer.customer_name"></span>
                                <span class="text-[9px] font-black shrink-0"
                                    :class="patiencePercent(customer) > 50 ? 'text-green-600' : (patiencePercent(customer) > 25 ? 'text-amber-500' : 'text-red-500 animate-pulse')"
                                    x-text="`${Math.round(patiencePercent(customer))}%`"></span>
                            </div>

                            <div class="flex items-center justify-center gap-2 my-auto">
                                <span class="text-3xl" x-text="customer.customer_avatar"></span>
                                <div class="bg-green-50 dark:bg-gray-700 border border-green-200 rounded-lg p-1 text-center shrink-0">
                                    <span class="text-xl block leading-none" x-text="customer.recipe_icon"></span>
                                    <span class="text-[9px] font-black block text-green-800 dark:text-green-300 leading-tight truncate max-w-[70px]" x-text="customer.recipe_name"></span>
                                </div>
                            </div>

                            {{-- Patience Bar Horizontal --}}
                            <div class="w-full bg-gray-200 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-1000"
                                    :class="patiencePercent(customer) > 50 ? 'bg-green-500' : (patiencePercent(customer) > 25 ? 'bg-amber-500' : 'bg-red-500 animate-pulse')"
                                    :style="`width: ${patiencePercent(customer)}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!customer">
                        <div class="h-full flex flex-col items-center justify-center text-gray-300 dark:text-gray-600">
                            <span class="text-lg">👤</span>
                            <span class="text-[9px]">Menunggu...</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Meja Saji (2 Piring) --}}
        <div class="md:col-span-4 bg-white dark:bg-gray-800 rounded-2xl p-2 border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <span class="text-[10px] font-black text-gray-400 uppercase">🍽️ Meja Saji</span>
            <div class="grid grid-cols-2 gap-1.5 my-auto">
                <template x-for="(plate, pIdx) in servingPlates" :key="pIdx">
                    <div class="h-20 rounded-xl border-2 flex flex-col items-center justify-center p-1 text-center"
                        :class="plate ? 'border-amber-400 bg-amber-50/60 dark:bg-gray-700' : 'border-dashed border-gray-200 dark:border-gray-700'">
                        <template x-if="plate">
                            <div class="space-y-0.5">
                                <span class="text-2xl block leading-none" x-text="plate.output_icon"></span>
                                <span class="text-[9px] font-black block truncate max-w-[75px]" x-text="plate.name"></span>
                                <div class="flex gap-1 justify-center">
                                    <template x-for="cust in customers.filter(c => c.status === 'waiting' && c.recipe_id === plate.id)" :key="cust.id">
                                        <button @click="serveToCustomer(pIdx, cust)" class="bg-green-600 hover:bg-green-700 text-white text-[8px] font-bold px-1.5 py-0.5 rounded shadow">
                                            Sajikan
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="!plate">
                            <span class="text-[9px] text-gray-300">Piring Kosong</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- BARIS 3: WORKBENCH & RAK PERKAKAS (TOOLS) --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 flex-1 min-h-0">
        {{-- Slot Meja Kerja --}}
        <div class="md:col-span-8 bg-white dark:bg-gray-800 rounded-2xl p-3 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-1 pb-1 border-b border-gray-100 dark:border-gray-700">
                <span class="text-xs font-black text-gray-800 dark:text-white flex items-center gap-1.5">
                    <span>🛠️</span> Meja Kerja (Workbench)
                    <span class="text-[9px] bg-green-100 text-green-800 px-1.5 py-0.2 rounded font-bold" x-text="`${filledSlotsCount}/4`"></span>
                </span>
                <button @click="clearWorkbench()" :disabled="filledSlotsCount === 0" class="text-[10px] text-gray-400 hover:text-red-500 disabled:opacity-30 font-bold">
                    Kosongkan
                </button>
            </div>

            <div class="grid grid-cols-4 gap-2 my-auto">
                <template x-for="(slot, idx) in workbenchSlots" :key="idx">
                    <div class="h-20 sm:h-24 rounded-xl border-2 p-1.5 flex flex-col justify-between text-center relative"
                        :class="slot ? 'border-green-500 bg-green-50/50 dark:bg-gray-700' : 'border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/40'">
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
                    <div @click="selectedToolId = tool.id"
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
        <div class="flex items-center justify-between mb-1.5 pb-1 border-b border-gray-100 dark:border-gray-700">
            <span class="text-xs font-black text-gray-800 dark:text-white flex items-center gap-1.5">
                <span>🎒</span> Rak Inventaris Bahan Sampah
            </span>
            <span class="text-[10px] text-gray-400">💡 Klik bahan untuk meletakkan ke slot meja kerja</span>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
            <template x-for="item in inventory" :key="item.id">
                <div @click="moveToWorkbench(item)"
                    :class="item.quantity > 0 ? 'cursor-pointer hover:border-green-500 bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600' : 'opacity-40 cursor-not-allowed pointer-events-none bg-gray-100 dark:bg-gray-800 border-dashed border-gray-300'"
                    class="rounded-xl border p-1.5 text-center transition flex items-center justify-between gap-1.5 relative">
                    <div class="text-2xl" x-text="item.icon"></div>
                    <div class="text-left flex-1 min-w-0">
                        <h4 class="font-black text-[11px] text-gray-800 dark:text-white truncate" x-text="item.name"></h4>
                        <span class="text-[9px] text-gray-400 block" x-text="item.category"></span>
                    </div>
                    <span :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900' : 'bg-gray-200 text-gray-400'" class="text-[9px] font-black px-1.5 py-0.5 rounded-md shrink-0" x-text="item.quantity > 0 ? `x${item.quantity}` : '0'"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- MODAL BUKU RESEP --}}
    <div x-show="showRecipeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showRecipeModal = false" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-base font-black text-gray-800 dark:text-white">📖 Panduan Resep Upcycling</h3>
                <button @click="showRecipeModal = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>
            <div class="space-y-2">
                <template x-for="r in recipes" :key="r.id">
                    <div class="p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl" x-text="r.output_icon"></span>
                            <div>
                                <h4 class="font-black text-xs text-gray-800 dark:text-white" x-text="r.name"></h4>
                                <div class="text-[10px] text-gray-500">
                                    <template x-for="(ing, i) in r.ingredients" :key="ing.id">
                                        <span x-text="`${ing.icon} ${ing.name}${i < r.ingredients.length - 1 ? ' + ' : ''}`"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <span class="text-[10px] font-black text-emerald-600" x-text="`+${r.eco_points} Eco`"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- MODAL REKAPITULASI SKOR STAGE --}}
    <div x-show="stageEnded" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-sm w-full p-5 text-center space-y-3 border-2 border-emerald-500">
            <h3 class="text-xl font-black text-gray-800 dark:text-white">⏰ Stage Selesai!</h3>
            <div class="p-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-green-600 text-white">
                <span class="text-[10px] uppercase font-bold block">Total Skor</span>
                <span class="text-3xl font-black font-mono" x-text="stageRecap ? stageRecap.total_score : 0"></span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="p-2 bg-green-50 rounded-xl">
                    <span class="text-gray-400 block text-[9px]">Pesanan Selesai</span>
                    <span class="font-black text-green-700" x-text="stageRecap ? stageRecap.orders_completed : stageOrdersCompleted"></span>
                </div>
                <div class="p-2 bg-amber-50 rounded-xl">
                    <span class="text-gray-400 block text-[9px]">Koin Didapat</span>
                    <span class="font-black text-amber-600" x-text="`+${stageRecap ? stageRecap.coins_earned : stageCoinsEarned}`"></span>
                </div>
            </div>
            <button @click="restartStage()" class="w-full bg-green-600 hover:bg-green-700 text-white font-black py-2.5 rounded-xl text-xs shadow">
                🔄 Main Lagi
            </button>
        </div>
    </div>

</div>
@endsection
