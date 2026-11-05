<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Category;
use App\Models\CategoryKeyword;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\ShoppingList;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Demo Alumno',
                'password' => Hash::make('password'),
            ]
        );

        $profesor = User::firstOrCreate(
            ['email' => 'oscar@ejemplo.com'],
            [
                'name' => 'Profesor Óscar',
                'password' => Hash::make('password'),
            ]
        );

        $templates = [
            ['name' => 'Ous', 'emoji' => '🥚', 'category_name' => 'Frescos'],
            ['name' => 'Llet', 'emoji' => '🥛', 'category_name' => 'Làctics'],
            ['name' => 'Pa', 'emoji' => '🥖', 'category_name' => 'Forn'],
            ['name' => 'Aigua', 'emoji' => '💧', 'category_name' => 'Begudes'],
            ['name' => 'Avellanes', 'emoji' => '🌰', 'category_name' => 'Fruits secs'],
            ['name' => 'Poma', 'emoji' => '🍎', 'category_name' => 'Fruita'],
            ['name' => 'Pollastre', 'emoji' => '🍗', 'category_name' => 'Carnisseria'],
            ['name' => 'Formatge', 'emoji' => '🧀', 'category_name' => 'Làctics'],
        ];

        foreach ($templates as $tpl) {
            ProductTemplate::firstOrCreate(['name' => $tpl['name']], $tpl);
        }

        $keywords = [
            'llet' => 'Làctics',
            'leche' => 'Làctics',
            'formatge' => 'Làctics',
            'queso' => 'Làctics',
            'pa' => 'Forn',
            'pan' => 'Forn',
            'poma' => 'Fruita',
            'manzana' => 'Fruita',
            'platano' => 'Fruita',
            'platan' => 'Fruita',
            'pollastre' => 'Carnisseria',
            'pollo' => 'Carnisseria',
            'ternera' => 'Carnisseria',
            'aigua' => 'Begudes',
            'agua' => 'Begudes',
            'refresc' => 'Begudes',
            'refresco' => 'Begudes',
            'avellanes' => 'Fruits secs',
            'nueces' => 'Fruits secs',
            'nous' => 'Fruits secs',
            'ous' => 'Frescos',
            'huevos' => 'Frescos',
        ];

        foreach ($keywords as $kw => $cat) {
            CategoryKeyword::firstOrCreate(['keyword' => $kw], ['category_name' => $cat]);
        }

        $list = ShoppingList::firstOrCreate(
            ['name' => 'Demo Evaluación', 'owner_id' => $user->id],
            ['emoji' => '🛒', 'color' => '#4f46e5']
        );

        $list->members()->syncWithoutDetaching([
            $user->id => ['role' => 'owner'],
            $profesor->id => ['role' => 'member'],
        ]);

        $tags = [
            'Mercadona' => '#10b981',
            'Bonpreu-Esclat' => '#f97316',
            'Urgente' => '#ef4444',
        ];

        $createdTags = [];
        foreach ($tags as $tagName => $color) {
            $createdTags[$tagName] = Tag::firstOrCreate([
                'list_id' => $list->id,
                'name' => $tagName,
            ], ['color' => $color]);
        }

        $categories = [
            ['name' => 'Làctics', 'emoji' => '🥛', 'position' => 1],
            ['name' => 'Forn', 'emoji' => '🥖', 'position' => 2],
            ['name' => 'Frescos', 'emoji' => '🥚', 'position' => 3],
            ['name' => 'Begudes', 'emoji' => '💧', 'position' => 4],
            ['name' => 'Fruits secs', 'emoji' => '🌰', 'position' => 5],
            ['name' => 'Carnisseria', 'emoji' => '🍗', 'position' => 6],
            ['name' => 'Fruita', 'emoji' => '🍎', 'position' => 7],
        ];

        $createdCats = [];
        foreach ($categories as $cat) {
            $createdCats[$cat['name']] = Category::firstOrCreate([
                'list_id' => $list->id,
                'name' => $cat['name'],
            ], [
                'emoji' => $cat['emoji'],
                'position' => $cat['position'],
            ]);
        }

        $sampleProduct = Product::firstOrCreate([
            'list_id' => $list->id,
            'name' => 'Llet sencera',
        ], [
            'category_id' => $createdCats['Làctics']->id,
            'quantity' => '2L',
            'is_completed' => false,
            'created_by' => $user->id,
        ]);
        $sampleProduct->tags()->syncWithoutDetaching([$createdTags['Mercadona']->id]);

        $sampleProduct2 = Product::firstOrCreate([
            'list_id' => $list->id,
            'name' => 'Pa de pagès',
        ], [
            'category_id' => $createdCats['Forn']->id,
            'quantity' => '1 barra',
            'is_completed' => true,
            'completed_by' => $user->id,
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);
        $sampleProduct2->tags()->syncWithoutDetaching([$createdTags['Bonpreu-Esclat']->id]);
