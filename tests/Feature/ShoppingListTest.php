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

    public function test_user_can_toggle_product_completion(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::create([
            'name' => 'Demo Lista',
            'owner_id' => $user->id,
        ]);
        $list->members()->attach($user->id, ['role' => 'owner']);

        $category = Category::create([
            'list_id' => $list->id,
            'name' => 'Làctics',
        ]);

        $product = Product::create([
            'list_id' => $list->id,
            'category_id' => $category->id,
            'name' => 'Llet',
            'is_completed' => false,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patch(route('products.toggle', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_completed' => true,
            'completed_by' => $user->id,
        ]);

        $response->assertRedirect(route('lists.index', ['list_id' => $list->id]));
    }

    public function test_user_can_share_list_with_another_email(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::create([
            'name' => 'Demo Evaluación',
            'owner_id' => $user->id,
        ]);
        $list->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->post(route('lists.share', $list), [
            'email' => 'oscar@ejemplo.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'oscar@ejemplo.com',
        ]);

        $this->assertDatabaseHas('list_invitations', [
            'list_id' => $list->id,
            'email' => 'oscar@ejemplo.com',
        ]);

        $response->assertRedirect(route('lists.index', ['list_id' => $list->id]));
    }

    public function test_user_can_delete_product(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::create([
            'name' => 'Demo Lista',
            'owner_id' => $user->id,
        ]);
        $list->members()->attach($user->id, ['role' => 'owner']);

        $product = Product::create([
            'list_id' => $list->id,
            'name' => 'Pan',
            'is_completed' => false,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('products.destroy', $product));

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);

        $response->assertRedirect(route('lists.index', ['list_id' => $list->id]));
    }
}
