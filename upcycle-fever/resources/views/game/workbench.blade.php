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

        // Kurangi stok di inventaris pemain (sementara di UI)
        item.quantity--;

        // Masukkan ke slot meja kerja
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

    // Aksi: Mengembalikan bahan dari slot meja kerja ke inventaris
    removeFromWorkbench(slotIndex) {
        const slotItem = this.workbenchSlots[slotIndex];
        if (!slotItem) return;

        const invItem = this.inventory.find(i => i.id === slotItem.id);
        if (invItem) {
            invItem.quantity++;
        }

        this.workbenchSlots[slotIndex] = null;
        this.showNotification(`${slotItem.name} dikembalikan ke inventaris.`, 'info');
    },

    // Aksi: Kosongkan seluruh slot meja kerja
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

    // Aksi PBI-005: Eksekusi Perakitan Produk dengan Validasi Alat & Resep (AC 1 & AC 2)
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
                // AC 1: Status produk valid, tampilkan preview produk jadi
                this.craftedProduct = data.product;
                // Kosongkan slot meja kerja karena bahan sudah terpakai
                this.workbenchSlots = [null, null, null, null];
                this.showNotification(data.message, 'assembly-success');
            } else {
                // AC 2: Jika salah, tampilkan pesan gagal, bahan tetap di meja kerja (tidak hangus)
                this.showNotification(data.message || 'Kombinasi salah!', 'error');
            }
        } catch (e) {
            this.showNotification('Terjadi kesalahan jaringan saat perakitan.', 'error');
        } finally {
            this.isCrafting = false;
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
}" class="space-y-8">

    {{-- Header Section --}}
    <div class="bg-gradient-to-r from-emerald-800 via-green-700 to-teal-800 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-green-100 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3">
                    <span>Feature F-02: Sistem Crafting & Resep (PBI-004 & PBI-005)</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight flex items-center gap-3">
                    <span>🛠️ Meja Kerja Perakitan</span>
                </h1>
                <p class="text-green-100 mt-2 text-sm sm:text-base max-w-2xl leading-relaxed">
                    Pilih bahan baku limbah, tentukan alat kerja yang sesuai, dan rakit menjadi produk upcycle ramah lingkungan berdaya guna tinggi!
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <button @click="showRecipeModal = true" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold px-4 py-2.5 rounded-xl text-sm shadow transition flex items-center gap-2">
                    <span>📖</span> Buku Resep
                </button>
                <a href="{{ route('materials.index') }}" class="bg-white/20 hover:bg-white/30 text-white font-bold px-4 py-2.5 rounded-xl text-sm transition flex items-center gap-2 border border-white/20">
                    <span>📋</span> Katalog Sampah
                </a>
                <button 
                    @click="clearWorkbench()" 
                    :disabled="filledSlotsCount === 0"
                    class="bg-red-500/80 hover:bg-red-600 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold px-4 py-2.5 rounded-xl text-sm transition flex items-center gap-2">
                    <span>🔄</span> Reset Meja
                </button>
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

    {{-- AREA UTAMA: MEJA KERJA + RAK ALAT KERJA (PBI-005) --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- Kolom Kiri (3 Kolom): Meja Kerja Slots (PBI-004 & PBI-005) --}}
        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <span>Workbench Slots</span>
                            <span class="text-xs bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-300 font-extrabold px-2.5 py-0.5 rounded-full"
                                x-text="`${filledSlotsCount} / 4 Terisi`"></span>
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Preview bahan terpilih sebelum tindakan perakitan dijalankan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-gray-500">Total Eco-Impact Bahan:</span>
                        <span class="bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-black text-sm px-3 py-1 rounded-xl flex items-center gap-1">
                            <span>🌱</span> +<span x-text="totalEcoPoints"></span> Poin
                        </span>
                    </div>
                </div>

                {{-- 4 Slots Meja Kerja Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <template x-for="(slot, index) in workbenchSlots" :key="index">
                        <div class="relative min-h-[200px] rounded-2xl border-2 transition-all flex flex-col justify-between p-4"
                            :class="slot 
                                ? 'border-green-500 bg-gradient-to-b from-green-50 to-white dark:from-gray-700 dark:to-gray-800 shadow-md transform -translate-y-1' 
                                : 'border-dashed border-gray-300 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-800/40 hover:border-green-400'">
                            
                            {{-- Slot Header --}}
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold tracking-wider uppercase"
                                    :class="slot ? 'text-green-700 dark:text-green-400' : 'text-gray-400'"
                                    x-text="`Slot #${index + 1}`"></span>

                                <template x-if="slot">
                                    <button 
                                        @click="removeFromWorkbench(index)" 
                                        title="Keluarkan dari meja kerja"
                                        class="w-6 h-6 bg-red-100 hover:bg-red-200 text-red-600 rounded-full flex items-center justify-center text-xs font-bold transition shadow-sm">
                                        ✕
                                    </button>
                                </template>
                            </div>

                            {{-- Konten Slot (Preview Bahan Terpilih) --}}
                            <div class="my-auto text-center py-2">
                                <template x-if="slot">
                                    <div class="space-y-1.5">
                                        <div class="w-14 h-14 mx-auto bg-white dark:bg-gray-700 rounded-2xl shadow-inner border border-green-200 dark:border-green-800 flex items-center justify-center text-3xl">
                                            <span x-text="slot.icon"></span>
                                        </div>
                                        <h4 class="font-bold text-gray-800 dark:text-white text-xs leading-snug line-clamp-1" x-text="slot.name"></h4>
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <span class="text-[9px] font-semibold px-2 py-0.5 rounded-full bg-green-200/60 dark:bg-green-900/60 text-green-800 dark:text-green-200"
                                                x-text="slot.category"></span>
                                            <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800"
                                                x-text="`+${slot.eco_impact} Eco`"></span>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!slot">
                                    <div class="py-5 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                        <div class="w-10 h-10 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-lg mb-1.5">
                                            ＋
                                        </div>
                                        <span class="text-xs font-medium">Slot Kosong</span>
                                        <span class="text-[10px] text-gray-400">Pilih dari inventaris</span>
                                    </div>
                                </template>
                            </div>

                            {{-- Slot Footer --}}
                            <div class="pt-2 text-center border-t border-gray-100 dark:border-gray-700">
                                <template x-if="slot">
                                    <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        ✓ Bahan Terpasang
                                    </span>
                                </template>
                                <template x-if="!slot">
                                    <span class="text-[10px] text-gray-400 italic">Menunggu input</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Action Banner: Rakit Produk Sekarang (PBI-005) --}}
            <div class="mt-6 p-4 sm:p-5 bg-gradient-to-r from-gray-50 to-green-50/50 dark:from-gray-700/50 dark:to-gray-800 rounded-2xl border border-green-100 dark:border-gray-600 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-green-600 text-white flex items-center justify-center text-2xl font-bold shadow-md shrink-0">
                        <span x-text="selectedTool ? selectedTool.icon : '⚙️'"></span>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
                            <span>Perakitan Resep Crafting</span>
                            <template x-if="selectedTool">
                                <span class="text-xs text-green-700 font-semibold bg-green-100 px-2 py-0.5 rounded-full" x-text="`Alat: ${selectedTool.name}`"></span>
                            </template>
                        </h4>
                        <p class="text-xs text-gray-500" x-show="!canAssemble">
                            <span x-show="filledSlotsCount === 0">Pilih minimal 1 bahan dan 1 alat kerja.</span>
                            <span x-show="filledSlotsCount > 0 && !selectedToolId">Silakan pilih salah satu alat kerja di rak kanan ➔</span>
                        </p>
                        <p class="text-xs text-green-700 dark:text-green-300 font-medium" x-show="canAssemble">
                            Kombinasi <strong x-text="filledSlotsCount"></strong> bahan dengan alat <strong x-text="selectedTool.name"></strong> siap dirakit!
                        </p>
                    </div>
                </div>

                <button 
                    @click="executeCrafting()"
                    :disabled="!canAssemble || isCrafting"
                    class="w-full sm:w-auto bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-extrabold px-6 py-3 rounded-xl text-sm shadow-md transition flex items-center justify-center gap-2">
                    <span x-show="!isCrafting">🔨 Rakit Produk Sekarang (Craft)</span>
                    <span x-show="isCrafting">Memproses Perakitan...</span>
                </button>
            </div>
        </div>

        {{-- Kolom Kanan (1 Kolom): RAK ALAT KERJA / TOOLS SHELF (PBI-005) --}}
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2 mb-1">
                    <span>🧰 Rak Perkakas</span>
                </h3>
                <p class="text-xs text-gray-500 mb-4">Pilih alat yang sesuai dengan resep yang ingin dirakit.</p>

                <div class="space-y-3">
                    <template x-for="tool in tools" :key="tool.id">
                        <div 
                            @click="selectedToolId = tool.id"
                            :class="selectedToolId === tool.id 
                                ? 'border-green-600 bg-green-50/80 dark:bg-gray-700 ring-2 ring-green-500/30 shadow-sm' 
                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-700/50'"
                            class="p-3.5 rounded-2xl border cursor-pointer transition flex items-center justify-between group">
                            
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-white dark:bg-gray-600 shadow-sm flex items-center justify-center text-xl border border-gray-100 dark:border-gray-500 group-hover:scale-105 transition"
                                    x-text="tool.icon"></div>
                                <div>
                                    <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="tool.name"></h4>
                                    <span class="text-[10px] text-gray-500 block capitalize" x-text="`Tipe: ${tool.action_type}`"></span>
                                </div>
                            </div>

                            <div>
                                <span 
                                    :class="selectedToolId === tool.id ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400'"
                                    class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold transition">
                                    ✓
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 text-center">
                <button 
                    @click="showRecipeModal = true"
                    class="text-xs text-green-600 dark:text-green-400 font-bold hover:underline flex items-center justify-center gap-1 mx-auto">
                    <span>🔍</span> Panduan Formula Resep
                </button>
            </div>
        </div>

    </div>

    {{-- AREA 2: INVENTARIS PEMAIN (PBI-004) --}}
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <span>🎒 Inventaris Bahan Sampah Pemain</span>
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">Klik pada bahan sampah untuk meletakkannya ke slot meja kerja di atas.</p>
            </div>
            <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-3 py-1.5 rounded-xl font-medium">
                💡 Klik bahan untuk memindahkan ke meja kerja
            </span>
        </div>

        {{-- Grid Kartu Bahan Inventaris --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <template x-for="item in inventory" :key="item.id">
                <div 
                    @click="moveToWorkbench(item)"
                    :class="item.quantity > 0 
                        ? 'cursor-pointer hover:border-green-500 hover:shadow-lg hover:-translate-y-1 bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600' 
                        : 'opacity-40 cursor-not-allowed bg-gray-100 dark:bg-gray-800 border-dashed border-gray-200'"
                    class="rounded-2xl border p-3.5 transition-all flex flex-col justify-between text-center relative group">

                    {{-- Badge Jumlah Stok (Quantity) --}}
                    <div class="absolute top-2 right-2">
                        <span 
                            :class="item.quantity > 0 ? 'bg-amber-400 text-gray-900 font-black' : 'bg-gray-300 text-gray-600'"
                            class="text-[11px] px-2 py-0.5 rounded-full shadow-sm"
                            x-text="`x${item.quantity}`">
                        </span>
                    </div>

                    {{-- Ikon & Nama --}}
                    <div class="py-2">
                        <div class="text-3xl mb-1.5 group-hover:scale-110 transition transform" x-text="item.icon"></div>
                        <h4 class="font-bold text-xs text-gray-800 dark:text-white leading-tight line-clamp-2" x-text="item.name"></h4>
                        <span class="text-[10px] text-gray-400 block mt-1" x-text="item.category"></span>
                    </div>

                    {{-- Tombol Aksi --}}
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

    {{-- MODAL SUKSES CRAFTING (CELEBRATION MODAL) --}}
    <div x-show="craftedProduct" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="craftedProduct = null" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center space-y-4 border border-green-200">
            <div class="w-20 h-20 mx-auto bg-green-100 dark:bg-green-900/60 rounded-3xl flex items-center justify-center text-5xl shadow-inner border border-green-300 animate-bounce">
                <span x-text="craftedProduct ? craftedProduct.output_icon : '✨'"></span>
            </div>

            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-green-600 dark:text-green-400">Karya Upcycling Selesai!</span>
                <h3 class="text-xl font-black text-gray-800 dark:text-white mt-1" x-text="craftedProduct ? craftedProduct.name : ''"></h3>
                <p class="text-xs text-gray-500 mt-1" x-text="craftedProduct ? craftedProduct.description : ''"></p>
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-2xl border border-gray-100 dark:border-gray-600">
                <div>
                    <span class="text-[10px] text-gray-400 block font-semibold">Reward Koin</span>
                    <span class="text-sm font-black text-amber-500 flex items-center justify-center gap-1">
                        💰 +<span x-text="craftedProduct ? craftedProduct.coin_reward : 0"></span>
                    </span>
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 block font-semibold">Eco-Impact</span>
                    <span class="text-sm font-black text-emerald-600 flex items-center justify-center gap-1">
                        🌱 +<span x-text="craftedProduct ? craftedProduct.eco_points : 0"></span> Poin
                    </span>
                </div>
            </div>

            <button 
                @click="craftedProduct = null"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-extrabold py-3 rounded-xl text-sm transition shadow-md">
                Lanjutkan Crafting Lainnya →
            </button>
        </div>
    </div>

    {{-- MODAL BUKU RESEP (RECIPE GUIDE MODAL) --}}
    <div x-show="showRecipeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showRecipeModal = false" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                    <span>📖 Buku Panduan Resep Crafting</span>
                </h3>
                <button @click="showRecipeModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <div class="space-y-3">
                <template x-for="r in recipes" :key="r.id">
                    <div class="p-3.5 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white dark:bg-gray-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm border border-gray-100"
                                x-text="r.output_icon"></div>
                            <div>
                                <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="r.name"></h4>
                                <div class="flex items-center gap-1.5 mt-1 text-[11px] text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-green-700">Bahan:</span>
                                    <template x-for="(ing, idx) in r.ingredients" :key="ing.id">
                                        <span x-text="`${ing.icon} ${ing.name}${idx < r.ingredients.length - 1 ? ' + ' : ''}`"></span>
                                    </template>
                                </div>
                                <span class="text-[10px] text-gray-500 block mt-0.5" x-text="`Alat: ${r.tool ? r.tool.name : '-'}`"></span>
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
                class="w-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 font-bold py-2.5 rounded-xl text-xs transition">
                Tutup Panduan
            </button>
        </div>
    </div>

</div>
@endsection
