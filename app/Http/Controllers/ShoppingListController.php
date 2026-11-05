<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Category;
use App\Models\CategoryKeyword;
use App\Models\ListInvitation;
use App\Models\ProductTemplate;
use App\Models\ShoppingList;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $lists = ShoppingList::where('owner_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->with(['owner', 'members'])
            ->latest()
            ->get();

        $selectedListId = $request->query('list_id', $lists->first()?->id);
        $activeList = null;
        $groupedProducts = collect();

        if ($selectedListId) {
            $activeList = $lists->firstWhere('id', (int) $selectedListId);

            if ($activeList) {
                $activeList->load([
                    'categories',
                    'tags',
                    'members',
                    'owner',
                    'activities.user',
                    'products' => function ($query) {
                        $query->with(['category', 'tags', 'completedByUser', 'createdByUser'])
                            ->orderBy('is_completed')
                            ->latest();
                    },
                ]);

                $groupedProducts = $activeList->products->groupBy(function ($product) {
                    return $product->category ? $product->category->name : 'Sin categoría';
                });
            }
        }

        $catalog = ProductTemplate::orderBy('name')->get();
        $keywords = CategoryKeyword::pluck('category_name', 'keyword')->toArray();

        return view('shopping-lists.index', [
            'lists' => $lists,
            'activeList' => $activeList,
            'groupedProducts' => $groupedProducts,
            'catalog' => $catalog,
            'categoryKeywords' => $keywords,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'emoji' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $user = Auth::user();

        $list = ShoppingList::create([
            'name' => $validated['name'],
            'owner_id' => $user->id,
            'emoji' => $validated['emoji'] ?? '🛒',
            'color' => $validated['color'] ?? '#4f46e5',
        ]);

        $list->members()->attach($user->id, ['role' => 'owner']);

        $defaultCategories = [
            ['name' => 'Làctics', 'emoji' => '🥛', 'position' => 1],
            ['name' => 'Forn', 'emoji' => '🥖', 'position' => 2],
            ['name' => 'Frescos', 'emoji' => '🥚', 'position' => 3],
            ['name' => 'Begudes', 'emoji' => '💧', 'position' => 4],
            ['name' => 'Fruits secs', 'emoji' => '🌰', 'position' => 5],
            ['name' => 'Carnisseria', 'emoji' => '🍗', 'position' => 6],
            ['name' => 'Fruita', 'emoji' => '🍎', 'position' => 7],
        ];

        foreach ($defaultCategories as $category) {
            Category::create([
                'list_id' => $list->id,
                'name' => $category['name'],
                'emoji' => $category['emoji'],
                'position' => $category['position'],
            ]);
        }

        $defaultTags = [
            ['name' => 'Mercadona', 'color' => '#10b981'],
            ['name' => 'Bonpreu-Esclat', 'color' => '#f97316'],
            ['name' => 'Urgente', 'color' => '#ef4444'],
        ];

        foreach ($defaultTags as $tag) {
            Tag::create([
                'list_id' => $list->id,
                'name' => $tag['name'],
                'color' => $tag['color'],
            ]);
        }

        Activity::create([
            'list_id' => $list->id,
            'user_id' => $user->id,
            'action' => 'creó la lista',
            'subject_name' => $list->name,
            'created_at' => now(),
        ]);

        return redirect()->route('lists.index', ['list_id' => $list->id])
            ->with('status', 'Lista creada con éxito.');
    }

