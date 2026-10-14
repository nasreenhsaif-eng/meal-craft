<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerCraftKitchenSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KitchenDailySheetController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403);
        }

        $dateInput = $request->query('date', now()->toDateString());

        try {
            $productionDate = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Invalid production date.',
            ], 422);
        }

        $rows = CustomerCraftKitchenSheetService::kitchenRowsForDate($productionDate);

        return response()->json([
            'production_date' => $productionDate->toDateString(),
            'rows' => $rows,
            'ingredient_lines' => CustomerCraftKitchenSheetService::ingredientLinesForDate($productionDate),
            'base_recipes' => CustomerCraftKitchenSheetService::baseRecipesFromKitchenRows($rows),
        ]);
    }
}
