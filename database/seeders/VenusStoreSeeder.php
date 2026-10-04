<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\User;

class VenusStoreSeeder extends Seeder
{
    public function run(): void
    {
        // Create Venus Store
        $venus = Store::firstOrCreate([
            'slug' => 'venus'
        ], [
            'name' => 'Venus',
            'address' => '123 Avenue des Champs-Élysées, 75008 Paris',
            'phone' => '01 42 56 78 90',
            'email' => 'contact@venus-store.fr',
        ]);

        $this->command->info("Store Venus created: {$venus->name}");

        // Attach users to Venus store
        $owner = User::where('email', 'owner@example.com')->first();
        $manager = User::where('email', 'manager@example.com')->first();
        $employee = User::where('email', 'employee@example.com')->first();
        
        if ($owner) $venus->users()->syncWithoutDetaching([$owner->id]);
        if ($manager) $venus->users()->syncWithoutDetaching([$manager->id]);
        if ($employee) $venus->users()->syncWithoutDetaching([$employee->id]);

        // Create Categories
        $categories = [
            ['name' => 'Mode Femme', 'slug' => 'mode-femme-venus'],
            ['name' => 'Mode Homme', 'slug' => 'mode-homme-venus'],
            ['name' => 'Accessoires', 'slug' => 'accessoires-venus'],
            ['name' => 'Chaussures', 'slug' => 'chaussures-venus'],
            ['name' => 'Bijoux', 'slug' => 'bijoux-venus'],
            ['name' => 'Parfums', 'slug' => 'parfums-venus'],
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $createdCategories[] = Category::firstOrCreate([
                'slug' => $categoryData['slug'],
                'store_id' => $venus->id,
            ], [
                'name' => $categoryData['name'],
            ]);
        }

        // Create Suppliers
        $suppliers = [
            ['name' => 'Fashion Elite Paris', 'contact_person' => 'Isabelle Laurent', 'email' => 'contact@fashionelite.fr', 'phone' => '01 44 55 66 77'],
            ['name' => 'Luxe Accessories', 'contact_person' => 'Antoine Moreau', 'email' => 'info@luxeaccessories.fr', 'phone' => '01 55 66 77 88'],
            ['name' => 'Parfums de France', 'contact_person' => 'Céline Dubois', 'email' => 'ventes@parfumsdefrance.fr', 'phone' => '01 66 77 88 99'],
            ['name' => 'Bijouterie Moderne', 'contact_person' => 'Marc Petit', 'email' => 'commandes@bijouteriemoderne.fr', 'phone' => '01 77 88 99 00'],
        ];

        $createdSuppliers = [];
        foreach ($suppliers as $supplierData) {
            $createdSuppliers[] = Supplier::firstOrCreate([
                'email' => $supplierData['email'],
                'store_id' => $venus->id,
            ], [
                'name' => $supplierData['name'],
                'contact_person' => $supplierData['contact_person'],
                'phone' => $supplierData['phone'],
            ]);
        }

        // Create Products with detailed information
        $products = [
            // Mode Femme
            ['name' => 'Robe Élégante Noire', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'VEN-RF-001', 'price' => 129.99, 'cost_price' => 65.00, 'stock' => 35, 'min_stock' => 8, 'description' => 'Robe élégante en tissu fluide, parfaite pour les soirées'],
            ['name' => 'Chemisier en Soie Blanc', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'VEN-RF-002', 'price' => 89.99, 'cost_price' => 45.00, 'stock' => 50, 'min_stock' => 12, 'description' => 'Chemisier en soie naturelle, coupe classique'],
            ['name' => 'Pantalon Tailleur Gris', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'VEN-RF-003', 'price' => 79.99, 'cost_price' => 40.00, 'stock' => 45, 'min_stock' => 10, 'description' => 'Pantalon tailleur coupe droite, tissu stretch'],
            ['name' => 'Jupe Plissée Midi', 'category_idx' => 0, 'supplier_idx' => 0, 'sku' => 'VEN-RF-004', 'price' => 69.99, 'cost_price' => 35.00, 'stock' => 40, 'min_stock' => 10, 'description' => 'Jupe plissée longueur midi, style vintage'],
            
            // Mode Homme
            ['name' => 'Costume 3 Pièces Marine', 'category_idx' => 1, 'supplier_idx' => 0, 'sku' => 'VEN-RH-001', 'price' => 299.99, 'cost_price' => 150.00, 'stock' => 20, 'min_stock' => 5, 'description' => 'Costume complet 3 pièces, laine mélangée'],
            ['name' => 'Chemise Business Blanche', 'category_idx' => 1, 'supplier_idx' => 0, 'sku' => 'VEN-RH-002', 'price' => 59.99, 'cost_price' => 30.00, 'stock' => 60, 'min_stock' => 15, 'description' => 'Chemise business coton égyptien, col italien'],
            ['name' => 'Polo Casual Bleu', 'category_idx' => 1, 'supplier_idx' => 0, 'sku' => 'VEN-RH-003', 'price' => 49.99, 'cost_price' => 25.00, 'stock' => 55, 'min_stock' => 12, 'description' => 'Polo en coton piqué, coupe moderne'],
            
            // Accessoires
            ['name' => 'Sac à Main Cuir Camel', 'category_idx' => 2, 'supplier_idx' => 1, 'sku' => 'VEN-ACC-001', 'price' => 189.99, 'cost_price' => 95.00, 'stock' => 25, 'min_stock' => 6, 'description' => 'Sac à main en cuir véritable, plusieurs compartiments'],
            ['name' => 'Écharpe Cachemire Beige', 'category_idx' => 2, 'supplier_idx' => 1, 'sku' => 'VEN-ACC-002', 'price' => 79.99, 'cost_price' => 40.00, 'stock' => 40, 'min_stock' => 10, 'description' => 'Écharpe en pur cachemire, ultra douce'],
            ['name' => 'Ceinture Cuir Noir', 'category_idx' => 2, 'supplier_idx' => 1, 'sku' => 'VEN-ACC-003', 'price' => 45.99, 'cost_price' => 23.00, 'stock' => 50, 'min_stock' => 12, 'description' => 'Ceinture en cuir italien, boucle argentée'],
            ['name' => 'Lunettes de Soleil Aviateur', 'category_idx' => 2, 'supplier_idx' => 1, 'sku' => 'VEN-ACC-004', 'price' => 149.99, 'cost_price' => 75.00, 'stock' => 30, 'min_stock' => 8, 'description' => 'Lunettes style aviateur, verres polarisés'],
            
            // Chaussures
            ['name' => 'Escarpins Cuir Noir', 'category_idx' => 3, 'supplier_idx' => 0, 'sku' => 'VEN-CHAU-001', 'price' => 119.99, 'cost_price' => 60.00, 'stock' => 35, 'min_stock' => 8, 'description' => 'Escarpins classiques talon 7cm, cuir véritable'],
            ['name' => 'Baskets Blanches Premium', 'category_idx' => 3, 'supplier_idx' => 0, 'sku' => 'VEN-CHAU-002', 'price' => 139.99, 'cost_price' => 70.00, 'stock' => 45, 'min_stock' => 10, 'description' => 'Baskets en cuir blanc, semelle confort'],
            ['name' => 'Bottines Chelsea Marron', 'category_idx' => 3, 'supplier_idx' => 0, 'sku' => 'VEN-CHAU-003', 'price' => 159.99, 'cost_price' => 80.00, 'stock' => 28, 'min_stock' => 7, 'description' => 'Bottines Chelsea en daim, style britannique'],
            
            // Bijoux
            ['name' => 'Collier Argent Pendentif', 'category_idx' => 4, 'supplier_idx' => 3, 'sku' => 'VEN-BIJ-001', 'price' => 89.99, 'cost_price' => 45.00, 'stock' => 40, 'min_stock' => 10, 'description' => 'Collier en argent 925, pendentif cristal'],
            ['name' => 'Bracelet Or Rose', 'category_idx' => 4, 'supplier_idx' => 3, 'sku' => 'VEN-BIJ-002', 'price' => 129.99, 'cost_price' => 65.00, 'stock' => 30, 'min_stock' => 8, 'description' => 'Bracelet plaqué or rose, maille fine'],
            ['name' => 'Boucles d\'Oreilles Perles', 'category_idx' => 4, 'supplier_idx' => 3, 'sku' => 'VEN-BIJ-003', 'price' => 69.99, 'cost_price' => 35.00, 'stock' => 45, 'min_stock' => 12, 'description' => 'Boucles d\'oreilles perles de culture'],
            
            // Parfums
            ['name' => 'Parfum Femme Élégance 50ml', 'category_idx' => 5, 'supplier_idx' => 2, 'sku' => 'VEN-PAR-001', 'price' => 79.99, 'cost_price' => 40.00, 'stock' => 50, 'min_stock' => 12, 'description' => 'Eau de parfum florale, notes de jasmin'],
            ['name' => 'Parfum Homme Intense 75ml', 'category_idx' => 5, 'supplier_idx' => 2, 'sku' => 'VEN-PAR-002', 'price' => 89.99, 'cost_price' => 45.00, 'stock' => 45, 'min_stock' => 10, 'description' => 'Eau de toilette boisée, notes épicées'],
            ['name' => 'Coffret Parfum Luxe', 'category_idx' => 5, 'supplier_idx' => 2, 'sku' => 'VEN-PAR-003', 'price' => 149.99, 'cost_price' => 75.00, 'stock' => 25, 'min_stock' => 6, 'description' => 'Coffret cadeau avec parfum et lotion'],
        ];

        foreach ($products as $productData) {
            $catIndex = $productData['category_idx'];
            $supIndex = $productData['supplier_idx'];

            Product::firstOrCreate([
                'sku' => $productData['sku'],
                'store_id' => $venus->id,
            ], [
                'name' => $productData['name'],
                'category_id' => $createdCategories[$catIndex]->id,
                'supplier_id' => $createdSuppliers[$supIndex]->id,
                'description' => $productData['description'],
                'price' => $productData['price'],
                'cost_price' => $productData['cost_price'],
                'stock_quantity' => $productData['stock'],
                'minimum_stock_level' => $productData['min_stock'],
            ]);
        }

        $this->command->info('✅ Venus store seeded with ' . count($products) . ' products!');
    }
}
