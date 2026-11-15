<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\Tool;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $scissors = Tool::where('slug', 'gunting-serbaguna')->first();
        $glueGun  = Tool::where('slug', 'lem-tembak-panas')->first();
        $brush    = Tool::where('slug', 'kuas-cat-warna')->first();
        $cutter   = Tool::where('slug', 'pisau-cutter-presisi')->first();

        $botolPet = Material::where('slug', 'botol-plastik-pet')->first();
        $kardus   = Material::where('slug', 'kardus-karton-bekas')->first();
        $kaleng   = Material::where('slug', 'kaleng-minuman-aluminium')->first();
        $kain     = Material::where('slug', 'kain-perca-denim-katun')->first();
        $kaca     = Material::where('slug', 'botol-kaca-bening')->first();
        $tutup    = Material::where('slug', 'tutup-botol-hdpe')->first();

        $recipes = [
            [
                'name'        => 'Pot Gantung Tanaman Hias',
                'slug'        => 'pot-gantung-tanaman-hias',
                'tool_id'     => $scissors?->id,
                'output_icon' => '🪴',
                'description' => 'Pot tanaman mandiri berbahan botol plastik bekas dengan hiasan tali kain.',
                'eco_points'  => 35,
                'coin_reward' => 60,
                'ingredients' => [
                    $botolPet?->id => 1,
                    $kain?->id     => 1,
                ],
            ],
            [
                'name'        => 'Wadah Pensil Karakter Meja',
                'slug'        => 'wadah-pensil-karakter-meja',
                'tool_id'     => $glueGun?->id,
                'output_icon' => '✏️',
                'description' => 'Organizer meja belajar estetik berbahan karton tebal.',
                'eco_points'  => 25,
                'coin_reward' => 45,
                'ingredients' => [
                    $kardus?->id => 1,
                ],
            ],
            [
                'name'        => 'Lampu Tidur Kaca Estetik',
                'slug'        => 'lampu-tidur-kaca-estetik',
                'tool_id'     => $brush?->id,
                'output_icon' => '💡',
                'description' => 'Botol kaca bekas dilukis motif daun dengan pencahayaan hangat.',
                'eco_points'  => 40,
                'coin_reward' => 75,
                'ingredients' => [
                    $kaca?->id => 1,
                ],
            ],
            [
                'name'        => 'Tatakan Cangkir Daur Ulang',
                'slug'        => 'tatakan-cangkir-daur-ulang',
                'tool_id'     => $glueGun?->id,
                'output_icon' => '☕',
                'description' => 'Tatakan gelas tahan panas dari lelehan tutup botol HDPE warna-warni.',
                'eco_points'  => 20,
                'coin_reward' => 35,
                'ingredients' => [
                    $tutup?->id => 1,
                ],
            ],
            [
                'name'        => 'Tempat Lilin Kaleng Retro',
                'slug'        => 'tempat-lilin-kaleng-retro',
                'tool_id'     => $cutter?->id,
                'output_icon' => '🪔',
                'description' => 'Kaleng minuman aluminium dengan ukiran lubang motif cahaya lilin.',
                'eco_points'  => 30,
                'coin_reward' => 50,
                'ingredients' => [
                    $kaleng?->id => 1,
                ],
            ],
        ];

        foreach ($recipes as $r) {
            $ingredients = $r['ingredients'];
            unset($r['ingredients']);

            $recipe = Recipe::updateOrCreate(['slug' => $r['slug']], $r);

            $syncData = [];
            foreach ($ingredients as $matId => $qty) {
                if ($matId) {
                    $syncData[$matId] = ['quantity' => $qty];
                }
            }
            $recipe->ingredients()->sync($syncData);
        }
    }
}

