<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Support\BaseIngredientDetailViewPresenter;
use Illuminate\Http\JsonResponse;

class IngredientDetailViewController extends Controller
{
    public function __invoke(Ingredient $ingredient, BaseIngredientDetailViewPresenter $presenter): JsonResponse
    {
        if (! $ingredient->isPreparedBaseIngredient()) {
            abort(404);
        }

        $ingredient->load(['components' => function ($query): void {
            $query->orderBy('ingredients.name');
        }]);

        return response()->json([
            'title' => $ingredient->name,
            'detailView' => $presenter->build($ingredient),
        ]);
    }
}
