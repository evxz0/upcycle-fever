@extends('layouts.app')
@section('title', 'Katalog Bahan Limbah')

@section('content')
<div x-data="{
    search: '{{ $search ?? '' }}',
    selectedCategory: '{{ $category ?? 'Semua' }}',
    materials: @js($materials),
    showCreateModal: false,
    newMaterial: {
        name: '',
        category: 'Plastik',
        type: 'Anorganik',
        description: '',
        icon: '🧴',
        eco_impact: 15
    },
    isSubmitting: false,
    message: '',

    get filteredMaterials() {
        return this.materials.filter(m => {
            const matchCategory = this.selectedCategory === 'Semua' || m.category === this.selectedCategory;
            const matchSearch = !this.search || 
                m.name.toLowerCase().includes(this.search.toLowerCase()) || 
                (m.description && m.description.toLowerCase().includes(this.search.toLowerCase())) ||
                m.category.toLowerCase().includes(this.search.toLowerCase());
            return matchCategory && matchSearch;
        });
    },

    async submitMaterial() {
        this.isSubmitting = true;
        try {
            const res = await fetch('{{ route('api.materials.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify(this.newMaterial)
            });
            const data = await res.json();
            if (res.ok && data.success) {
                this.materials.unshift(data.data);
                this.showCreateModal = false;
                this.message = 'Bahan limbah baru berhasil didaftarkan!';
                this.newMaterial = { name: '', category: 'Plastik', type: 'Anorganik', description: '', icon: '🧴', eco_impact: 15 };
                setTimeout(() => this.message = '', 4000);
            } else {
                alert(data.message || 'Gagal menyimpan bahan.');
            }
        } catch (e) {
            alert('Terjadi kesalahan jaringan.');
        } finally {
            this.isSubmitting = false;
        }
    }
}" class="space-y-6">

    {{-- Banner Header --}}
    <div class="bg-gradient-to-r from-green-800 to-teal-700 text-white rounded-2xl p-6 sm:p-8 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 bg-green-900/60 text-green-200 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-3">
                <span>🌱 SDG 12: Konsumsi & Produksi Bertanggung Jawab</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Katalog Master Data Bahan Limbah</h1>
            <p class="text-green-100 mt-2 max-w-2xl text-sm leading-relaxed">
                Eksplorasi ragam bahan sampah daur ulang, fakta dekomposisi lingkungan, serta nilai Eco-Impact untuk dijadikan produk bernilai tinggi di Meja Kerja.
            </p>
        </div>
        <div class="flex flex-wrap gap-3 shrink-0">
            <button @click="showCreateModal = true" class="bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold px-4 py-2.5 rounded-xl text-sm shadow transition flex items-center gap-2">
                <span>➕</span> Tambah Bahan
            </button>
            <a href="{{ route('game.workbench') }}" class="bg-white/20 hover:bg-white/30 text-white font-bold px-4 py-2.5 rounded-xl text-sm transition flex items-center gap-2 border border-white/20">
                <span>🛠️</span> Buka Meja Kerja
            </a>
        </div>
    </div>

    {{-- Notifikasi Sukses --}}
    <div x-show="message" x-transition class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
        <span>✅</span> <span x-text="message"></span>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row items-center justify-between gap-4">
        {{-- Kategori Pills --}}
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            @foreach(['Semua', 'Plastik', 'Kertas/Karton', 'Logam', 'Tekstil', 'Kaca'] as $cat)
                <button 
                    @click="selectedCategory = '{{ $cat }}'"
                    :class="selectedCategory === '{{ $cat }}' 
                        ? 'bg-green-600 text-white font-semibold shadow-sm' 
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                    class="px-3.5 py-1.5 rounded-xl text-xs sm:text-sm transition">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        {{-- Search Input --}}
        <div class="relative w-full sm:w-72">
            <input 
                type="text" 
                x-model="search"
                placeholder="Cari bahan sampah..."
                class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 text-sm pl-10 focus:outline-none focus:ring-2 focus:ring-green-500 text-gray-800 dark:text-white"
            >
            <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
            <button 
                x-show="search" 
                @click="search = ''" 
                class="absolute right-3 top-2 text-gray-400 hover:text-gray-600 text-xs">
                ✕
            </button>
        </div>
    </div>

    {{-- Grid Bahan Limbah --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <template x-for="item in filteredMaterials" :key="item.id">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-green-50 dark:bg-gray-700 rounded-xl flex items-center justify-center text-2xl border border-green-100 dark:border-gray-600" x-text="item.icon || '📦'"></div>
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white text-base leading-tight" x-text="item.name"></h3>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full mt-1 inline-block"
                                    :class="{
                                        'bg-blue-100 text-blue-800': item.category === 'Plastik',
                                        'bg-amber-100 text-amber-800': item.category === 'Kertas/Karton',
                                        'bg-purple-100 text-purple-800': item.category === 'Logam',
                                        'bg-pink-100 text-pink-800': item.category === 'Tekstil',
                                        'bg-emerald-100 text-emerald-800': item.category === 'Kaca'
                                    }"
                                    x-text="item.category">
                                </span>
                            </div>
                        </div>
                        <span class="bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-300 font-extrabold text-xs px-2.5 py-1 rounded-lg">
                            +<span x-text="item.eco_impact"></span> Eco
                        </span>
                    </div>

                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed mb-4 line-clamp-3" x-text="item.description || 'Tidak ada deskripsi.'"></p>
                </div>

                <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500">
                    <span class="flex items-center gap-1 font-medium">
                        🏷️ Tipe: <span class="text-gray-700 dark:text-gray-300" x-text="item.type"></span>
                    </span>
                    <a href="{{ route('game.workbench') }}" class="text-green-600 dark:text-green-400 font-bold hover:underline flex items-center gap-1">
                        Bawa ke Meja Kerja →
                    </a>
                </div>
            </div>
        </template>
    </div>

    {{-- Fallback jika hasil kosong --}}
    <div x-show="filteredMaterials.length === 0" class="text-center py-12 bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-300 p-8">
        <div class="text-4xl mb-3">🔍</div>
        <h3 class="font-bold text-gray-700 dark:text-gray-200">Bahan tidak ditemukan</h3>
        <p class="text-sm text-gray-500 mt-1">Coba kata kunci lain atau pilih kategori 'Semua'.</p>
    </div>

    {{-- Modal Tambah Bahan Limbah (Technical Task: Pengelolaan Data Sampah) --}}
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.away="showCreateModal = false" class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Tambah Data Sampah Baru</h3>
                <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <form @submit.prevent="submitMaterial" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nama Bahan Sampah</label>
                    <input type="text" x-model="newMaterial.name" required placeholder="Contoh: Sedotan Plastik Bekas"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kategori</label>
                        <select x-model="newMaterial.category" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                            <option value="Plastik">Plastik</option>
                            <option value="Kertas/Karton">Kertas/Karton</option>
                            <option value="Logam">Logam</option>
                            <option value="Tekstil">Tekstil</option>
                            <option value="Kaca">Kaca</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Ikon (Emoji)</label>
                        <input type="text" x-model="newMaterial.icon" placeholder="🧴" maxlength="5"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tipe Sampah</label>
                        <input type="text" x-model="newMaterial.type" placeholder="Anorganik"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Poin Eco-Impact</label>
                        <input type="number" x-model.number="newMaterial.eco_impact" min="1" max="100" required
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Deskripsi Edukasi SDG 12</label>
                    <textarea x-model="newMaterial.description" rows="3" placeholder="Informasi dampak lingkungan dan potensi upcycle..."
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400">Batal</button>
                    <button type="submit" :disabled="isSubmitting" class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2 rounded-lg text-sm transition">
                        <span x-show="!isSubmitting">Simpan Bahan</span>
                        <span x-show="isSubmitting">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

