@extends('layouts.app')
@section('title', 'Gudang & Pemilahan Sampah (Trash Sorting)')

@section('content')
<div x-data="warehouseSorting()" class="space-y-8 py-6 max-w-6xl mx-auto px-4">

    {{-- Header Gudang & Trash Sorting --}}
    <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-teal-700 text-white rounded-3xl p-6 sm:p-8 shadow-xl relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="inline-block text-xs font-black uppercase tracking-wider bg-white/20 text-emerald-100 px-3 py-1 rounded-full mb-2">
                    ♻️ Fitur F-03: Trash Sorting & Manajemen Inventaris
                </span>
                <h1 class="text-3xl font-black tracking-tight">Gudang Studio & Pemilahan Sampah</h1>
                <p class="text-sm text-emerald-100 mt-2 max-w-xl leading-relaxed">
                    Pilah dan kelompokkan bahan sampah mentah ke dalam kategori yang tepat (Plastik, Kardus/Kertas, Kaleng, dan Kain Perca) agar persediaan studio teratur dan siap dirakit!
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button @click="showSortingModal = true" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-black px-6 py-3 rounded-2xl text-sm transition shadow-lg flex items-center gap-2">
                    <span>📥</span> Sortir Pasokan Sampah Baru
                </button>
                <a href="{{ route('game.workbench') }}" class="bg-white/20 hover:bg-white/30 text-white font-bold px-5 py-3 rounded-2xl text-sm transition backdrop-blur-sm">
                    🛠️ Ke Meja Kerja
                </a>
            </div>
        </div>
    </div>

    {{-- Notification Toast --}}
    <div x-show="toastMessage" x-cloak x-transition class="p-4 rounded-2xl text-sm font-bold flex items-center justify-between shadow-lg"
        :class="{
            'bg-emerald-600 text-white': toastType === 'success',
            'bg-red-500 text-white': toastType === 'error',
            'bg-blue-600 text-white': toastType === 'info'
        }">
        <span x-text="toastMessage"></span>
        <button @click="toastMessage = ''" class="opacity-80 hover:opacity-100 text-lg ml-4">✕</button>
    </div>

    {{-- Filter Tab Kategori Sampah (Acceptance Criteria 1) --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
        <button
            @click="activeCategory = 'Semua'"
            :class="activeCategory === 'Semua' ? 'bg-green-700 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700'"
            class="px-5 py-2.5 rounded-2xl text-xs font-black transition shrink-0 flex items-center gap-1.5">
            <span>🌐</span> Semua Kategori
        </button>

        <button
            @click="activeCategory = 'Plastik'"
            :class="activeCategory === 'Plastik' ? 'bg-blue-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700'"
            class="px-5 py-2.5 rounded-2xl text-xs font-black transition shrink-0 flex items-center gap-1.5">
            <span>🧴</span> Sampah Plastik
        </button>

        <button
            @click="activeCategory = 'Kertas/Karton'"
            :class="activeCategory === 'Kertas/Karton' ? 'bg-amber-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700'"
            class="px-5 py-2.5 rounded-2xl text-xs font-black transition shrink-0 flex items-center gap-1.5">
            <span>📦</span> Kardus & Kertas
        </button>

        <button
            @click="activeCategory = 'Logam'"
            :class="activeCategory === 'Logam' ? 'bg-slate-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700'"
            class="px-5 py-2.5 rounded-2xl text-xs font-black transition shrink-0 flex items-center gap-1.5">
            <span>🥫</span> Kaleng & Logam
        </button>

        <button
            @click="activeCategory = 'Tekstil'"
            :class="activeCategory === 'Tekstil' ? 'bg-purple-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700'"
            class="px-5 py-2.5 rounded-2xl text-xs font-black transition shrink-0 flex items-center gap-1.5">
            <span>🧵</span> Kain Perca
        </button>
    </div>

    {{-- Kategori Terpisah Rak Gudang (Antarmuka Kategori Terpisah - AC 1) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        {{-- Kategori: Plastik --}}
        <div x-show="activeCategory === 'Semua' || activeCategory === 'Plastik'" class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border-2 border-blue-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-blue-50 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl p-2 bg-blue-50 dark:bg-blue-900/40 rounded-2xl">🧴</span>
                        <div>
                            <h3 class="font-black text-sm text-gray-800 dark:text-white">Sampah Plastik</h3>
                            <span class="text-[10px] text-gray-400">PET, HDPE, Sachet</span>
                        </div>
                    </div>
                    <span class="text-xs font-black text-blue-600 bg-blue-50 px-2.5 py-1 rounded-xl" x-text="categoryTotal('Plastik') + ' item'"></span>
                </div>

                <div class="space-y-3">
                    <template x-for="item in getCategoryItems('Plastik')" :key="item.id">
                        <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl" x-text="item.icon"></span>
                                <div>
                                    <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="item.name"></h4>
                                    <span class="text-[10px] text-emerald-600 font-bold" x-text="`🌱 +${item.eco_impact} Eco`"></span>
                                </div>
                            </div>
                            <span class="text-xs font-black px-2.5 py-1 rounded-lg"
                                :class="item.quantity > 0 ? 'bg-amber-100 text-amber-800 font-mono' : 'bg-gray-200 text-gray-400'"
                                x-text="`x${item.quantity}`"></span>
                        </div>
                    </template>
                </div>
            </div>
            <button @click="openSortModalForCategory('Plastik')" class="mt-4 w-full py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-bold text-xs rounded-xl transition">
                + Tambah / Sortir Plastik
            </button>
        </div>

        {{-- Kategori: Kardus & Kertas --}}
        <div x-show="activeCategory === 'Semua' || activeCategory === 'Kertas/Karton'" class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border-2 border-amber-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-amber-50 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl p-2 bg-amber-50 dark:bg-amber-900/40 rounded-2xl">📦</span>
                        <div>
                            <h3 class="font-black text-sm text-gray-800 dark:text-white">Kardus & Kertas</h3>
                            <span class="text-[10px] text-gray-400">Karton, Box, Koran</span>
                        </div>
                    </div>
                    <span class="text-xs font-black text-amber-600 bg-amber-50 px-2.5 py-1 rounded-xl" x-text="categoryTotal('Kertas/Karton') + ' item'"></span>
                </div>

                <div class="space-y-3">
                    <template x-for="item in getCategoryItems('Kertas/Karton')" :key="item.id">
                        <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl" x-text="item.icon"></span>
                                <div>
                                    <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="item.name"></h4>
                                    <span class="text-[10px] text-emerald-600 font-bold" x-text="`🌱 +${item.eco_impact} Eco`"></span>
                                </div>
                            </div>
                            <span class="text-xs font-black px-2.5 py-1 rounded-lg"
                                :class="item.quantity > 0 ? 'bg-amber-100 text-amber-800 font-mono' : 'bg-gray-200 text-gray-400'"
                                x-text="`x${item.quantity}`"></span>
                        </div>
                    </template>
                </div>
            </div>
            <button @click="openSortModalForCategory('Kertas/Karton')" class="mt-4 w-full py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 font-bold text-xs rounded-xl transition">
                + Tambah / Sortir Kardus
            </button>
        </div>

        {{-- Kategori: Kaleng & Logam --}}
        <div x-show="activeCategory === 'Semua' || activeCategory === 'Logam'" class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border-2 border-slate-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-50 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl p-2 bg-slate-50 dark:bg-slate-900/40 rounded-2xl">🥫</span>
                        <div>
                            <h3 class="font-black text-sm text-gray-800 dark:text-white">Kaleng & Logam</h3>
                            <span class="text-[10px] text-gray-400">Aluminium, Tutup Kaleng</span>
                        </div>
                    </div>
                    <span class="text-xs font-black text-slate-600 bg-slate-50 px-2.5 py-1 rounded-xl" x-text="categoryTotal('Logam') + ' item'"></span>
                </div>

                <div class="space-y-3">
                    <template x-for="item in getCategoryItems('Logam')" :key="item.id">
                        <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl" x-text="item.icon"></span>
                                <div>
                                    <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="item.name"></h4>
                                    <span class="text-[10px] text-emerald-600 font-bold" x-text="`🌱 +${item.eco_impact} Eco`"></span>
                                </div>
                            </div>
                            <span class="text-xs font-black px-2.5 py-1 rounded-lg"
                                :class="item.quantity > 0 ? 'bg-amber-100 text-amber-800 font-mono' : 'bg-gray-200 text-gray-400'"
                                x-text="`x${item.quantity}`"></span>
                        </div>
                    </template>
                </div>
            </div>
            <button @click="openSortModalForCategory('Logam')" class="mt-4 w-full py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-300 font-bold text-xs rounded-xl transition">
                + Tambah / Sortir Kaleng
            </button>
        </div>

        {{-- Kategori: Kain Perca (Tekstil) --}}
        <div x-show="activeCategory === 'Semua' || activeCategory === 'Tekstil'" class="bg-white dark:bg-gray-800 rounded-3xl p-5 shadow-sm border-2 border-purple-100 dark:border-gray-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-purple-50 dark:border-gray-700">
                    <div class="flex items-center gap-2.5">
                        <span class="text-2xl p-2 bg-purple-50 dark:bg-purple-900/40 rounded-2xl">🧵</span>
                        <div>
                            <h3 class="font-black text-sm text-gray-800 dark:text-white">Kain Perca</h3>
                            <span class="text-[10px] text-gray-400">Denim, Katun, Perca</span>
                        </div>
                    </div>
                    <span class="text-xs font-black text-purple-600 bg-purple-50 px-2.5 py-1 rounded-xl" x-text="categoryTotal('Tekstil') + ' item'"></span>
                </div>

                <div class="space-y-3">
                    <template x-for="item in getCategoryItems('Tekstil')" :key="item.id">
                        <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl" x-text="item.icon"></span>
                                <div>
                                    <h4 class="font-bold text-xs text-gray-800 dark:text-white" x-text="item.name"></h4>
                                    <span class="text-[10px] text-emerald-600 font-bold" x-text="`🌱 +${item.eco_impact} Eco`"></span>
                                </div>
                            </div>
                            <span class="text-xs font-black px-2.5 py-1 rounded-lg"
                                :class="item.quantity > 0 ? 'bg-amber-100 text-amber-800 font-mono' : 'bg-gray-200 text-gray-400'"
                                x-text="`x${item.quantity}`"></span>
                        </div>
                    </template>
                </div>
            </div>
            <button @click="openSortModalForCategory('Tekstil')" class="mt-4 w-full py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 font-bold text-xs rounded-xl transition">
                + Tambah / Sortir Kain Perca
            </button>
        </div>

    </div>

    {{-- MODAL SORTIR DAN TAMBAH PASOKAN SAMPAH (Acceptance Criteria 2) --}}
    <div x-show="showSortingModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showSortingModal = false" class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-5 border border-green-200">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-black text-gray-800 dark:text-white flex items-center gap-2">
                        <span>♻️ Pemilahan Sampah (Trash Sorting)</span>
                    </h3>
                    <p class="text-xs text-gray-500">Pilih bahan sampah mentah dan kelompokkan ke kategori yang tepat.</p>
                </div>
                <button @click="showSortingModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
            </div>

            <form @submit.prevent="submitSorting()" class="space-y-4">
                {{-- Pilih Bahan Sampah --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Pilih Pasokan Sampah Mentah:</label>
                    <select x-model="selectedMaterialId" required class="w-full border border-gray-300 dark:border-gray-600 rounded-2xl px-4 py-2.5 text-xs bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="">-- Pilih Sampah Mentah --</option>
                        <template x-for="m in allMaterials" :key="m.id">
                            <option :value="m.id" x-text="`${m.icon} ${m.name} (Katalog: ${m.category})`"></option>
                        </template>
                    </select>
                </div>

                {{-- Pilih Kategori Tujuan Pemilahan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Masukkan ke Tong Kategori:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="p-3 border rounded-2xl cursor-pointer flex items-center gap-2 text-xs font-bold transition"
                            :class="targetCategory === 'Plastik' ? 'border-blue-500 bg-blue-50 text-blue-700 ring-2 ring-blue-300' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" name="sortCat" value="Plastik" x-model="targetCategory" class="sr-only">
                            <span>🧴 Sampah Plastik</span>
                        </label>
                        <label class="p-3 border rounded-2xl cursor-pointer flex items-center gap-2 text-xs font-bold transition"
                            :class="targetCategory === 'Kertas/Karton' ? 'border-amber-500 bg-amber-50 text-amber-700 ring-2 ring-amber-300' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" name="sortCat" value="Kertas/Karton" x-model="targetCategory" class="sr-only">
                            <span>📦 Kardus & Kertas</span>
                        </label>
                        <label class="p-3 border rounded-2xl cursor-pointer flex items-center gap-2 text-xs font-bold transition"
                            :class="targetCategory === 'Logam' ? 'border-slate-500 bg-slate-50 text-slate-700 ring-2 ring-slate-300' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" name="sortCat" value="Logam" x-model="targetCategory" class="sr-only">
                            <span>🥫 Kaleng & Logam</span>
                        </label>
                        <label class="p-3 border rounded-2xl cursor-pointer flex items-center gap-2 text-xs font-bold transition"
                            :class="targetCategory === 'Tekstil' ? 'border-purple-500 bg-purple-50 text-purple-700 ring-2 ring-purple-300' : 'border-gray-200 dark:border-gray-700'">
                            <input type="radio" name="sortCat" value="Tekstil" x-model="targetCategory" class="sr-only">
                            <span>🧵 Kain Perca</span>
                        </label>
                    </div>
                </div>

                {{-- Jumlah Pasokan --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Jumlah Pasokan:</label>
                    <div class="flex items-center gap-3">
                        <input type="number" min="1" max="20" x-model.number="quantity" class="w-24 border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-xs bg-white dark:bg-gray-700 text-center font-bold">
                        <span class="text-xs text-gray-400">unit pasokan bahan</span>
                    </div>
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="submit" :disabled="isSubmitting" class="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-extrabold py-3 rounded-2xl text-xs transition shadow-md">
                        <span x-show="!isSubmitting">✓ Konfirmasi Pemilahan & Simpan</span>
                        <span x-show="isSubmitting">Memproses...</span>
                    </button>
                    <button type="button" @click="showSortingModal = false" class="px-5 py-3 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-2xl text-xs font-bold">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function warehouseSorting() {
    return {
        activeCategory: 'Semua',
        showSortingModal: false,
        selectedMaterialId: '',
        targetCategory: 'Plastik',
        quantity: 2,
        isSubmitting: false,
        toastMessage: '',
        toastType: 'info',
        allMaterials: @js($allMaterials),
        inventoryItems: @js($inventory).map(item => ({
            id: item.material_id || item.id,
            name: item.material ? item.material.name : item.name,
            category: item.material ? item.material.category : item.category,
            icon: item.material ? item.material.icon : item.icon,
            eco_impact: item.material ? item.material.eco_impact : 10,
            quantity: item.quantity !== undefined ? item.quantity : 0
        })),

        getCategoryItems(categoryName) {
            return this.inventoryItems.filter(item => {
                if (categoryName === 'Plastik') return item.category === 'Plastik';
                if (categoryName === 'Kertas/Karton') return item.category === 'Kertas/Karton' || item.category === 'Kardus';
                if (categoryName === 'Logam') return item.category === 'Logam' || item.category === 'Kaleng';
                if (categoryName === 'Tekstil') return item.category === 'Tekstil' || item.category === 'Kain Perca';
                return item.category === categoryName;
            });
        },

        categoryTotal(categoryName) {
            return this.getCategoryItems(categoryName).reduce((sum, item) => sum + (item.quantity || 0), 0);
        },

        openSortModalForCategory(category) {
            this.targetCategory = category;
            this.showSortingModal = true;
        },

        showNotification(msg, type = 'info') {
            this.toastMessage = msg;
            this.toastType = type;
            setTimeout(() => {
                if (this.toastMessage === msg) this.toastMessage = '';
            }, 4000);
        },

        async submitSorting() {
            if (!this.selectedMaterialId) {
                this.showNotification('Silakan pilih bahan sampah mentah!', 'error');
                return;
            }

            this.isSubmitting = true;
            try {
                const res = await fetch('{{ route('inventory.sort') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({
                        material_id: this.selectedMaterialId,
                        category: this.targetCategory,
                        quantity: this.quantity
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.showNotification(data.message, 'success');
                    
                    // Update tampilan lokal inventaris secara reaktif
                    const existing = this.inventoryItems.find(i => i.id === data.material.id);
                    if (existing) {
                        existing.quantity += data.quantity;
                    } else {
                        this.inventoryItems.push({
                            id: data.material.id,
                            name: data.material.name,
                            category: data.material.category,
                            icon: data.material.icon,
                            eco_impact: data.material.eco_impact,
                            quantity: data.quantity
                        });
                    }

                    this.showSortingModal = false;
                    this.selectedMaterialId = '';
                } else {
                    this.showNotification(data.message || 'Gagal menyortir bahan sampah!', 'error');
                }
            } catch (e) {
                this.showNotification('Terjadi kendala jaringan saat menyortir.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        }
    }
}
</script>
@endsection
