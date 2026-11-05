<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function store(Request $request, ShoppingList $list): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();

        $category = Category::firstOrCreate(
            [
                'list_id' => $list->id,
                'name' => trim($validated['category']),
            ],
            [
                'position' => 0,
            ]
        );

        $product = Product::create([
            'list_id' => $list->id,
            'category_id' => $category->id,
            'name' => trim($validated['name']),
            'quantity' => $validated['quantity'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_completed' => false,
            'created_by' => $user->id,
        ]);

        if (!empty($validated['tag'])) {
            $tag = Tag::firstOrCreate([
                'list_id' => $list->id,
                'name' => trim($validated['tag']),
            ]);

            $product->tags()->attach($tag->id);
        }

        Activity::create([
            'list_id' => $list->id,
            'user_id' => $user->id,
            'action' => 'añadió el producto',
            'subject_name' => $product->name,
            'created_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'product' => $product->load(['category', 'tags']),
            ]);
        }

        return redirect()->route('lists.index', ['list_id' => $list->id])
            ->with('status', 'Producto añadido.');
    }

    public function quickAdd(Request $request, ShoppingList $list): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        $category = Category::firstOrCreate(
            [
                'list_id' => $list->id,
                'name' => trim($validated['category']),
            ],
            [
                'position' => 0,
            ]
        );

        $product = Product::create([
            'list_id' => $list->id,
            'category_id' => $category->id,
            'name' => trim($validated['name']),
            'is_completed' => false,
            'created_by' => $user->id,
        ]);

        Activity::create([
            'list_id' => $list->id,
            'user_id' => $user->id,
            'action' => 'añadió rápido',
            'subject_name' => $product->name,
            'created_at' => now(),
        ]);

        return redirect()->route('lists.index', ['list_id' => $list->id])
            ->with('status', 'Artículo añadido del catálogo.');
    }

    public function toggleComplete(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $isCompleted = !$product->is_completed;

        $product->update([
            'is_completed' => $isCompleted,
            'completed_by' => $isCompleted ? $user->id : null,
            'completed_at' => $isCompleted ? now() : null,
        ]);

        Activity::create([
            'list_id' => $product->list_id,
            'user_id' => $user->id,
            'action' => $isCompleted ? 'completó' : 'desmarcó',
            'subject_name' => $product->name,
            'created_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_completed' => $product->is_completed,
            ]);
        }

        return redirect()->route('lists.index', ['list_id' => $product->list_id]);
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $listId = $product->list_id;
        $productName = $product->name;

        $product->delete();

        Activity::create([
            'list_id' => $listId,
            'user_id' => $user->id,
            'action' => 'eliminó el producto',
            'subject_name' => $productName,
            'created_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('lists.index', ['list_id' => $listId])
            ->with('status', 'Producto eliminado.');
    }
}
