<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class UpdateVenusProductImagesSeeder extends Seeder
{
    public function run(): void
    {
        $imageMapping = [
            'VEN-RF-001' => 'storage/products/robe_elegante_noire.png',
            'VEN-RF-002' => 'storage/products/chemisier_soie_blanc.png',
            'VEN-ACC-001' => 'storage/products/sac_cuir_camel.png',
            'VEN-CHAU-001' => 'storage/products/escarpins_noir.png',
            'VEN-RH-001' => 'storage/products/costume_marine.png',
            'VEN-PAR-001' => 'storage/products/parfum_elegance.png',
        ];

        foreach ($imageMapping as $sku => $imagePath) {
            $product = Product::where('sku', $sku)->first();
            if ($product) {
                $product->update(['image' => $imagePath]);
                $this->command->info("Updated image for: {$product->name}");
            }
        }

        $this->command->info('✅ Product images updated successfully!');
    }
}
