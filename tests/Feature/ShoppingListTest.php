<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_shopping_lists_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Mis Listas de la Compra');
    }

    public function test_user_can_create_a_new_shopping_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('lists.store'), [
            'name' => 'Compra Fin de Semana',
        ]);

        $this->assertDatabaseHas('lists', [
            'name' => 'Compra Fin de Semana',
            'owner_id' => $user->id,
        ]);

        $list = ShoppingList::where('name', 'Compra Fin de Semana')->first();
        $response->assertRedirect(route('lists.index', ['list_id' => $list->id]));
    }

    public function test_user_can_add_product_to_shopping_list(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::create([
            'name' => 'Demo Lista',
            'owner_id' => $user->id,
        ]);
        $list->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->post(route('products.store', $list), [
            'name' => 'Llet',
            'category' => 'Làctics',
            'tag' => 'Mercadona',
        ]);

        $this->assertDatabaseHas('products', [
            'list_id' => $list->id,
            'name' => 'Llet',
        ]);

        $this->assertDatabaseHas('categories', [
            'list_id' => $list->id,
            'name' => 'Làctics',
        ]);

        $this->assertDatabaseHas('tags', [
            'list_id' => $list->id,
            'name' => 'Mercadona',
        ]);

        $response->assertRedirect(route('lists.index', ['list_id' => $list->id]));
    }

