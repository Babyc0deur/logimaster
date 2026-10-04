<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get all existing stores and seed them
        $allStores = Store::all();
        
        if ($allStores->isEmpty()) {
            $this->command->error('No stores found! Please create stores first.');
            return;
        }

        foreach ($allStores as $store) {
            // Determine how many categories and products based on store
            $categoriesCount = 6; // Default
            $productsCount = 15;  // Default
            
            // Vary the counts for different stores
            if (str_contains(strtolower($store->name), 'principal') || str_contains(strtolower($store->name), 'mon magasin')) {
                $categoriesCount = 8;
                $productsCount = 20;
            } elseif (str_contains(strtolower($store->name), 'paris')) {
                $categoriesCount = 5;
                $productsCount = 15;
            } elseif (str_contains(strtolower($store->name), 'lyon')) {
                $categoriesCount = 6;
                $productsCount = 12;
            }
            
            // Skip if store already has products
            if ($store->products()->count() > 0) {
                $this->command->info("Skipping {$store->name} - already has data");
                continue;
            }

            $this->command->info("Seeding store: {$store->name}");

            // Create Categories
            $categories = [
                ['name' => 'Électronique', 'slug' => 'electronique'],
                ['name' => 'Vêtements', 'slug' => 'vetements'],
                ['name' => 'Alimentation', 'slug' => 'alimentation'],
                ['name' => 'Maison & Jardin', 'slug' => 'maison-jardin'],
                ['name' => 'Sports & Loisirs', 'slug' => 'sports-loisirs'],
                ['name' => 'Livres', 'slug' => 'livres'],
                ['name' => 'Jouets', 'slug' => 'jouets'],
                ['name' => 'Beauté & Santé', 'slug' => 'beaute-sante'],
            ];

            // Take a subset of categories for variety
            $storeCategories = array_slice($categories, 0, $categoriesCount);
            $createdCategories = [];

            foreach ($storeCategories as $categoryData) {
                $createdCategories[] = Category::firstOrCreate([
                    'slug' => $categoryData['slug'] . '-' . $store->id, // Unique slug per store
                    'store_id' => $store->id,
                ], [
                    'name' => $categoryData['name'],
                ]);
            }

            // Create Suppliers
            $suppliers = [
                ['name' => 'TechWorld SA', 'contact_person' => 'Jean Dupont', 'email' => 'contact@techworld.fr', 'phone' => '01 23 45 67 89'],
                ['name' => 'Fashion Plus', 'contact_person' => 'Marie Martin', 'email' => 'info@fashionplus.fr', 'phone' => '01 34 56 78 90'],
                ['name' => 'Bio Aliments', 'contact_person' => 'Pierre Bernard', 'email' => 'commande@bioaliments.fr', 'phone' => '01 45 67 89 01'],
                ['name' => 'Maison Confort', 'contact_person' => 'Sophie Dubois', 'email' => 'vente@maisonconfort.fr', 'phone' => '01 56 78 90 12'],
                ['name' => 'Sport Équipement', 'contact_person' => 'Luc Thomas', 'email' => 'pro@sportequipement.fr', 'phone' => '01 67 89 01 23'],
            ];

            $createdSuppliers = [];
            foreach ($suppliers as $supplierData) {
                $createdSuppliers[] = Supplier::firstOrCreate([
                    'email' => $supplierData['email'],
                    'store_id' => $store->id,
                ], [
                    'name' => $supplierData['name'],
                    'contact_person' => $supplierData['contact_person'],
                    'phone' => $supplierData['phone'],
                ]);
            }

            // Create Products
            $allProducts = [
                // Électronique
                ['name' => 'Smartphone Galaxy X10', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'ELEC-001', 'price' => 699.99, 'stock' => 25, 'min_stock' => 5],
                ['name' => 'Ordinateur Portable Pro', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'ELEC-002', 'price' => 1299.99, 'stock' => 15, 'min_stock' => 3],
                ['name' => 'Écouteurs Sans Fil', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'ELEC-003', 'price' => 89.99, 'stock' => 50, 'min_stock' => 10],
                ['name' => 'Tablette 10 pouces', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'ELEC-004', 'price' => 349.99, 'stock' => 20, 'min_stock' => 5],
                
                // Vêtements
                ['name' => 'T-Shirt Coton Bio', 'category_idx' => 1, 'supplier_idx' => 1, 'sku' => 'VET-001', 'price' => 24.99, 'stock' => 100, 'min_stock' => 20],
                ['name' => 'Jean Slim Fit', 'category_idx' => 1, 'supplier_idx' => 1, 'sku' => 'VET-002', 'price' => 59.99, 'stock' => 60, 'min_stock' => 15],
                ['name' => 'Veste en Cuir', 'category_idx' => 1, 'supplier_idx' => 1, 'sku' => 'VET-003', 'price' => 199.99, 'stock' => 12, 'min_stock' => 3],
                ['name' => 'Chaussures Sport', 'category_idx' => 1, 'supplier_idx' => 1, 'sku' => 'VET-004', 'price' => 79.99, 'stock' => 40, 'min_stock' => 10],
                
                // Alimentation
                ['name' => 'Café Bio 1kg', 'category_idx' => 2, 'supplier_idx' => 2, 'sku' => 'ALIM-001', 'price' => 15.99, 'stock' => 80, 'min_stock' => 20],
                ['name' => 'Miel Artisanal 500g', 'category_idx' => 2, 'supplier_idx' => 2, 'sku' => 'ALIM-002', 'price' => 12.99, 'stock' => 45, 'min_stock' => 10],
                ['name' => 'Huile d\'Olive Extra Vierge', 'category_idx' => 2, 'supplier_idx' => 2, 'sku' => 'ALIM-003', 'price' => 18.99, 'stock' => 35, 'min_stock' => 8],
                ['name' => 'Pâtes Complètes Bio', 'category_idx' => 2, 'supplier_idx' => 2, 'sku' => 'ALIM-004', 'price' => 3.99, 'stock' => 120, 'min_stock' => 30],
                
                // Maison & Jardin
                ['name' => 'Aspirateur Robot', 'category_idx' => 3, 'supplier_idx' => 3, 'sku' => 'MAIS-001', 'price' => 299.99, 'stock' => 18, 'min_stock' => 4],
                ['name' => 'Lampe LED Design', 'category_idx' => 3, 'supplier_idx' => 3, 'sku' => 'MAIS-002', 'price' => 49.99, 'stock' => 30, 'min_stock' => 8],
                ['name' => 'Coussin Décoratif', 'category_idx' => 3, 'supplier_idx' => 3, 'sku' => 'MAIS-003', 'price' => 19.99, 'stock' => 55, 'min_stock' => 15],
                ['name' => 'Plante d\'Intérieur', 'category_idx' => 3, 'supplier_idx' => 3, 'sku' => 'MAIS-004', 'price' => 29.99, 'stock' => 25, 'min_stock' => 5],
                
                // Sports & Loisirs
                ['name' => 'Tapis de Yoga', 'category_idx' => 4, 'supplier_idx' => 4, 'sku' => 'SPORT-001', 'price' => 34.99, 'stock' => 40, 'min_stock' => 10],
                ['name' => 'Haltères 5kg (paire)', 'category_idx' => 4, 'supplier_idx' => 4, 'sku' => 'SPORT-002', 'price' => 44.99, 'stock' => 28, 'min_stock' => 6],
                ['name' => 'Ballon de Football', 'category_idx' => 4, 'supplier_idx' => 4, 'sku' => 'SPORT-003', 'price' => 24.99, 'stock' => 35, 'min_stock' => 8],
                ['name' => 'Raquette de Tennis', 'category_idx' => 4, 'supplier_idx' => 4, 'sku' => 'SPORT-004', 'price' => 89.99, 'stock' => 15, 'min_stock' => 3],
            ];

            // Shuffle and slice products for variety per store
            $storeProducts = $allProducts;
            if ($store->slug !== 'mon-magasin') {
                shuffle($storeProducts);
                $storeProducts = array_slice($storeProducts, 0, $productsCount);
            }

            foreach ($storeProducts as $productData) {
                // Ensure category index exists in created categories
                $catIndex = $productData['category_idx'];
                if (!isset($createdCategories[$catIndex])) {
                    $catIndex = 0; // Fallback
                }

                // Ensure supplier index exists
                $supIndex = $productData['supplier_idx'];
                if (!isset($createdSuppliers[$supIndex])) {
                    $supIndex = 0; // Fallback
                }

                Product::firstOrCreate([
                    'sku' => $productData['sku'] . '-' . $store->id, // Unique SKU per store
                    'store_id' => $store->id,
                ], [
                    'name' => $productData['name'],
                    'category_id' => $createdCategories[$catIndex]->id,
                    'supplier_id' => $createdSuppliers[$supIndex]->id,
                    'description' => 'Description détaillée du produit ' . $productData['name'],
                    'price' => $productData['price'],
                    'stock_quantity' => rand(5, 100), // Random stock
                    'minimum_stock_level' => $productData['min_stock'],
                ]);
            }
        }

        $this->command->info('✅ Données de démonstration créées pour tous les magasins!');
    }
}
