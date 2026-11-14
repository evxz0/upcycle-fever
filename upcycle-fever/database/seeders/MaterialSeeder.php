<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\User;
use App\Models\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            [
                'name'        => 'Botol Plastik PET',
                'slug'        => 'botol-plastik-pet',
                'category'    => 'Plastik',
                'type'        => 'Anorganik',
                'description' => 'Limbah botol minuman sekali pakai. Butuh 450 tahun terurai di alam; cocok di-upcycle menjadi pot gantung self-watering atau wadah serbaguna.',
                'icon'        => '🧴',
                'eco_impact'  => 20,
            ],
            [
                'name'        => 'Kardus Karton Bekas',
                'slug'        => 'kardus-karton-bekas',
                'category'    => 'Kertas/Karton',
                'type'        => 'Daur Ulang',
                'description' => 'Kotak kemasan paket e-commerce. Mengolah kembali 1 ton karton menyelamatkan 17 pohon dan menghemat 4.000 kWh energi listrik.',
                'icon'        => '📦',
                'eco_impact'  => 15,
            ],
            [
                'name'        => 'Kaleng Minuman Aluminium',
                'slug'        => 'kaleng-minuman-aluminium',
                'category'    => 'Logam',
                'type'        => 'Anorganik',
                'description' => 'Kaleng minuman bersoda berbahan aluminium. Daur ulang kaleng menghemat 95% energi dibanding membuat aluminium dari tambang bauksit.',
                'icon'        => '🥫',
                'eco_impact'  => 25,
            ],
            [
                'name'        => 'Kain Perca Denim & Katun',
                'slug'        => 'kain-perca-denim-katun',
                'category'    => 'Tekstil',
                'type'        => 'Tekstil',
                'description' => 'Potongan sisa kain konveksi baju/celana. Mengurangi timbulan limbah fashion yang menyumbang 10% jejak karbon dunia.',
                'icon'        => '🧵',
                'eco_impact'  => 20,
            ],
            [
                'name'        => 'Botol Kaca Bening',
                'slug'        => 'botol-kaca-bening',
                'category'    => 'Kaca',
                'type'        => 'Anorganik',
                'description' => 'Botol kaca sirup/selai. Material kaca dapat didaur ulang tanpa batas waktu tanpa penurunan kualitas; ideal untuk lampu hias atau vas.',
                'icon'        => '🍾',
                'eco_impact'  => 25,
            ],
            [
                'name'        => 'Tutup Botol HDPE',
                'slug'        => 'tutup-botol-hdpe',
                'category'    => 'Plastik',
                'type'        => 'Anorganik',
                'description' => 'Tutup botol dari plastik nomor 2 (HDPE). Tahan panas dan sangat mudah dilebur menjadi tatakan cangkir atau aksesoris meja.',
                'icon'        => '⭕',
                'eco_impact'  => 10,
            ],
            [
                'name'        => 'Koran & Majalah Bekas',
                'slug'        => 'koran-majalah-bekas',
                'category'    => 'Kertas/Karton',
                'type'        => 'Daur Ulang',
                'description' => 'Lembaran koran dan kertas cetak tak terpakai. Dapat dipilin atau dibuat bubur kertas (paper mache) menjadi wadah estetik.',
                'icon'        => '📰',
                'eco_impact'  => 15,
            ],
            [
                'name'        => 'Kantung Plastik Kresek',
                'slug'        => 'kantung-plastik-kresek',
                'category'    => 'Plastik',
                'type'        => 'Anorganik',
                'description' => 'Plastik pembungkus belanjaan tipis. Sering mencemari biota laut; dapat dipadatkan (fused plastic) menjadi dompet tahan air.',
                'icon'        => '🛍️',
                'eco_impact'  => 15,
            ],
        ];

        foreach ($materials as $data) {
            Material::updateOrCreate(['slug' => $data['slug']], $data);
        }

        // Berikan sample inventaris awal untuk semua player yang ada
        $allMaterials = Material::all();
        $players = User::where('role', 'player')->get();

        foreach ($players as $player) {
            foreach ($allMaterials->take(5) as $idx => $material) {
                Inventory::updateOrCreate(
                    [
                        'user_id'     => $player->id,
                        'material_id' => $material->id,
                    ],
                    [
                        'quantity'    => 3 + $idx,
                    ]
                );
            }
        }
    }
}

