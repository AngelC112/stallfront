<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\SlugGenerator;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Frutta',
                'description' => 'Frutta fresca di stagione',
                'always_available' => false,
                'season_start_month' => 1,
                'season_start_day' => 1,
                'season_end_month' => 12,
                'season_end_day' => 31,
                'position' => 1,
            ],
            [
                'name' => 'Verdura',
                'description' => 'Verdura fresca',
                'always_available' => true,
                'position' => 2,
            ],
            [
                'name' => 'Prodotti da forno',
                'description' => 'Pane e prodotti da forno',
                'always_available' => true,
                'position' => 3,
            ],
        ];

        foreach ($categories as $category) {
            $slug = SlugGenerator::unique(Category::class, $category['name']);

            Category::updateOrCreate(['slug' => $slug],
                array_merge($category, [
                    'slug' => $slug,
                    'is_active' => true,
                ]));
        }
    }
}
