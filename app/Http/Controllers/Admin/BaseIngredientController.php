<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBaseIngredientRequest;
use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class BaseIngredientController extends Controller
{
    public function store(StoreBaseIngredientRequest $request, BaseIngredientService $service): RedirectResponse
    {
        $data = $request->validated();

        try {
            $service->upsert(
                null,
                $data['name'],
                $this->componentRowsFromValidated($data['components']),
                isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', __('Base ingredient saved to the library.'));
    }

    public function update(
        StoreBaseIngredientRequest $request,
        Ingredient $ingredient,
        BaseIngredientService $service,
    ): RedirectResponse {
        if (! $ingredient->isPreparedBaseIngredient()) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', __('Only prepared base ingredients can be updated here.'));
        }

        $data = $request->validated();

        try {
            $service->upsert(
                $ingredient,
                $data['name'],
                $this->componentRowsFromValidated($data['components']),
                isset($data['finished_weight_grams']) ? (float) $data['finished_weight_grams'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.ingredient-library')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', __('Base ingredient updated.'));
    }

    /**
     * @param  list<array{ingredient_id: int|string, amount_grams: float|string}>  $components
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    private function componentRowsFromValidated(array $components): array
    {
        $rows = [];
        foreach ($components as $row) {
            $rows[] = [
                'ingredient_id' => (int) $row['ingredient_id'],
                'amount_grams' => (float) $row['amount_grams'],
            ];
        }

        return $rows;
    }
}
