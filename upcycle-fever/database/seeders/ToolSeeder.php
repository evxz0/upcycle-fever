<?php

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;

class ToolSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            [
                'name'        => 'Gunting Serbaguna',
                'slug'        => 'gunting-serbaguna',
                'icon'        => '✂️',
                'action_type' => 'potong',
                'description' => 'Memotong botol plastik, kain perca, dan karton dengan presisi.',
            ],
            [
                'name'        => 'Lem Tembak Panas',
                'slug'        => 'lem-tembak-panas',
                'icon'        => '🔫',
                'action_type' => 'rekat',
                'description' => 'Merekatkan tutup botol, kardus, dan bahan daur ulang agar kokoh.',
            ],
            [
                'name'        => 'Kuas & Cat Warna',
                'slug'        => 'kuas-cat-warna',
                'icon'        => '🖌️',
                'action_type' => 'lukis',
                'description' => 'Memberi warna dan motif indah pada kaca, kaleng, dan botol.',
            ],
            [
                'name'        => 'Pisau Cutter Presisi',
                'slug'        => 'pisau-cutter-presisi',
                'icon'        => '🔪',
                'action_type' => 'ukir',
                'description' => 'Melubangi kaleng aluminium dan memotong pola karton tebal.',
            ],
        ];

        foreach ($tools as $t) {
            Tool::updateOrCreate(['slug' => $t['slug']], $t);
        }
    }
}

