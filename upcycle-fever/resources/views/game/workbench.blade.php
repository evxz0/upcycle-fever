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
        }, 2500);
    },

    // PBI-010: Loop timer stage hitung mundur tiap detik, kesabaran pelanggan, & spawn berkala
    init() {
        if (this.stageTimeRemaining <= 0) {
            this.finishStageSession();
            return;
        }
        this.startGameLoop();
    },

    startGameLoop() {
        if (this.stageTimerInterval) clearInterval(this.stageTimerInterval);

        this.stageTimerInterval = setInterval(() => {
            if (this.stageEnded) return;

            // AC 1: Hitung mundur timer stage tiap detik
            if (this.stageTimeRemaining > 0) {
                this.stageTimeRemaining--;
            }

            // AC 2: Timer mencapai 0 → akhiri sesi stage otomatis & tampilkan rekapitulasi skor
            if (this.stageTimeRemaining <= 0) {
                this.finishStageSession();
                return;
            }

            // Timer kesabaran masing-masing pelanggan
            this.customers.forEach(c => {
                if (c.status === 'waiting' && c.patience_remaining > 0) {
                    c.patience_remaining--;
                    if (c.patience_remaining <= 0 && !c._expiring) {
                        c._expiring = true;
                        this.handleExpiredCustomer(c);
                    }
                }
            });

            // Spawn pesanan pelanggan baru secara berkala bila masih ada slot kosong
            const activeCount = this.customers.filter(c => c.status === 'waiting').length;
            if (activeCount < 3) {
                this.nextSpawnCountdown--;
                if (this.nextSpawnCountdown <= 0) {
                    this.nextSpawnCountdown = this.spawnInterval;
                    this.spawnPeriodicCustomer();
                }
            } else {
                this.nextSpawnCountdown = this.spawnInterval;
            }
        }, 1000);
    },

    async spawnPeriodicCustomer() {
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
                this.stageTimeRemaining = 0;
                this.stageEnded = true;
                if (this.stageTimerInterval) clearInterval(this.stageTimerInterval);
                if (data.recap) this.stageRecap = data.recap;
                return;
            }

            if (data.success && data.spawned && data.order) {
                const exists = this.customers.some(c => c.id === data.order.id);
                if (!exists) {
                    this.customers.push(data.order);
                    this.showNotification(`Pelanggan baru tiba: ${data.order.customer_name} memesan ${data.order.recipe_name}!`, 'info');
                }
            } else if (data.next_spawn_in) {
                this.nextSpawnCountdown = data.next_spawn_in;
            }
        } catch (e) {
            // Abaikan error jaringan sementara; loop berikutnya akan mencoba lagi
        }
    },

    async finishStageSession() {
        if (this.stageEnded) return;
        this.stageEnded = true;
        this.stageTimeRemaining = 0;
        if (this.stageTimerInterval) {
            clearInterval(this.stageTimerInterval);
            this.stageTimerInterval = null;
        }

        const buildFallbackRecap = () => {
            const penaltyScore = this.stageOrdersExpired * 20;
            return {
                stage_number: this.stageNumber,
                duration_seconds: this.stageDuration,
                orders_completed: this.stageOrdersCompleted,
                orders_expired: this.stageOrdersExpired,
                coins_earned: this.stageCoinsEarned,
                eco_points_earned: this.stageEcoEarned,
                xp_earned: this.stageXpEarned,
                penalty_score: penaltyScore,
                total_score: Math.max(0, this.stageCoinsEarned + (this.stageEcoEarned * 2) - penaltyScore),
                stars: this.stageOrdersCompleted >= 5 ? 3 : (this.stageOrdersCompleted >= 2 ? 2 : (this.stageOrdersCompleted >= 1 ? 1 : 0))
            };
        };

        if (!this.stageSessionId) {
            this.stageRecap = buildFallbackRecap();
            return;
        }

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
            this.stageRecap = (data.success && data.recap) ? data.recap : buildFallbackRecap();
        } catch (e) {
            this.stageRecap = buildFallbackRecap();
        }
    },

    async restartStage() {
        if (this.isRestartingStage) return;
        this.isRestartingStage = true;

        try {
            const res = await fetch('{{ route('api.game.stage.start') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    stage_number: this.stageNumber,
                    duration_seconds: this.stageDuration,
                    spawn_interval_seconds: this.spawnInterval
                })
            });
            const data = await res.json();

            if (data.success && data.stage) {
                this.stageSessionId = data.stage.id;
                this.stageNumber = data.stage.stage_number;
                this.stageDuration = data.stage.duration_seconds;
                this.stageTimeRemaining = data.stage.remaining_seconds;
                this.spawnInterval = data.stage.spawn_interval_seconds;
                this.nextSpawnCountdown = this.spawnInterval;
                this.customers = data.customers || [];
                this.servingPlates = [null, null];
                this.clearWorkbench();
                this.stageOrdersCompleted = 0;
                this.stageOrdersExpired = 0;
                this.stageCoinsEarned = 0;
                this.stageEcoEarned = 0;
                this.stageXpEarned = 0;
                this.stageRecap = null;
                this.stageEnded = false;
                this.startGameLoop();
                this.showNotification(`Stage ${this.stageNumber} dimulai! Selesaikan pesanan sebelum waktu habis.`, 'success');
            }
        } catch (e) {
            this.showNotification('Gagal memulai ulang stage. Silakan muat ulang halaman.', 'error');
        } finally {
            this.isRestartingStage = false;
        }
    },

    async handleExpiredCustomer(customer) {
        if (this.stageEnded) return;

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
            if (!data.success) return;

            this.stageOrdersExpired++;
            if (data.penalty) {
                this.playerCoins = Math.max(0, this.playerCoins - (data.penalty.coins || 0));
                this.playerEcoPoints = Math.max(0, this.playerEcoPoints - (data.penalty.eco_points || 0));
                this.penaltyPopup = {
                    customer: customer.customer_name,
                    coins: data.penalty.coins || 0,
                    eco_points: data.penalty.eco_points || 0,
                    score: data.penalty.score || 0
                };
                setTimeout(() => this.penaltyPopup = null, 3500);
                this.setSlotFeedback(
                    customer.slot_index,
                    'expired',
                    `😤 ${customer.customer_name} pergi! -${data.penalty.coins} Koin, -${data.penalty.score} Skor`
                );
            }

            const idx = this.customers.findIndex(c => c.id === customer.id);
            if (idx !== -1) {
                if (data.new_customer) {
                    this.customers[idx] = data.new_customer;
                } else {
                    this.customers.splice(idx, 1);
                }
            }
            this.showNotification(data.message || `${customer.customer_name} pergi karena tidak sabar!`, 'warning');
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

    // Aksi PBI-008 & PBI-009: Eksekusi Perakitan Produk dengan Validasi & Progress Bar
    async executeCrafting() {
        if (!this.selectedToolId) {
            this.showNotification('Pilih salah satu perkakas kerja terlebih dahulu!', 'warning');
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
        this.craftProgress = 15;

        // Simulasi progres visual crafting
        const progressTimer = setInterval(() => {
            if (this.craftProgress < 85) {
                this.craftProgress += 20;
            }
        }, 150);

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
            clearInterval(progressTimer);
            this.craftProgress = 100;

            if (res.ok && data.success) {
                // Produk selesai dirakit → letakkan di meja saji
                const emptyPlate = this.servingPlates.findIndex(p => p === null);
                if (emptyPlate !== -1) {
                    this.servingPlates[emptyPlate] = data.product;
                } else {
                    this.craftedProduct = data.product;
                }

                // Kosongkan meja kerja
                this.workbenchSlots = [null, null, null, null];

                // PBI-009 AC 1: Pembaruan stok inventaris secara reaktif tanpa reload penuh
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

                this.showNotification(data.message, 'assembly-success');
            } else {
                // PBI-009 AC 2: Kembalikan item ke inventaris jika perakitan ditolak backend
                this.showNotification(data.message || 'Kombinasi salah atau stok bahan tidak mencukupi!', 'error');
            }
        } catch (e) {
            clearInterval(progressTimer);
            this.showNotification('Terjadi kesalahan jaringan saat perakitan.', 'error');
        } finally {
            setTimeout(() => {
                this.isCrafting = false;
                this.craftProgress = 0;
            }, 300);
        }
    },

    // PBI-006, PBI-010 & PBI-011: Serahkan produk dari meja saji ke pelanggan (anti double-serve & feedback visual)
    async serveToCustomer(plateIndex, customer) {
        if (this.stageEnded) {
            this.showNotification('Waktu sesi stage sudah berakhir!', 'warning');
            return;
        }

        if (this.servingOrderId === customer.id) {
            return;
        }

        const product = this.servingPlates[plateIndex];
        if (!product) {
            this.showNotification('Tidak ada produk di piring ini!', 'warning');
            return;
        }

        if (customer.patience_remaining <= 0 || customer.status !== 'waiting') {
            this.setSlotFeedback(customer.slot_index, 'error', '⏰ Waktu pelanggan sudah habis!');
            this.showNotification(`${customer.customer_name} sudah kehabisan kesabaran!`, 'error');
            return;
        }

        if (product.id !== customer.recipe_id) {
            this.setSlotFeedback(customer.slot_index, 'error', '❌ Produk tidak cocok!');
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
                // Kosongkan piring saji
                this.servingPlates[plateIndex] = null;

                // Tampilkan popup reward & catat skor stage sesi ini
                this.rewardPopup = data.reward;
                this.playerCoins += data.reward.coins;
                this.playerEcoPoints += data.reward.eco_points;
                this.playerXp += data.reward.xp;
                this.stageOrdersCompleted++;
                this.stageCoinsEarned += data.reward.coins;
                this.stageEcoEarned += data.reward.eco_points;
                this.stageXpEarned += data.reward.xp;
                setTimeout(() => this.rewardPopup = null, 3000);

                // PBI-012 AC 2: Floating text "+Speed Bonus!" saat sisa patience > 70%
                if (data.speed_bonus || (data.reward && data.reward.speed_bonus_awarded)) {
                    this.speedBonusFloat = {
                        label: data.reward.speed_bonus_label || '+Speed Bonus!',
                        bonus_coins: data.reward.bonus_coins || 0,
                        bonus_score: data.reward.bonus_score || 0,
                        bonus_eco_points: data.reward.bonus_eco_points || 0,
                        customer: customer.customer_name
                    };
                    setTimeout(() => this.speedBonusFloat = null, 2600);
                }

                this.setSlotFeedback(
                    customer.slot_index,
                    'success',
                    data.speed_bonus
                        ? `⚡ +Speed Bonus! +${data.reward.coins} Koin (+${data.reward.bonus_score} Skor)`
                        : `✅ +${data.reward.coins} Koin`
                );

                // Ganti pelanggan dengan yang baru atau kosongkan slot agar diisi spawn berkala
                const idx = this.customers.findIndex(c => c.id === customer.id);
                if (idx !== -1) {
                    if (data.new_customer) {
                        this.customers[idx] = data.new_customer;
                    } else {
                        this.customers.splice(idx, 1);
                    }
                }

                this.showNotification(data.message, 'assembly-success');
            } else {
                // Jika ditolak karena waktu pelanggan baru saja habis di server
                if (data.expired) {
                    this.stageOrdersExpired++;
                    if (data.penalty) {
                        this.playerCoins = Math.max(0, this.playerCoins - (data.penalty.coins || 0));
                        this.playerEcoPoints = Math.max(0, this.playerEcoPoints - (data.penalty.eco_points || 0));
                        this.penaltyPopup = {
                            customer: customer.customer_name,
                            coins: data.penalty.coins || 0,
                            eco_points: data.penalty.eco_points || 0,
                            score: data.penalty.score || 0
                        };
                        setTimeout(() => this.penaltyPopup = null, 3500);
                    }
                    const idx = this.customers.findIndex(c => c.id === customer.id);
                    if (idx !== -1) {
                        if (data.new_customer) {
                            this.customers[idx] = data.new_customer;
                        } else {
                            this.customers.splice(idx, 1);
                        }
                    }
                    this.setSlotFeedback(customer.slot_index, 'expired', `😤 ${customer.customer_name} pergi!`);
                } else {
                    this.setSlotFeedback(customer.slot_index, 'error', '❌ Penyerahan gagal');
                }
                this.showNotification(data.message, 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi kesalahan jaringan.', 'error');
        } finally {
            this.servingOrderId = null;
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

    {{-- ═══ TOP BAR: Status Pemain & Timer Hitung Mundur Stage (PBI-010 AC 1) ═══ --}}
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 text-white rounded-2xl px-5 py-3 shadow-xl space-y-2.5">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('game.lobby') }}" class="bg-gray-700 hover:bg-gray-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition">☰ MENU</a>
                <span class="bg-blue-600 text-white text-xs font-black px-3 py-1 rounded-lg flex items-center gap-1">
                    👥 <span x-text="`${customers.filter(c => c.status === 'waiting').length}/3`">1/3</span>
                </span>
                <span class="bg-emerald-700/80 text-emerald-100 text-xs font-bold px-2.5 py-1 rounded-lg"
                    x-text="`Stage ${stageNumber}`">Stage 1</span>
            </div>

            {{-- Visual Countdown Timer Stage (PBI-010 AC 1) --}}
            <div class="flex items-center gap-2 px-4 py-1.5 rounded-xl border font-mono font-black text-base tracking-wider transition-all"
                :class="{
                    'bg-emerald-900/50 border-emerald-500/50 text-emerald-300': stageTimeRemaining > 30,
                    'bg-amber-900/60 border-amber-400/70 text-amber-300': stageTimeRemaining <= 30 && stageTimeRemaining > 10,
                    'bg-red-900/70 border-red-500 text-red-200 animate-pulse': stageTimeRemaining <= 10
                }"
                data-testid="stage-countdown-timer">
                <span>⏱️</span>
                <span x-text="formattedStageTime">02:00</span>
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

        {{-- Progress Bar Waktu Stage --}}
        <div class="w-full h-1.5 bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-1000"
                :class="{
                    'bg-emerald-400': stageTimeRemaining > 30,
                    'bg-amber-400': stageTimeRemaining <= 30 && stageTimeRemaining > 10,
                    'bg-red-500': stageTimeRemaining <= 10
                }"
                :style="`width: ${stageTimePercent}%`"></div>
        </div>
    </div>

    {{-- ═══ AREA 1: PELANGGAN NPC (3 Slot Counter + Spawn Berkala) — PBI-006 & PBI-010 ═══ --}}
    <div class="bg-gradient-to-b from-emerald-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-3xl p-6 shadow-sm border border-emerald-100 dark:border-gray-700">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span>👥 Antrian Pelanggan Studio</span>
                <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 px-2.5 py-0.5 rounded-full font-extrabold"
                    x-text="`${customers.filter(c => c.status === 'waiting').length} / 3 menunggu`"></span>
            </h2>
            <span x-show="!stageEnded && customers.filter(c => c.status === 'waiting').length < 3"
                class="text-xs font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 px-3 py-1 rounded-full">
                ⏳ Pelanggan berikutnya dalam <span x-text="`${nextSpawnCountdown}s`"></span>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <template x-for="(customer, slotIdx) in customerSlots" :key="slotIdx">
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl border-2 p-4 transition-all min-h-[220px] flex flex-col justify-center"
                    :class="{
                        'border-red-400 dark:border-red-600 shadow-md ring-2 ring-red-400/30': customer && patiencePercent(customer) <= 25,
                        'border-amber-300 dark:border-amber-600 shadow-md': customer && patiencePercent(customer) > 25 && patiencePercent(customer) <= 50,
                        'border-green-300 dark:border-green-700 shadow-md': customer && patiencePercent(customer) > 50,
                        'border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40': !customer
                    }">

                    {{-- Umpan Balik Visual Transient pada Slot (Sukses / Gagal / Pergi+Penalti) — PBI-011 --}}
                    <template x-if="slotFeedback[slotIdx]">
                        <div x-transition
                            class="absolute -top-3 left-1/2 -translate-x-1/2 z-10 whitespace-nowrap text-[11px] font-extrabold px-3 py-1 rounded-full shadow-md"
                            :class="{
                                'bg-emerald-600 text-white': slotFeedback[slotIdx].type === 'success',
                                'bg-red-600 text-white': slotFeedback[slotIdx].type === 'error' || slotFeedback[slotIdx].type === 'expired'
                            }"
                            x-text="slotFeedback[slotIdx].text"></div>
                    </template>

                    <template x-if="customer">
                        <div class="flex items-stretch justify-between gap-3">
                            {{-- Kolom Kiri: Balon Pesanan & Avatar NPC --}}
                            <div class="flex-1 text-center flex flex-col justify-between">
                                {{-- Badge Status Kesabaran: Speed Bonus (>70%) / Mendesak (<=25%) --}}
                                <div class="min-h-[20px] mb-1">
                                    <span x-show="patiencePercent(customer) > 70"
                                        class="inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                        ⚡ Speed Bonus!
                                    </span>
                                    <span x-show="patiencePercent(customer) <= 25"
                                        class="inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-700 animate-pulse">
                                        🔥 Mendesak!
                                    </span>
                                </div>

                                {{-- Balon Dialog Pesanan --}}
                                <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl p-2.5 mb-3 shadow-sm relative">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-2xl" x-text="customer.recipe_icon"></span>
                                        <span class="text-xs font-bold text-gray-800 dark:text-white leading-tight" x-text="customer.recipe_name"></span>
                                    </div>
                                    <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white dark:bg-gray-700 border-r border-b border-gray-200 dark:border-gray-600 rotate-45"></div>
                                </div>

                                {{-- Avatar & Nama NPC --}}
                                <div>
                                    <div class="text-4xl mb-1" x-text="customer.customer_avatar"></div>
                                    <h4 class="font-bold text-sm text-gray-800 dark:text-white" x-text="customer.customer_name"></h4>
                                </div>
                            </div>

                            {{-- Kolom Kanan: Patience Bar VERTIKAL Berwarna Hijau (PBI-011 AC 1) --}}
                            <div class="flex flex-col items-center justify-between py-1 shrink-0" data-testid="patience-bar-vertical">
                                <span class="text-[10px] font-black uppercase tracking-tight"
                                    :class="{
                                        'text-green-600 dark:text-green-400': patiencePercent(customer) > 50,
                                        'text-amber-600 dark:text-amber-400': patiencePercent(customer) <= 50 && patiencePercent(customer) > 25,
                                        'text-red-600 dark:text-red-400': patiencePercent(customer) <= 25
                                    }"
                                    x-text="`${Math.round(patiencePercent(customer))}%`"></span>

                                <div class="w-4 h-28 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden flex flex-col justify-end border border-gray-300/70 dark:border-gray-600 shadow-inner my-1"
                                    role="progressbar"
                                    :aria-valuenow="customer.patience_remaining"
                                    aria-valuemin="0"
                                    :aria-valuemax="customer.patience_max">
                                    <div class="w-full rounded-full transition-all duration-1000 ease-linear"
                                        data-testid="patience-bar-fill"
                                        :class="{
                                            'bg-green-500': patiencePercent(customer) > 50,
                                            'bg-amber-500': patiencePercent(customer) <= 50 && patiencePercent(customer) > 25,
                                            'bg-red-500 animate-pulse': patiencePercent(customer) <= 25
                                        }"
                                        :style="`height: ${patiencePercent(customer)}%`"></div>
                                </div>

                                <span class="text-[11px] font-mono font-black"
                                    :class="patiencePercent(customer) <= 25 ? 'text-red-600 animate-pulse' : 'text-gray-600 dark:text-gray-300'"
                                    x-text="`${customer.patience_remaining}s`"></span>
                            </div>
                        </div>
                    </template>

                    <template x-if="!customer">
                        <div class="text-center text-gray-400 space-y-1.5 py-4">
                            <div class="text-3xl opacity-50">🪑</div>
                            <p class="text-xs font-bold">Slot Antrean #<span x-text="slotIdx + 1"></span></p>
                            <p class="text-[11px]">Menunggu pelanggan baru...</p>
                        </div>
                    </template>
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
                                        :disabled="servingOrderId === customer.id || stageEnded"
                                        class="bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition flex items-center gap-1">
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

    {{-- Floating Text "+Speed Bonus!" saat penyerahan cepat (>70% patience) — PBI-012 AC 2 --}}
    <style>
        @keyframes speedBonusFloatUp {
            0%   { opacity: 0; transform: translate(-50%, 16px) scale(0.9); }
            15%  { opacity: 1; transform: translate(-50%, 0) scale(1.06); }
            80%  { opacity: 1; transform: translate(-50%, -22px) scale(1); }
            100% { opacity: 0; transform: translate(-50%, -40px) scale(0.96); }
        }
        .animate-speed-bonus {
            animation: speedBonusFloatUp 2.6s ease-out forwards;
        }
    </style>
    <div x-show="speedBonusFloat" x-cloak data-testid="speed-bonus-floating"
        class="fixed top-28 left-1/2 z-50 pointer-events-none animate-speed-bonus">
        <div class="bg-gradient-to-r from-emerald-500 via-green-500 to-amber-400 text-white px-6 py-3 rounded-2xl shadow-2xl border-2 border-white/80 text-center">
            <div class="text-xl font-black tracking-wide drop-shadow" x-text="speedBonusFloat ? `⚡ ${speedBonusFloat.label}` : '+Speed Bonus!'">+Speed Bonus!</div>
            <div class="text-xs font-extrabold text-amber-50 mt-0.5 flex items-center justify-center gap-3">
                <span x-text="speedBonusFloat ? `💰 +${speedBonusFloat.bonus_coins} Koin` : ''"></span>
                <span x-text="speedBonusFloat ? `🏆 +${speedBonusFloat.bonus_score} Skor` : ''"></span>
                <span x-show="speedBonusFloat && speedBonusFloat.bonus_eco_points > 0"
                    x-text="speedBonusFloat ? `🌱 +${speedBonusFloat.bonus_eco_points} Eco` : ''"></span>
            </div>
        </div>
    </div>

    {{-- Reward Popup (Sukses Penyerahan + Bonus Kesabaran) --}}
    <div x-show="rewardPopup" x-cloak x-transition data-testid="reward-popup" class="fixed top-20 right-6 z-50 bg-gradient-to-br from-amber-400 to-amber-500 text-gray-900 rounded-2xl p-4 shadow-2xl max-w-xs animate-bounce">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🎉</span>
            <div>
                <span class="font-black text-sm block" x-text="rewardPopup && rewardPopup.speed_bonus_awarded ? '⚡ +Speed Bonus!' : 'Reward Diterima!'"></span>
                <span class="text-xs font-bold block" x-text="rewardPopup ? `💰 +${rewardPopup.coins} Koin  🌱 +${rewardPopup.eco_points} Eco` : ''"></span>
                <span x-show="rewardPopup && rewardPopup.bonus_coins > 0" class="text-[10px] font-extrabold text-amber-950 block" x-text="rewardPopup ? `Bonus Cepat: +${rewardPopup.bonus_coins} Koin, +${rewardPopup.bonus_score} Skor` : ''"></span>
            </div>
        </div>
    </div>

    {{-- Penalty Popup (Pelanggan Pergi Karena Kesabaran Habis — PBI-011 & PBI-012) --}}
    <div x-show="penaltyPopup" x-cloak x-transition data-testid="penalty-popup" class="fixed top-40 right-6 z-50 bg-gradient-to-br from-red-600 to-rose-700 text-white rounded-2xl p-4 shadow-2xl max-w-xs border border-red-300">
        <div class="flex items-center gap-3">
            <span class="text-3xl">😤</span>
            <div>
                <span class="font-black text-sm block" x-text="penaltyPopup ? `${penaltyPopup.customer} Pergi!` : 'Pelanggan Pergi!'"></span>
                <span class="text-xs font-bold text-red-100 block" x-text="penaltyPopup ? `Penalti: -${penaltyPopup.coins} Koin  -${penaltyPopup.eco_points} Eco  -${penaltyPopup.score} Skor` : ''"></span>
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
                    <span x-show="isCrafting" x-cloak>Sedang Merakit...</span>
                </button>
            </div>

            {{-- Progress Bar Visual saat Crafting Berlangsung (PBI-008 Tech Task) --}}
            <div x-show="isCrafting" x-transition class="mt-4 p-4 bg-emerald-50 dark:bg-gray-700/70 rounded-2xl border border-emerald-200 dark:border-gray-600 space-y-2">
                <div class="flex justify-between items-center text-xs font-bold text-emerald-900 dark:text-emerald-200">
                    <span class="flex items-center gap-2">
                        <span class="animate-spin text-sm">⚙️</span> Memproses perakitan bahan limbah di meja kerja...
                    </span>
                    <span class="font-mono text-emerald-700 dark:text-emerald-300 font-extrabold" x-text="`${craftProgress}%`"></span>
                </div>
                <div class="w-full bg-emerald-200/60 dark:bg-gray-600 h-2.5 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 via-emerald-500 to-teal-500 h-full rounded-full transition-all duration-300"
                        :style="`width: ${craftProgress}%`"></div>
                </div>
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
                        : 'opacity-40 cursor-not-allowed pointer-events-none bg-gray-100 dark:bg-gray-800 border-dashed border-gray-300 dark:border-gray-700'"
                    class="rounded-2xl border p-3 transition-all flex flex-col justify-between text-center relative">

                    <div class="absolute top-2 right-2">
                        <span
                            :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900 font-black' : 'bg-gray-300 text-gray-600 font-bold'"
                            class="text-[11px] px-2 py-0.5 rounded-full shadow-sm"
                            x-text="item.quantity > 0 ? `x${item.quantity}` : 'Habis'">
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
                            :class="item.quantity > 0 ? 'bg-green-50 text-green-700 hover:bg-green-600 hover:text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                            <span x-text="item.quantity > 0 ? '+ Pilih' : 'Stok Habis'"></span>
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

    {{-- MODAL BUKU RESEP & PANDUAN EDUKASI UP CYCLING LOKAL (NON-AI) --}}
    <div x-show="showRecipeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showRecipeModal = false" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                        <span>📖 Panduan Resep & Edukasi Upcycling</span>
                    </h3>
                    <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">🌿 Mendukung SDG 12 Target 12.5 (Pengurangan Limbah)</span>
                </div>
                <button @click="showRecipeModal = false" class="text-gray-400 hover:text-gray-600 font-bold text-lg">✕</button>
            </div>

            <div class="bg-emerald-50 dark:bg-gray-700/60 p-3 rounded-2xl border border-emerald-200 dark:border-gray-600 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-extrabold text-emerald-800 dark:text-emerald-300 block mb-0.5">💡 Tips Meja Kerja Studio:</span>
                Pilih bahan yang sesuai formula resep di bawah dan pastikan perkakas yang cocok sudah aktif sebelum menekan tombol <b>Rakit Produk</b>.
            </div>

            <div class="space-y-3">
                <template x-for="r in recipes" :key="r.id">
                    <div class="p-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/40 space-y-2">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 bg-white dark:bg-gray-600 rounded-xl flex items-center justify-center text-2xl shadow-sm border border-gray-100"
                                    x-text="r.output_icon"></div>
                                <div>
                                    <h4 class="font-extrabold text-xs text-gray-800 dark:text-white" x-text="r.name"></h4>
                                    <div class="flex items-center gap-1 mt-0.5 text-[11px] text-gray-600 dark:text-gray-300">
                                        <span class="font-bold text-green-700 dark:text-green-400">Bahan:</span>
                                        <template x-for="(ing, idx) in r.ingredients" :key="ing.id">
                                            <span x-text="`${ing.icon} ${ing.name}${idx < r.ingredients.length - 1 ? ' + ' : ''}`"></span>
                                        </template>
                                    </div>
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400 block" x-text="`Alat: ${r.tool ? r.tool.name : '-'}`"></span>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-emerald-600 block" x-text="`+${r.eco_points} Eco`"></span>
                                <span class="text-[11px] font-bold text-amber-500 block" x-text="`💰 ${r.coin_reward}`"></span>
                            </div>
                        </div>
                        <template x-if="r.description">
                            <p class="text-[11px] text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800 p-2 rounded-xl border border-gray-100 dark:border-gray-700" x-text="`Fakta: ${r.description}`"></p>
                        </template>
                    </div>
                </template>
            </div>

            <button
                @click="showRecipeModal = false"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-extrabold py-2.5 rounded-xl text-xs transition shadow">
                Tutup Panduan
            </button>
        </div>
    </div>

    {{-- MODAL REKAPITULASI SKOR STAGE (PBI-010 AC 2) --}}
    <div x-show="stageEnded" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4" data-testid="stage-score-recap">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full p-6 text-center space-y-5 border-2 border-emerald-500">
            <div class="space-y-1">
                <span class="inline-block text-xs font-black uppercase tracking-wider px-3 py-1 rounded-full bg-amber-100 text-amber-800">
                    ⏰ Waktu Sesi Stage Berakhir
                </span>
                <h3 class="text-2xl font-black text-gray-800 dark:text-white mt-2">
                    Rekapitulasi Skor — Stage <span x-text="stageRecap ? stageRecap.stage_number : stageNumber"></span>
                </h3>
                {{-- Rating Bintang --}}
                <div class="text-3xl tracking-widest pt-1">
                    <template x-for="i in 3" :key="i">
                        <span x-text="stageRecap && i <= stageRecap.stars ? '⭐' : '☆'"></span>
                    </template>
                </div>
            </div>

            {{-- Total Skor --}}
            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-green-600 text-white shadow-inner">
                <span class="text-[11px] uppercase tracking-wider font-bold text-emerald-100 block">Total Skor Stage</span>
                <span class="text-4xl font-black font-mono" x-text="stageRecap ? stageRecap.total_score : 0">0</span>
            </div>

            {{-- Rincian Statistik Stage --}}
            <div class="grid grid-cols-2 gap-3 text-left">
                <div class="p-3 rounded-2xl bg-green-50 dark:bg-gray-700/60 border border-green-100 dark:border-gray-600">
                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-300 block">Pesanan Selesai</span>
                    <span class="text-lg font-black text-green-700 dark:text-green-400">✅ <span x-text="stageRecap ? stageRecap.orders_completed : stageOrdersCompleted">0</span></span>
                </div>
                <div class="p-3 rounded-2xl bg-red-50 dark:bg-gray-700/60 border border-red-100 dark:border-gray-600">
                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-300 block">Pelanggan Pergi</span>
                    <span class="text-lg font-black text-red-600 dark:text-red-400">😤 <span x-text="stageRecap ? stageRecap.orders_expired : stageOrdersExpired">0</span></span>
                    <span x-show="stageRecap && stageRecap.penalty_score > 0" class="text-[10px] font-bold text-red-500 block" x-text="stageRecap ? `Penalti: -${stageRecap.penalty_score} skor` : ''"></span>
                </div>
                <div class="p-3 rounded-2xl bg-amber-50 dark:bg-gray-700/60 border border-amber-100 dark:border-gray-600">
                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-300 block">Koin Diperoleh</span>
                    <span class="text-lg font-black text-amber-600 dark:text-amber-400">💰 +<span x-text="stageRecap ? stageRecap.coins_earned : stageCoinsEarned">0</span></span>
                </div>
                <div class="p-3 rounded-2xl bg-emerald-50 dark:bg-gray-700/60 border border-emerald-100 dark:border-gray-600">
                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-300 block">Eco-Points & XP</span>
                    <span class="text-sm font-black text-emerald-700 dark:text-emerald-400">
                        🌱 +<span x-text="stageRecap ? stageRecap.eco_points_earned : stageEcoEarned">0</span>
                        <span class="text-purple-600 dark:text-purple-300 ml-1">⚡ +<span x-text="stageRecap ? stageRecap.xp_earned : stageXpEarned">0</span></span>
                    </span>
                </div>
            </div>

            {{-- Tombol Aksi --}}
            <div class="space-y-2 pt-1">
                <button
                    @click="restartStage()"
                    :disabled="isRestartingStage"
                    class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-extrabold py-3 rounded-xl text-sm transition shadow-md">
                    <span x-show="!isRestartingStage">🔄 Main Lagi (Stage Baru)</span>
                    <span x-show="isRestartingStage" x-cloak>Memulai Stage Baru...</span>
                </button>
                <a href="{{ route('game.lobby') }}"
                    class="w-full block border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-bold py-2.5 rounded-xl text-xs transition">
                    🏠 Kembali ke Lobi Game
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
