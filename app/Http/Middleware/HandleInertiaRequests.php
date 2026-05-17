<?php

namespace App\Http\Middleware;

use App\Support\MealCraftInertiaSharedData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $mealCraft = MealCraftInertiaSharedData::forRequest($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->getKey(),
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'initials' => $request->user()->initials(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'mealCraft' => $mealCraft !== [] ? $mealCraft : null,
            // Backward-compatible aliases (prefer `mealCraft.urls.*` in new code).
            'mealBulkDestroyUrl' => $mealCraft['urls']['mealLibrary']['bulkDestroy'] ?? null,
            'mealReorderUrl' => $mealCraft['urls']['mealLibrary']['reorder'] ?? null,
            'ingredientBulkDestroyUrl' => $mealCraft['urls']['ingredientLibrary']['bulkDestroy'] ?? null,
            'mealLibrarySchemaNotice' => $mealCraft['notices']['mealLibrarySchema'] ?? null,
        ];
    }
}
