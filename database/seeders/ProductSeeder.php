<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Disable foreign key checks to safely delete products
        Schema::disableForeignKeyConstraints();
        
        // Clear existing products using delete instead of truncate
        Product::query()->delete();
        
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
        
        // Seed truck loads
        $truckLoads = [
            [
                'name' => 'Riversand',
                'category' => 'load',
                'price' => 240.00,
                'description' => 'Includes delivery'
            ],
            [
                'name' => 'Pitsand',
                'category' => 'load',
                'price' => 220.00,
                'description' => 'Includes delivery'
            ],
            [
                'name' => 'Quarry',
                'category' => 'load',
                'price' => 460.00,
                'description' => 'Includes delivery'
            ],
            [
                'name' => 'Gravel',
                'category' => 'load',
                'price' => 120.00,
                'description' => 'Includes delivery'
            ],
        ];
        
        foreach ($truckLoads as $load) {
            Product::create($load);
        }
        
        // Seed building materials
        
        // Basic blocks and bricks
        $blocksAndBricks = [
            [
                'name' => 'Face Blocks',
                'category' => 'building_material',
                'type' => 'block',
                'price' => 0.68,
            ],
            [
                'name' => 'Standard Brick',
                'category' => 'building_material',
                'type' => 'brick',
                'price' => 0.17,
            ],
        ];
        
        foreach ($blocksAndBricks as $item) {
            Product::create($item);
        }
        
        // Pavers with variants
        $paverTypes = [
            'Holland' => [
                'plain' => 0.17,
                'red' => 0.20,
                'black' => 0.20,
            ],
            'Interlocking' => [
                'plain' => 0.17,
                'red' => 0.20,
                'black' => 0.20,
            ],
            'Oriental' => [
                'plain' => 0.21,
                'red' => 0.28,
                'black' => 0.28,
            ],
            'Diplomate' => [
                'plain' => 0.33,
                'red' => 0.40,
                'black' => 0.40,
            ],
            'Arrow' => [
                'plain' => 0.45,
                'red' => 0.52,
                'black' => 0.52,
            ],
            'Hexagon' => [
                'plain' => 0.20,
                'red' => 0.27,
                'black' => 0.27,
            ],
            'Clover' => [
                'plain' => 0.15,
                'red' => 0.22,
                'black' => 0.22,
            ],
            'Diamond' => [
                'plain' => 0.20,
                'red' => 0.27,
                'black' => 0.27,
            ],
        ];
        
        foreach ($paverTypes as $type => $variants) {
            foreach ($variants as $variant => $price) {
                Product::create([
                    'name' => $type . ' Paver - ' . ucfirst($variant),
                    'category' => 'building_material',
                    'type' => 'paver',
                    'variant' => $variant,
                    'price' => $price,
                ]);
            }
        }
    }
}