<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DietTag;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Inertia\Inertia;
use Inertia\Response;

class IngredientLibraryController extends Controller
{
    public function index(): Response
    {
        $ingredients = Ingredient::query()
            ->where('is_verified', 1)
            ->latest()
            ->get()
            ->map(fn (Ingredient $ingredient): array => $this->toLibraryRow($ingredient))
            ->values()
            ->all();

        return Inertia::render('Admin/IngredientsLibrary', [
            'dietTags' => DietTag::toDropdownOptions(),
            'ingredients' => $ingredients,
            'csvTemplateUrl' => asset('templates/ingredients-library-template.csv'),
            'csvExportUrl' => route('admin.ingredient-library.export-csv'),
            'csvImportUrl' => route('admin.ingredient-library.import-csv'),
        ]);
    }

    /**
     * Flatten DB + micronutrients JSON into the shape expected by the Ingredients Library table.
     *
     * @return array<string, mixed>
     */
    private function toLibraryRow(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];
        $highlights = $ingredient->highlights;

        $badgeLabels = [];
        if ($highlights['folate'] ?? false) {
            $badgeLabels[] = 'Folate';
        }
        if ($highlights['b12'] ?? false) {
            $badgeLabels[] = 'B12';
        }
        if ($highlights['magnesium'] ?? false) {
            $badgeLabels[] = 'Magnesium';
        }
        if ($highlights['iron'] ?? false) {
            $badgeLabels[] = 'Iron';
        }
        if ($highlights['zinc'] ?? false) {
            $badgeLabels[] = 'Zinc';
        }

        return [
            'id' => (string) $ingredient->id,
            'name' => $ingredient->name,
            'category' => $ingredient->usda_food_category ?? '',
            'fdc' => $ingredient->fdc_id !== null ? (string) $ingredient->fdc_id : '—',
            'highlights' => $badgeLabels,
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'vitA' => (float) ($micros['vitamin_a'] ?? 0),
            'vitB6' => (float) $ingredient->b6,
            'vitB9' => (float) $ingredient->b9_folate,
            'vitB12' => (float) $ingredient->b12,
            'vitC' => (float) ($micros['vitamin_c'] ?? 0),
            'vitD' => (float) ($micros['vitamin_d'] ?? 0),
            'vitE' => (float) ($micros['vitamin_e'] ?? 0),
            'vitK' => (float) ($micros['vitamin_k'] ?? 0),
            'calcium' => (float) ($micros['calcium'] ?? 0),
            'iron' => (float) $ingredient->iron,
            'magnesium' => (float) $ingredient->magnesium,
            'potassium' => (float) ($micros['potassium'] ?? 0),
            'zinc' => (float) ($micros['zinc'] ?? 0),
            'sodium' => (float) ($micros['sodium'] ?? 0),
            'sugar' => (float) ($micros['sugar'] ?? 0),
            'fiber' => (float) ($micros['fiber'] ?? 0),
            /** Raw per-100 g JSON (same keys as import CSV) so the UI can fall back if flat fields are missing. */
            'micronutrients' => $micros,
        ];
    }
}
