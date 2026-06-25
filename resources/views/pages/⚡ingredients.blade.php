<?php

use App\IngredientsImport;
use App\Models\Ingredient;
use App\Services\MealCsvLibraryImportService;
use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Ingredients')] class extends Component {
    use WithFileUploads;

    public ?int $editingIngredientId = null;
    /** @var array<int, int|string> */
    public array $selectedIngredients = [];
    public bool $selectAll = false;

    public string $name = '';
    public string $category = '';

    public float $calories = 0;

    public float $protein = 0;

    public float $carbs = 0;

    public float $fat = 0;

    public string $micronutrients = '{}';

    /** Filters the saved-ingredients table. */
    public string $libraryTableSearch = '';

    #[On('focus-ingredient-library-search')]
    public function onFocusIngredientLibrarySearch(?string $q = null): void
    {
        $this->libraryTableSearch = is_string($q) ? $q : '';
    }

    public ?string $status = null;

    public ?string $error = null;

    public $importCsvFile;

    /** @var array{created: int, updated: int, skipped: int, unresolved: int}|null */
    public ?array $importSummary = null;

    /** @var array<int, array{row: int, reason: string, value: string}> */
    public array $importSkippedRows = [];

    // External API fetching is disabled. This page is CSV/manual-entry only.

    public function saveIngredient(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'calories' => ['required', 'numeric', 'min:0'],
            'protein' => ['required', 'numeric', 'min:0'],
            'carbs' => ['required', 'numeric', 'min:0'],
            'fat' => ['required', 'numeric', 'min:0'],
            'micronutrients' => ['required', 'string'],
        ]);

        $decodedMicronutrients = json_decode($validated['micronutrients'], true);

        if (! is_array($decodedMicronutrients)) {
            $this->error = 'Micronutrients must be valid JSON.';

            return;
        }

        $payload = array_merge(
            [
                'name' => $validated['name'],
                'usda_food_category' => filled($validated['category'] ?? null) ? $validated['category'] : null,
                'calories' => (float) $validated['calories'],
                'protein' => (float) $validated['protein'],
                'carbs' => (float) $validated['carbs'],
                'fat' => (float) $validated['fat'],
                'micronutrients' => $decodedMicronutrients,
                'is_verified' => true,
            ],
            [
                'b6' => (float) ($decodedMicronutrients['vitamin_b6'] ?? 0),
                'b9_folate' => (float) ($decodedMicronutrients['vitamin_b9'] ?? 0),
                'b12' => (float) ($decodedMicronutrients['vitamin_b12'] ?? 0),
                'iron' => (float) ($decodedMicronutrients['iron'] ?? 0),
                'magnesium' => (float) ($decodedMicronutrients['magnesium'] ?? 0),
            ],
        );

        if ($this->editingIngredientId !== null) {
            Ingredient::query()->whereKey($this->editingIngredientId)->update($payload);
            $this->status = 'Ingredient updated.';
        } else {
            Ingredient::query()->create($payload);
            $this->status = 'Ingredient created.';
        }

        $this->js('Livewire.dispatch("ingredientsImported")');

        app(MenuDevelopmentCsvSync::class)->syncAllFromDatabase();

        $this->resetForm();
    }

    public function editIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::query()->findOrFail($ingredientId);

        $this->editingIngredientId = $ingredient->id;
        $this->name = $ingredient->name;
        $this->category = (string) ($ingredient->usda_food_category ?? '');
        $this->calories = $ingredient->calories;
        $this->protein = $ingredient->protein;
        $this->carbs = $ingredient->carbs;
        $this->fat = $ingredient->fat;
        $this->micronutrients = json_encode($ingredient->micronutrients ?? [], JSON_PRETTY_PRINT) ?: '{}';
        $this->error = null;
        $this->status = __('Ingredient loaded for editing — the form is above.');

        $this->js('window.requestAnimationFrame(() => document.getElementById("ingredient-editor-panel")?.scrollIntoView({behavior:"smooth",block:"start"}))');
    }

    public function deleteIngredient(int $ingredientId): void
    {
        Ingredient::query()->whereKey($ingredientId)->delete();

        if ($this->editingIngredientId === $ingredientId) {
            $this->resetForm();
        }

        $this->js('Livewire.dispatch("ingredientsImported")');

        app(MenuDevelopmentCsvSync::class)->syncAllFromDatabase();

        $this->status = 'Ingredient deleted.';
    }

    public function deleteSelected(): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($id) => is_numeric($id) ? (int) $id : null,
            $this->selectedIngredients
        ), fn ($id) => is_int($id) && $id > 0)));

        if ($ids === []) {
            $this->selectedIngredients = [];

            return;
        }

        if ($this->editingIngredientId !== null && in_array((int) $this->editingIngredientId, $ids, true)) {
            $this->resetForm();
        }

        Ingredient::query()->whereIn('id', $ids)->delete();

        $count = count($ids);
        $this->selectedIngredients = [];
        $this->status = $count === 1 ? 'Deleted 1 ingredient.' : "Deleted {$count} ingredients.";
        $this->error = null;

        $this->js('Livewire.dispatch("ingredientsImported")');

        app(MenuDevelopmentCsvSync::class)->syncAllFromDatabase();
    }

    public function clearSelection(): void
    {
        $this->selectedIngredients = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            // Safety: only select the currently visible results (honors search filter).
            $this->selectedIngredients = $this->ingredients->pluck('id')->all();
        } else {
            $this->selectedIngredients = [];
        }
    }

    /**
     * Keep the master checkbox in sync when the user toggles row checkboxes.
     */
    public function updatedSelectedIngredients(): void
    {
        $visibleIds = $this->ingredients->pluck('id')->all();

        if ($visibleIds === []) {
            $this->selectAll = false;

            return;
        }

        $selected = array_values(array_unique(array_filter(array_map(
            fn ($id) => is_numeric($id) ? (int) $id : null,
            $this->selectedIngredients
        ), fn ($id) => is_int($id) && $id > 0)));

        $this->selectedIngredients = $selected;
        $this->selectAll = count(array_intersect($visibleIds, $selected)) === count($visibleIds);
    }

    public function updatedLibraryTableSearch(): void
    {
        // Avoid accidental deletes when switching the search filter.
        $this->clearSelection();
    }

    public function cancelIngredientEditor(): void
    {
        $this->resetForm();
        $this->status = null;
    }

    // External API enrichment has been removed. CSV import is the primary source of nutrition data.



    /**
     * @param  array<string, mixed>  $product
     */
    private function labelForOpenFoodFactsProduct(array $product, float $calories): string
    {
        $name = $product['product_name'] ?? $product['product_name_en'] ?? $product['generic_name'] ?? '';
        $name = is_string($name) ? trim($name) : '';
        $brand = $product['brands'] ?? '';

        if (is_string($brand) && trim($brand) !== '') {
            $brand = trim(Str::before($brand, ','));
        } else {
            $brand = '';
        }

        $title = $name !== '' ? $name : (string) __('Unknown product');

        if ($brand !== '') {
            $title .= ' · '.$brand;
        }

        return $title.' — '.round($calories, 1).' kcal/100g';
    }

    /**
     * @return array<int, string>
     */
    private function ingredientSearchTerms(string $ingredientName): array
    {
        $normalized = Str::of($ingredientName)->lower()->replaceMatches('/[^a-z0-9 ]+/', '')->squish()->value();
        $firstWord = Str::before($normalized, ' ');
        $plural = Str::plural($firstWord);

        return collect([$ingredientName, $normalized, $firstWord, $plural])
            ->filter(fn (string $term) => $term !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function resolveAlias(string $ingredientName): string
    {
        $normalized = Str::of($ingredientName)->lower()->replaceMatches('/[^a-z0-9 ]+/', '')->squish()->value();

        /** @var array<string, string> $aliases */
        $aliases = [
            'capsicum' => 'bell pepper',
            'chickpeas' => 'chickpea',
            'garbanzo beans' => 'chickpea',
            'garbanzo bean' => 'chickpea',
            'coriander leaves' => 'cilantro',
            'spring onion' => 'green onion',
            'scallion' => 'green onion',
            'aubergine' => 'eggplant',
            'courgette' => 'zucchini',
            'sweet potato' => 'potato',
        ];

        return $aliases[$normalized] ?? $ingredientName;
    }

    /**
     * @param  array<string, mixed>  $nutriments
     * @return array<string, mixed>
     */
    private function extractMicronutrients(array $nutriments): array
    {
        return $this->normalizeMicronutrients([
            'vitamin_a' => $nutriments['vitamin-a_100g'] ?? $nutriments['vitamin-a'] ?? null,
            'vitamin_b6' => $nutriments['vitamin-b6_100g'] ?? $nutriments['vitamin-b6'] ?? null,
            'vitamin_b9' => $nutriments['vitamin-b9_100g'] ?? $nutriments['folates_100g'] ?? $nutriments['folic-acid_100g'] ?? null,
            'vitamin_b12' => $nutriments['vitamin-b12_100g'] ?? $nutriments['vitamin-b12'] ?? null,
            'vitamin_c' => $nutriments['vitamin-c_100g'] ?? $nutriments['vitamin-c'] ?? null,
            'vitamin_d' => $nutriments['vitamin-d_100g'] ?? $nutriments['vitamin-d'] ?? null,
            'vitamin_e' => $nutriments['vitamin-e_100g'] ?? $nutriments['vitamin-e'] ?? null,
            'fiber' => $nutriments['fiber_100g'] ?? $nutriments['fiber'] ?? null,
            'calcium' => $nutriments['calcium_100g'] ?? $nutriments['calcium'] ?? null,
            'iron' => $nutriments['iron_100g'] ?? $nutriments['iron'] ?? null,
            'magnesium' => $nutriments['magnesium_100g'] ?? $nutriments['magnesium'] ?? null,
            'potassium' => $nutriments['potassium_100g'] ?? $nutriments['potassium'] ?? null,
        ]);
    }

    /**
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float|null>}|null
     */
    private function fallbackNutrition(string $ingredientName): ?array
    {
        $key = $this->canonicalIngredientKey($ingredientName);

        /** @var array<string, array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float|null>}> $fallbacks */
        $fallbacks = [
            'apple' => ['calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.003, 'vitamin_b6' => 0.041, 'vitamin_b9' => 0.003, 'vitamin_b12' => 0, 'vitamin_c' => 4.6, 'vitamin_d' => 0, 'vitamin_e' => 0.18, 'fiber' => 2.4, 'calcium' => 6, 'iron' => 0.12, 'magnesium' => 5, 'potassium' => 107]],
            'apple juice' => ['calories' => 46, 'protein' => 0.1, 'carbs' => 11.3, 'fat' => 0.1, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.02, 'vitamin_b9' => 0.001, 'vitamin_b12' => 0, 'vitamin_c' => 0.9, 'vitamin_d' => 0, 'vitamin_e' => 0.01, 'fiber' => 0.2, 'calcium' => 8, 'iron' => 0.12, 'magnesium' => 5, 'potassium' => 101]],
            'apple cider vinegar' => ['calories' => 21, 'protein' => 0, 'carbs' => 0.9, 'fat' => 0, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0, 'vitamin_b9' => 0, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0, 'fiber' => 0, 'calcium' => 7, 'iron' => 0.2, 'magnesium' => 5, 'potassium' => 73]],
            'banana' => ['calories' => 89, 'protein' => 1.1, 'carbs' => 22.8, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0.003, 'vitamin_b6' => 0.4, 'vitamin_b9' => 0.02, 'vitamin_b12' => 0, 'vitamin_c' => 8.7, 'vitamin_d' => 0, 'vitamin_e' => 0.1, 'fiber' => 2.6, 'calcium' => 5, 'iron' => 0.26, 'magnesium' => 27, 'potassium' => 358]],
            'black beans' => ['calories' => 132, 'protein' => 8.9, 'carbs' => 23.7, 'fat' => 0.5, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.07, 'vitamin_b9' => 0.149, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.1, 'fiber' => 8.7, 'calcium' => 27, 'iron' => 2.1, 'magnesium' => 70, 'potassium' => 355]],
            'broccoli' => ['calories' => 34, 'protein' => 2.8, 'carbs' => 6.6, 'fat' => 0.4, 'micronutrients' => ['vitamin_a' => 0.031, 'vitamin_b6' => 0.175, 'vitamin_b9' => 0.063, 'vitamin_b12' => 0, 'vitamin_c' => 89.2, 'vitamin_d' => 0, 'vitamin_e' => 0.78, 'fiber' => 2.6, 'calcium' => 47, 'iron' => 0.73, 'magnesium' => 21, 'potassium' => 316]],
            'butter' => ['calories' => 717, 'protein' => 0.9, 'carbs' => 0.1, 'fat' => 81.1, 'micronutrients' => ['vitamin_a' => 0.684, 'vitamin_b6' => 0.003, 'vitamin_b9' => 0.003, 'vitamin_b12' => 0.17, 'vitamin_c' => 0, 'vitamin_d' => 1.5, 'vitamin_e' => 2.32, 'fiber' => 0, 'calcium' => 24, 'iron' => 0.02, 'magnesium' => 2, 'potassium' => 24]],
            'cannellini beans' => ['calories' => 139, 'protein' => 9.7, 'carbs' => 25.1, 'fat' => 0.6, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.14, 'vitamin_b9' => 0.172, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.03, 'fiber' => 6.3, 'calcium' => 69, 'iron' => 4, 'magnesium' => 63, 'potassium' => 561]],
            'carrot' => ['calories' => 41, 'protein' => 0.9, 'carbs' => 9.6, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.835, 'vitamin_b6' => 0.138, 'vitamin_b9' => 0.019, 'vitamin_b12' => 0, 'vitamin_c' => 5.9, 'vitamin_d' => 0, 'vitamin_e' => 0.66, 'fiber' => 2.8, 'calcium' => 33, 'iron' => 0.3, 'magnesium' => 12, 'potassium' => 320]],
            'cheddar cheese' => ['calories' => 403, 'protein' => 24.9, 'carbs' => 1.3, 'fat' => 33.1, 'micronutrients' => ['vitamin_a' => 0.265, 'vitamin_b6' => 0.08, 'vitamin_b9' => 0.018, 'vitamin_b12' => 1.0, 'vitamin_c' => 0, 'vitamin_d' => 0.6, 'vitamin_e' => 0.7, 'fiber' => 0, 'calcium' => 710, 'iron' => 0.7, 'magnesium' => 27, 'potassium' => 76]],
            'chicken breast' => ['calories' => 165, 'protein' => 31, 'carbs' => 0, 'fat' => 3.6, 'micronutrients' => ['vitamin_a' => 0.013, 'vitamin_b6' => 0.6, 'vitamin_b9' => 0.004, 'vitamin_b12' => 0.3, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.3, 'fiber' => 0, 'calcium' => 15, 'iron' => 1, 'magnesium' => 29, 'potassium' => 256]],
            'ginger' => ['calories' => 80, 'protein' => 1.8, 'carbs' => 17.8, 'fat' => 0.8, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.16, 'vitamin_b9' => 0.011, 'vitamin_b12' => 0, 'vitamin_c' => 5, 'vitamin_d' => 0, 'vitamin_e' => 0.26, 'fiber' => 2, 'calcium' => 16, 'iron' => 0.6, 'magnesium' => 43, 'potassium' => 415]],
            'egg' => ['calories' => 155, 'protein' => 13, 'carbs' => 1.1, 'fat' => 11, 'micronutrients' => ['vitamin_a' => 0.16, 'vitamin_b6' => 0.17, 'vitamin_b9' => 0.047, 'vitamin_b12' => 1.11, 'vitamin_c' => 0, 'vitamin_d' => 2.2, 'vitamin_e' => 1.05, 'fiber' => 0, 'calcium' => 50, 'iron' => 1.75, 'magnesium' => 10, 'potassium' => 126]],
            'feta cheese' => ['calories' => 265, 'protein' => 14.2, 'carbs' => 3.9, 'fat' => 21.5, 'micronutrients' => ['vitamin_a' => 0.125, 'vitamin_b6' => 0.07, 'vitamin_b9' => 0.032, 'vitamin_b12' => 1.7, 'vitamin_c' => 0, 'vitamin_d' => 0.5, 'vitamin_e' => 0.18, 'fiber' => 0, 'calcium' => 493, 'iron' => 0.65, 'magnesium' => 19, 'potassium' => 62]],
            'flour' => ['calories' => 364, 'protein' => 10.3, 'carbs' => 76.3, 'fat' => 1, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.04, 'vitamin_b9' => 0.026, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.06, 'fiber' => 2.7, 'calcium' => 15, 'iron' => 1.2, 'magnesium' => 22, 'potassium' => 107]],
            'olive oil' => ['calories' => 884, 'protein' => 0, 'carbs' => 0, 'fat' => 100, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0, 'vitamin_b9' => 0, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 14.35, 'fiber' => 0, 'calcium' => 1, 'iron' => 0.56, 'magnesium' => 0, 'potassium' => 1]],
            'onion' => ['calories' => 40, 'protein' => 1.1, 'carbs' => 9.3, 'fat' => 0.1, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.12, 'vitamin_b9' => 0.019, 'vitamin_b12' => 0, 'vitamin_c' => 7.4, 'vitamin_d' => 0, 'vitamin_e' => 0.02, 'fiber' => 1.7, 'calcium' => 23, 'iron' => 0.21, 'magnesium' => 10, 'potassium' => 146]],
            'kidney beans' => ['calories' => 127, 'protein' => 8.7, 'carbs' => 22.8, 'fat' => 0.5, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.07, 'vitamin_b9' => 0.13, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.03, 'fiber' => 6.4, 'calcium' => 28, 'iron' => 2.9, 'magnesium' => 45, 'potassium' => 405]],
            'mozzarella' => ['calories' => 280, 'protein' => 28, 'carbs' => 3.1, 'fat' => 17, 'micronutrients' => ['vitamin_a' => 0.18, 'vitamin_b6' => 0.09, 'vitamin_b9' => 0.013, 'vitamin_b12' => 2.3, 'vitamin_c' => 0, 'vitamin_d' => 0.4, 'vitamin_e' => 0.2, 'fiber' => 0, 'calcium' => 731, 'iron' => 0.2, 'magnesium' => 30, 'potassium' => 95]],
            'garlic' => ['calories' => 149, 'protein' => 6.4, 'carbs' => 33.1, 'fat' => 0.5, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 1.24, 'vitamin_b9' => 0.003, 'vitamin_b12' => 0, 'vitamin_c' => 31.2, 'vitamin_d' => 0, 'vitamin_e' => 0.08, 'fiber' => 2.1, 'calcium' => 181, 'iron' => 1.7, 'magnesium' => 25, 'potassium' => 401]],
            'lemon' => ['calories' => 29, 'protein' => 1.1, 'carbs' => 9.3, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.08, 'vitamin_b9' => 0.011, 'vitamin_b12' => 0, 'vitamin_c' => 53, 'vitamin_d' => 0, 'vitamin_e' => 0.15, 'fiber' => 2.8, 'calcium' => 26, 'iron' => 0.6, 'magnesium' => 8, 'potassium' => 138]],
            'lime' => ['calories' => 30, 'protein' => 0.7, 'carbs' => 10.5, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.002, 'vitamin_b6' => 0.04, 'vitamin_b9' => 0.008, 'vitamin_b12' => 0, 'vitamin_c' => 29.1, 'vitamin_d' => 0, 'vitamin_e' => 0.22, 'fiber' => 2.8, 'calcium' => 33, 'iron' => 0.6, 'magnesium' => 6, 'potassium' => 102]],
            'basil' => ['calories' => 23, 'protein' => 3.2, 'carbs' => 2.7, 'fat' => 0.6, 'micronutrients' => ['vitamin_a' => 0.264, 'vitamin_b6' => 0.155, 'vitamin_b9' => 0.068, 'vitamin_b12' => 0, 'vitamin_c' => 18, 'vitamin_d' => 0, 'vitamin_e' => 0.8, 'fiber' => 1.6, 'calcium' => 177, 'iron' => 3.17, 'magnesium' => 64, 'potassium' => 295]],
            'parsley' => ['calories' => 36, 'protein' => 3, 'carbs' => 6.3, 'fat' => 0.8, 'micronutrients' => ['vitamin_a' => 0.421, 'vitamin_b6' => 0.09, 'vitamin_b9' => 0.152, 'vitamin_b12' => 0, 'vitamin_c' => 133, 'vitamin_d' => 0, 'vitamin_e' => 0.75, 'fiber' => 3.3, 'calcium' => 138, 'iron' => 6.2, 'magnesium' => 50, 'potassium' => 554]],
            'lentils' => ['calories' => 116, 'protein' => 9, 'carbs' => 20.1, 'fat' => 0.4, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.178, 'vitamin_b9' => 0.181, 'vitamin_b12' => 0, 'vitamin_c' => 1.5, 'vitamin_d' => 0, 'vitamin_e' => 0.11, 'fiber' => 7.9, 'calcium' => 19, 'iron' => 3.3, 'magnesium' => 36, 'potassium' => 369]],
            'milk' => ['calories' => 61, 'protein' => 3.2, 'carbs' => 4.8, 'fat' => 3.3, 'micronutrients' => ['vitamin_a' => 0.046, 'vitamin_b6' => 0.04, 'vitamin_b9' => 0.005, 'vitamin_b12' => 0.45, 'vitamin_c' => 0, 'vitamin_d' => 1.3, 'vitamin_e' => 0.07, 'fiber' => 0, 'calcium' => 113, 'iron' => 0.03, 'magnesium' => 10, 'potassium' => 132]],
            'oats' => ['calories' => 389, 'protein' => 16.9, 'carbs' => 66.3, 'fat' => 6.9, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.12, 'vitamin_b9' => 0.056, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.42, 'fiber' => 10.6, 'calcium' => 54, 'iron' => 4.72, 'magnesium' => 177, 'potassium' => 429]],
            'potato' => ['calories' => 77, 'protein' => 2, 'carbs' => 17, 'fat' => 0.1, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.3, 'vitamin_b9' => 0.015, 'vitamin_b12' => 0, 'vitamin_c' => 19.7, 'vitamin_d' => 0, 'vitamin_e' => 0.01, 'fiber' => 2.2, 'calcium' => 12, 'iron' => 0.81, 'magnesium' => 23, 'potassium' => 425]],
            'rice' => ['calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0.05, 'vitamin_b9' => 0.003, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0.04, 'fiber' => 0.4, 'calcium' => 10, 'iron' => 0.2, 'magnesium' => 12, 'potassium' => 35]],
            'salmon' => ['calories' => 208, 'protein' => 20, 'carbs' => 0, 'fat' => 13, 'micronutrients' => ['vitamin_a' => 0.04, 'vitamin_b6' => 0.8, 'vitamin_b9' => 0.025, 'vitamin_b12' => 3.2, 'vitamin_c' => 0, 'vitamin_d' => 10.9, 'vitamin_e' => 2.0, 'fiber' => 0, 'calcium' => 9, 'iron' => 0.3, 'magnesium' => 27, 'potassium' => 363]],
            'spinach' => ['calories' => 23, 'protein' => 2.9, 'carbs' => 3.6, 'fat' => 0.4, 'micronutrients' => ['vitamin_a' => 0.469, 'vitamin_b6' => 0.195, 'vitamin_b9' => 0.194, 'vitamin_b12' => 0, 'vitamin_c' => 28.1, 'vitamin_d' => 0, 'vitamin_e' => 2.03, 'fiber' => 2.2, 'calcium' => 99, 'iron' => 2.71, 'magnesium' => 79, 'potassium' => 558]],
            'tofu' => ['calories' => 76, 'protein' => 8, 'carbs' => 1.9, 'fat' => 4.8, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.05, 'vitamin_b9' => 0.027, 'vitamin_b12' => 0, 'vitamin_c' => 0.1, 'vitamin_d' => 0, 'vitamin_e' => 0.01, 'fiber' => 0.3, 'calcium' => 350, 'iron' => 5.4, 'magnesium' => 30, 'potassium' => 121]],
            'tomato' => ['calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.042, 'vitamin_b6' => 0.08, 'vitamin_b9' => 0.015, 'vitamin_b12' => 0, 'vitamin_c' => 13.7, 'vitamin_d' => 0, 'vitamin_e' => 0.54, 'fiber' => 1.2, 'calcium' => 10, 'iron' => 0.27, 'magnesium' => 11, 'potassium' => 237]],
            'orange' => ['calories' => 47, 'protein' => 0.9, 'carbs' => 11.8, 'fat' => 0.1, 'micronutrients' => ['vitamin_a' => 0.011, 'vitamin_b6' => 0.06, 'vitamin_b9' => 0.03, 'vitamin_b12' => 0, 'vitamin_c' => 53.2, 'vitamin_d' => 0, 'vitamin_e' => 0.18, 'fiber' => 2.4, 'calcium' => 40, 'iron' => 0.1, 'magnesium' => 10, 'potassium' => 181]],
            'parmesan' => ['calories' => 431, 'protein' => 38, 'carbs' => 4.1, 'fat' => 29, 'micronutrients' => ['vitamin_a' => 0.207, 'vitamin_b6' => 0.09, 'vitamin_b9' => 0.01, 'vitamin_b12' => 1.2, 'vitamin_c' => 0, 'vitamin_d' => 0.5, 'vitamin_e' => 0.22, 'fiber' => 0, 'calcium' => 1184, 'iron' => 0.9, 'magnesium' => 44, 'potassium' => 92]],
            'strawberry' => ['calories' => 32, 'protein' => 0.7, 'carbs' => 7.7, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.047, 'vitamin_b9' => 0.024, 'vitamin_b12' => 0, 'vitamin_c' => 58.8, 'vitamin_d' => 0, 'vitamin_e' => 0.29, 'fiber' => 2, 'calcium' => 16, 'iron' => 0.41, 'magnesium' => 13, 'potassium' => 153]],
            'yogurt' => ['calories' => 59, 'protein' => 10, 'carbs' => 3.6, 'fat' => 0.4, 'micronutrients' => ['vitamin_a' => 0.006, 'vitamin_b6' => 0.05, 'vitamin_b9' => 0.007, 'vitamin_b12' => 0.75, 'vitamin_c' => 0.5, 'vitamin_d' => 1.0, 'vitamin_e' => 0.03, 'fiber' => 0, 'calcium' => 110, 'iron' => 0.05, 'magnesium' => 11, 'potassium' => 141]],
            'vinegar' => ['calories' => 18, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'micronutrients' => ['vitamin_a' => 0, 'vitamin_b6' => 0, 'vitamin_b9' => 0, 'vitamin_b12' => 0, 'vitamin_c' => 0, 'vitamin_d' => 0, 'vitamin_e' => 0, 'fiber' => 0, 'calcium' => 7, 'iron' => 0.2, 'magnesium' => 5, 'potassium' => 73]],
            'bell pepper' => ['calories' => 31, 'protein' => 1.0, 'carbs' => 6.0, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0.157, 'vitamin_b6' => 0.291, 'vitamin_b9' => 0.046, 'vitamin_b12' => 0, 'vitamin_c' => 127.7, 'vitamin_d' => 0, 'vitamin_e' => 1.58, 'fiber' => 2.1, 'calcium' => 7, 'iron' => 0.43, 'magnesium' => 12, 'potassium' => 211]],
            'chickpea' => ['calories' => 164, 'protein' => 8.9, 'carbs' => 27.4, 'fat' => 2.6, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.139, 'vitamin_b9' => 0.172, 'vitamin_b12' => 0, 'vitamin_c' => 1.3, 'vitamin_d' => 0, 'vitamin_e' => 0.35, 'fiber' => 7.6, 'calcium' => 49, 'iron' => 2.89, 'magnesium' => 48, 'potassium' => 291]],
            'cilantro' => ['calories' => 23, 'protein' => 2.1, 'carbs' => 3.7, 'fat' => 0.5, 'micronutrients' => ['vitamin_a' => 0.337, 'vitamin_b6' => 0.149, 'vitamin_b9' => 0.062, 'vitamin_b12' => 0, 'vitamin_c' => 27, 'vitamin_d' => 0, 'vitamin_e' => 2.5, 'fiber' => 2.8, 'calcium' => 67, 'iron' => 1.77, 'magnesium' => 26, 'potassium' => 521]],
            'green onion' => ['calories' => 32, 'protein' => 1.8, 'carbs' => 7.3, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.05, 'vitamin_b6' => 0.061, 'vitamin_b9' => 0.064, 'vitamin_b12' => 0, 'vitamin_c' => 18.8, 'vitamin_d' => 0, 'vitamin_e' => 0.55, 'fiber' => 2.6, 'calcium' => 72, 'iron' => 1.48, 'magnesium' => 20, 'potassium' => 276]],
            'eggplant' => ['calories' => 25, 'protein' => 1.0, 'carbs' => 5.9, 'fat' => 0.2, 'micronutrients' => ['vitamin_a' => 0.001, 'vitamin_b6' => 0.084, 'vitamin_b9' => 0.022, 'vitamin_b12' => 0, 'vitamin_c' => 2.2, 'vitamin_d' => 0, 'vitamin_e' => 0.3, 'fiber' => 3.0, 'calcium' => 9, 'iron' => 0.23, 'magnesium' => 14, 'potassium' => 229]],
            'zucchini' => ['calories' => 17, 'protein' => 1.2, 'carbs' => 3.1, 'fat' => 0.3, 'micronutrients' => ['vitamin_a' => 0.01, 'vitamin_b6' => 0.163, 'vitamin_b9' => 0.024, 'vitamin_b12' => 0, 'vitamin_c' => 17.9, 'vitamin_d' => 0, 'vitamin_e' => 0.12, 'fiber' => 1.0, 'calcium' => 16, 'iron' => 0.37, 'magnesium' => 18, 'potassium' => 261]],
        ];

        if (! isset($fallbacks[$key])) {
            return null;
        }

        return [
            'calories' => $fallbacks[$key]['calories'],
            'protein' => $fallbacks[$key]['protein'],
            'carbs' => $fallbacks[$key]['carbs'],
            'fat' => $fallbacks[$key]['fat'],
            'micronutrients' => $this->normalizeMicronutrients($fallbacks[$key]['micronutrients']),
        ];
    }

    private function canonicalIngredientKey(string $ingredientName): string
    {
        $normalized = Str::of($ingredientName)
            ->lower()
            ->replaceMatches('/\([^)]*\)/', ' ')
            ->replaceMatches('/[^a-z0-9 ]+/', ' ')
            ->squish()
            ->value();

        /** @var array<string, string> $exactAliases */
        $exactAliases = [
            'apple red' => 'apple',
            'apple green' => 'apple',
            'bananas' => 'banana',
            'capsicum' => 'bell pepper',
            'aleppo pepper' => 'bell pepper',
            'bell peppers' => 'bell pepper',
            'basmati rice white' => 'rice',
            'basmati rice brown' => 'rice',
            'sushi rice' => 'rice',
            'arborio rice' => 'rice',
            'chicken' => 'chicken breast',
            'chicken leg' => 'chicken breast',
            'chicken fillet' => 'chicken breast',
            'chicken tenderloin' => 'chicken breast',
            'olive oil extra virgin' => 'olive oil',
            'olive oil' => 'olive oil',
            'butter ghee salted unsalted' => 'butter',
            'butter salted' => 'butter',
            'butter unsalted' => 'butter',
            'ghee' => 'butter',
            'clarified butter' => 'butter',
            'onions' => 'onion',
            'red onion' => 'onion',
            'white onion' => 'onion',
            'yellow onion' => 'onion',
            'shallot' => 'onion',
            'scallion' => 'green onion',
            'spring onion' => 'green onion',
            'garlic powder' => 'garlic',
            'ginger root' => 'ginger',
            'tomatoes' => 'tomato',
            'cherry tomatoes' => 'tomato',
            'roma tomatoes' => 'tomato',
            'plum tomatoes' => 'tomato',
            'sun dried tomatoes' => 'tomato',
            'oat flour' => 'oats',
            'rolled oats' => 'oats',
            'quick oats' => 'oats',
            'sweet potato' => 'potato',
            'potatoes' => 'potato',
            'black beans dried cooked' => 'black beans',
            'kidney beans red dried cooked' => 'kidney beans',
            'cannellini beans white beans' => 'cannellini beans',
            'rice vinegar' => 'vinegar',
            'red wine vinegar' => 'vinegar',
            'white vinegar' => 'vinegar',
            'balsamic vinegar' => 'vinegar',
            'feta cheese goat sheep' => 'feta cheese',
            'cheddar cheese' => 'cheddar cheese',
            'mozzarella' => 'mozzarella',
            'parmesan' => 'parmesan',
            'almond flour' => 'flour',
            'cashew flour' => 'flour',
            'cassava flour' => 'flour',
            'gluten free flour blend' => 'flour',
            'parsley flat leaf' => 'parsley',
            'parsley italian' => 'parsley',
            'cilantro coriander' => 'cilantro',
            'chickpeas cooked dried' => 'chickpea',
            'kidney beans red dried cooked' => 'kidney beans',
            'black beans dried cooked' => 'black beans',
            'lentils green red brown' => 'lentils',
            'spinach' => 'spinach',
            'broccolini' => 'broccoli',
            'eggplant aubergine' => 'eggplant',
            'lemon juice zest preserved' => 'lemon',
            'lime juice zest leaves' => 'lime',
            'orange juice zest' => 'orange',
            'strawberries' => 'strawberry',
        ];

        if (isset($exactAliases[$normalized])) {
            return $exactAliases[$normalized];
        }

        if (str_contains($normalized, 'olive oil')) {
            return 'olive oil';
        }

        if (str_contains($normalized, 'butter') || str_contains($normalized, 'ghee')) {
            return 'butter';
        }

        if (str_contains($normalized, 'apple cider vinegar')) {
            return 'apple cider vinegar';
        }

        if (str_contains($normalized, 'vinegar')) {
            return 'vinegar';
        }

        if (str_contains($normalized, 'apple juice')) {
            return 'apple juice';
        }

        if (str_contains($normalized, 'apple')) {
            return 'apple';
        }

        if (str_contains($normalized, 'basmati rice') || str_contains($normalized, 'sushi rice') || str_contains($normalized, 'arborio rice') || str_contains($normalized, 'rice')) {
            return 'rice';
        }

        if (str_contains($normalized, 'chicken')) {
            return 'chicken breast';
        }

        if (str_contains($normalized, 'bell pepper') || str_contains($normalized, 'capsicum')) {
            return 'bell pepper';
        }

        if (str_contains($normalized, 'egg')) {
            return 'egg';
        }

        if (str_contains($normalized, 'onion') || str_contains($normalized, 'shallot')) {
            return 'onion';
        }

        if (str_contains($normalized, 'scallion') || str_contains($normalized, 'spring onion')) {
            return 'green onion';
        }

        if (str_contains($normalized, 'garlic')) {
            return 'garlic';
        }

        if (str_contains($normalized, 'lentil')) {
            return 'lentils';
        }

        if (str_contains($normalized, 'kidney bean')) {
            return 'kidney beans';
        }

        if (str_contains($normalized, 'black bean')) {
            return 'black beans';
        }

        if (str_contains($normalized, 'cannellini bean') || str_contains($normalized, 'white bean')) {
            return 'cannellini beans';
        }

        if (str_contains($normalized, 'lemon')) {
            return 'lemon';
        }

        if (str_contains($normalized, 'lime')) {
            return 'lime';
        }

        if (str_contains($normalized, 'tomato')) {
            return 'tomato';
        }

        if (str_contains($normalized, 'oat')) {
            return 'oats';
        }

        if (str_contains($normalized, 'potato')) {
            return 'potato';
        }

        if (str_contains($normalized, 'parsley')) {
            return 'parsley';
        }

        if (str_contains($normalized, 'basil')) {
            return 'basil';
        }

        if (str_contains($normalized, 'feta')) {
            return 'feta cheese';
        }

        if (str_contains($normalized, 'cheddar')) {
            return 'cheddar cheese';
        }

        if (str_contains($normalized, 'mozzarella')) {
            return 'mozzarella';
        }

        if (str_contains($normalized, 'parmesan')) {
            return 'parmesan';
        }

        if (str_contains($normalized, 'flour')) {
            return 'flour';
        }

        if (str_contains($normalized, 'ginger')) {
            return 'ginger';
        }

        if (str_contains($normalized, 'orange')) {
            return 'orange';
        }

        if (str_contains($normalized, 'strawberr')) {
            return 'strawberry';
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $micronutrients
     * @return array<string, float>
     */
    private function normalizeMicronutrients(array $micronutrients): array
    {
        return [
            'vitamin_a' => (float) ($micronutrients['vitamin_a'] ?? 0),
            'vitamin_b6' => (float) ($micronutrients['vitamin_b6'] ?? 0),
            'vitamin_b9' => (float) ($micronutrients['vitamin_b9'] ?? 0),
            'vitamin_b12' => (float) ($micronutrients['vitamin_b12'] ?? 0),
            'vitamin_c' => (float) ($micronutrients['vitamin_c'] ?? 0),
            'vitamin_d' => (float) ($micronutrients['vitamin_d'] ?? 0),
            'vitamin_e' => (float) ($micronutrients['vitamin_e'] ?? 0),
            'fiber' => (float) ($micronutrients['fiber'] ?? 0),
            'calcium' => (float) ($micronutrients['calcium'] ?? 0),
            'iron' => (float) ($micronutrients['iron'] ?? 0),
            'magnesium' => (float) ($micronutrients['magnesium'] ?? 0),
            'potassium' => (float) ($micronutrients['potassium'] ?? 0),
        ];
    }

    public function importCsv(): void
    {
        $this->validate([
            'importCsvFile' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        if (! $this->importCsvFile instanceof UploadedFile) {
            $this->error = 'CSV upload is invalid.';

            return;
        }

        $count = app(IngredientsImport::class)->import($this->importCsvFile);

        $mealFollowUp = ['imported' => 0, 'updated' => 0, 'still_pending' => 0];
        if (auth()->check()) {
            $mealFollowUp = app(MealCsvLibraryImportService::class)->processPendingMealImportsForUser(auth()->user());
        }

        $this->importCsvFile = null;
        $this->importSummary = null;
        $this->importSkippedRows = [];
        $this->status = __('Import Complete: :n ingredients updated with full nutritional profiles.', ['n' => $count]);
        if (($mealFollowUp['imported'] ?? 0) > 0 || ($mealFollowUp['updated'] ?? 0) > 0) {
            $this->status .= ' '.__(
                'Meal library: :imported new meal(s) created and :updated updated from your pending meal CSV import.',
                [
                    'imported' => $mealFollowUp['imported'],
                    'updated' => $mealFollowUp['updated'],
                ],
            );
        }
        if (($mealFollowUp['still_pending'] ?? 0) > 0) {
            $this->status .= ' '.__('Some meal CSV rows are still waiting on missing ingredients.');
        }
        $this->error = null;

        $this->js('Livewire.dispatch("ingredientsImported")');
    }

    /**
     * @return array<int, string>
     */
    private function extractIngredientNames(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractIngredientNamesFromRaw(string $rawContent): array
    {
        $content = str_replace("\u{FEFF}", '', $rawContent);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return collect(explode("\n", $content))
            ->map(function (string $line): array {
                $trimmedLine = trim($line);

                if ($trimmedLine === '') {
                    return [];
                }

                $columns = str_getcsv($trimmedLine);
                $firstColumn = trim((string) ($columns[0] ?? ''));

                if (strtolower($firstColumn) === 'name') {
                    return [];
                }

                if (str_contains($firstColumn, ';')) {
                    return collect(explode(';', $firstColumn))
                        ->map(fn ($part): string => trim($part))
                        ->filter()
                        ->values()
                        ->all();
                }

                return [$firstColumn];
            })
            ->flatten()
            ->map(fn ($name): string => trim((string) $name, " \t\n\r\0\x0B\"'"))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{calories: mixed, protein: mixed, carbs: mixed, fat: mixed, micronutrients: array<string, mixed>}|null  $record
     * @param  array<string, array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, mixed>}>  $nutritionCache
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, mixed>}
     */
    private function resolveImportNutritionData(string $ingredientName, ?array $record, array &$nutritionCache, int &$unresolved): array
    {
        $recordCalories = trim((string) ($record['calories'] ?? ''));
        $recordProtein = trim((string) ($record['protein'] ?? ''));
        $recordCarbs = trim((string) ($record['carbs'] ?? ''));
        $recordFat = trim((string) ($record['fat'] ?? ''));
        $recordMicronutrients = $record['micronutrients'] ?? [];

        $hasAnyMacroValue = $recordCalories !== '' || $recordProtein !== '' || $recordCarbs !== '' || $recordFat !== '';
        $hasMicronutrients = is_array($recordMicronutrients) && $recordMicronutrients !== [];

        if ($hasAnyMacroValue || $hasMicronutrients) {
            return [
                'calories' => (float) ($record['calories'] ?? 0),
                'protein' => (float) ($record['protein'] ?? 0),
                'carbs' => (float) ($record['carbs'] ?? 0),
                'fat' => (float) ($record['fat'] ?? 0),
                'micronutrients' => is_array($recordMicronutrients) ? $recordMicronutrients : [],
            ];
        }

        $normalizedName = $this->resolveAlias($ingredientName);
        $cacheKey = Str::of($normalizedName)->lower()->replaceMatches('/[^a-z0-9 ]+/', '')->squish()->value();

        if (! isset($nutritionCache[$cacheKey])) {
            $nutritionCache[$cacheKey] = $this->fallbackNutrition($normalizedName)
                ?? [
                    'calories' => 0,
                    'protein' => 0,
                    'carbs' => 0,
                    'fat' => 0,
                    'micronutrients' => [],
                ];

            if ($nutritionCache[$cacheKey]['calories'] <= 0) {
                $unresolved++;
            }
        }

        return $nutritionCache[$cacheKey];
    }

    public function resetForm(): void
    {
        $this->editingIngredientId = null;
        $this->name = '';
        $this->category = '';
        $this->calories = 0;
        $this->protein = 0;
        $this->carbs = 0;
        $this->fat = 0;
        $this->micronutrients = '{}';
        $this->error = null;
    }

    public function exportCsv()
    {
        $filename = 'ingredients-'.Carbon::now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', 'Calories', 'Protein', 'Carbs', 'Fat', 'Micronutrients'], ',', '"', '\\');

            Ingredient::query()
                ->orderBy('name')
                ->each(function (Ingredient $ingredient) use ($handle): void {
                    fputcsv($handle, [
                        $ingredient->name,
                        $ingredient->calories,
                        $ingredient->protein,
                        $ingredient->carbs,
                        $ingredient->fat,
                        json_encode($ingredient->micronutrients ?? []),
                    ], ',', '"', '\\');
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadCsvTemplate()
    {
        $filename = 'ingredients-template.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name',
                'category',
                'fdc_id',
                'calories',
                'protein',
                'carbs',
                'fat',
                'b6',
                'b9_folate',
                'b12',
                'iron',
                'magnesium',
                'fiber',
                'sugar',
                'calcium',
                'potassium',
                'sodium',
                'zinc',
                'vitamin_c',
                'vitamin_a',
                'vitamin_e',
                'vitamin_d',
                'vitamin_k2',
            ], ',', '"', '\\');
            fputcsv($handle, [
                'Banana',
                'Fruits and Fruit Juices',
                '',
                '89',
                '1.1',
                '22.8',
                '0.3',
                '0.37',
                '20',
                '0',
                '0.26',
                '27',
                '2.6',
                '12.2',
                '5',
                '358',
                '1',
                '0.15',
                '8.7',
                '0.003',
                '0.1',
                '0',
                '0',
            ], ',', '"', '\\');
            fputcsv($handle, [
                'Spinach',
                'Vegetables and Vegetable Products',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ], ',', '"', '\\');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function downloadSkippedRowsCsv()
    {
        if ($this->importSummary === null || ($this->importSummary['skipped'] ?? 0) === 0 || $this->importSkippedRows === []) {
            $this->error = 'No skipped rows available to download.';

            return null;
        }

        $filename = 'ingredients-import-skipped-rows.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['row', 'reason', 'value'], ',', '"', '\\');

            foreach ($this->importSkippedRows as $skippedRow) {
                fputcsv($handle, [
                    $skippedRow['row'],
                    $skippedRow['reason'],
                    $skippedRow['value'],
                ], ',', '"', '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Writes {@see MealCraftIngredientsJsonExporter::defaultPath()} after the HTTP response when not in tests,
     * so single-row enrichment does not block the browser on large libraries (reduces 504 / max_execution_time).
     *
     * @throws \JsonException
     */
    private function queueLibraryJsonExportAfterResponse(): void
    {
        if (app()->runningUnitTests()) {
            MealCraftIngredientsJsonExporter::export();

            return;
        }

        ExportMealCraftIngredientsJsonJob::dispatch()->afterResponse();
    }

    

    /**
     * @param  array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, mixed>}  $base
     * @param  array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, mixed>}|null  $supplemental
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float>}
     */
    private function mergeNutritionData(array $base, ?array $supplemental): array
    {
        if ($supplemental === null) {
            return [
                'calories' => (float) $base['calories'],
                'protein' => (float) $base['protein'],
                'carbs' => (float) $base['carbs'],
                'fat' => (float) $base['fat'],
                'micronutrients' => $this->normalizeMicronutrients($base['micronutrients']),
            ];
        }

        $mergedMicronutrients = $this->normalizeMicronutrients($base['micronutrients']);
        $supplementMicronutrients = $this->normalizeMicronutrients($supplemental['micronutrients']);

        foreach ($mergedMicronutrients as $key => $value) {
            if ($value <= 0 && ($supplementMicronutrients[$key] ?? 0) > 0) {
                $mergedMicronutrients[$key] = $supplementMicronutrients[$key];
            }
        }

        return [
            'calories' => (float) ($base['calories'] > 0 ? $base['calories'] : $supplemental['calories']),
            'protein' => (float) ($base['protein'] > 0 ? $base['protein'] : $supplemental['protein']),
            'carbs' => (float) ($base['carbs'] > 0 ? $base['carbs'] : $supplemental['carbs']),
            'fat' => (float) ($base['fat'] > 0 ? $base['fat'] : $supplemental['fat']),
            'micronutrients' => $mergedMicronutrients,
        ];
    }

    /**
     * @param  array<string, mixed>  $micronutrients
     */
    private function hasMissingMicronutrients(array $micronutrients): bool
    {
        return collect($this->normalizeMicronutrients($micronutrients))->contains(fn (float $value): bool => $value <= 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $foodNutrients
     * @return array<string, float>
     */
    private function buildUsdaNutrientNumberMapFromFoodNutrients(array $foodNutrients): array
    {
        return [];
    }

    /**
     * @param  array<string, float>  $byNumber
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float>}|null
     */
    private function nutritionFromUsdaNutrientNumberMap(array $byNumber): ?array
    {
        return null;
    }

    private function usdaDataTypeSortRank(string $dataType): int
    {
        return match ($dataType) {
            'Foundation' => 0,
            'SR Legacy' => 1,
            'Survey (FNDDS)' => 2,
            'Experimental' => 3,
            'Branded' => 10,
            default => 5,
        };
    }

    /**
     * @param  array<int, int>  $fdcIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchUsdaFoodsByFdcIds(array $fdcIds): array
    {
        return [];
    }

    /**
     * Match USDA generic-food tables: NDB, description, publication date, SR/Foundation category, data type.
     *
     * @param  array<string, mixed>  $food
     * @param  array<string, mixed>|null  $detail
     */
    private function formatUsdaNutritionProfileLabel(array $food, ?array $detail): string
    {
        $fdcId = isset($food['fdcId']) ? (int) $food['fdcId'] : 0;
        $description = is_string($food['description'] ?? null) ? trim($food['description']) : '';

        if ($description === '') {
            $description = 'FDC '.$fdcId;
        }

        $ndbRaw = $food['ndbNumber'] ?? (is_array($detail) ? ($detail['ndbNumber'] ?? null) : null);
        $ndb = is_string($ndbRaw) ? trim($ndbRaw) : (is_numeric($ndbRaw) ? (string) $ndbRaw : '');

        $pubRaw = $food['publicationDate'] ?? (is_array($detail) ? ($detail['publicationDate'] ?? null) : null);
        $publication = is_string($pubRaw) ? trim($pubRaw) : '';

        $category = '';

        if (is_array($detail) && isset($detail['foodCategory']) && is_array($detail['foodCategory'])) {
            $cat = $detail['foodCategory']['description'] ?? null;
            $category = is_string($cat) ? trim($cat) : '';
        }

        $dataType = is_string($food['dataType'] ?? null) ? trim($food['dataType']) : '';

        $parts = [];

        if ($ndb !== '') {
            $parts[] = 'NDB '.$ndb;
        }

        $parts[] = $description;

        if ($publication !== '') {
            $parts[] = $publication;
        }

        if ($category !== '') {
            $parts[] = $category;
        }

        if ($dataType !== '') {
            $parts[] = $dataType;
        }

        return implode(' — ', $parts);
    }

    /**
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float>}|null
     */
    private function fetchFromUsda(string $ingredientName): ?array
    {
        return null;
    }

    /**
     * Legacy FoodData Central search (GET) when the analysis resolver has no match — keeps herbs and custom labels enrichable in tests and production.
     */
    private function fetchFromUsdaLegacyGetSearch(string $ingredientName): ?array
    {
        return null;
    }

    /**
     * @return array{calories: float, protein: float, carbs: float, fat: float, micronutrients: array<string, float>}|null
     */
    private function fetchFromCalorieKing(string $ingredientName): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function displayNameFromCalorieKingFoodItem(array $item): ?string
    {
        foreach (['name', 'food_name', 'title', 'product_name', 'label'] as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $nested = $item['food'] ?? null;

        if (is_array($nested)) {
            return $this->displayNameFromCalorieKingFoodItem($nested);
        }

        return null;
    }

    /**
     * Cached food names from CalorieKing search (for lookup dropdown only).
     *
     * @return array<int, string>
     */
    private function calorieKingLookupNameSuggestions(string $searchQuery): array
    {
        return [];
    }

    private function normalizeSuggestionLabel(string $label): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $label));
    }

    /**
     * @return array<int, string>
     */
    private function tokenizeSuggestionWords(string $text): array
    {
        $stopWords = ['of', 'and', 'or', 'the', 'a', 'an', 'with', 'per', 'for', 'to', 'in', 'on', 'as', 'by'];

        return collect(preg_split('/\s+/', Str::lower($text)) ?: [])
            ->map(fn (string $w): string => (string) preg_replace('/[^a-z0-9]+/', '', $w))
            ->filter(fn (string $w): bool => $w !== '' && mb_strlen($w) >= 2 && ! in_array($w, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keep remote API labels that share at least one meaningful token with the ingredient (drops unrelated brands).
     *
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    private function filterRemoteLookupLabelsByContext(string $ingredientName, array $labels): array
    {
        if ($labels === []) {
            return [];
        }

        $baseName = trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $ingredientName));
        $words = $this->tokenizeSuggestionWords($baseName.' '.Str::before($ingredientName, ','));

        if ($words === []) {
            return $labels;
        }

        $out = [];

        foreach ($labels as $label) {
            $labelWords = $this->tokenizeSuggestionWords($label);

            foreach ($words as $word) {
                if (in_array($word, $labelWords, true)) {
                    $out[] = $label;

                    continue 2;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function displayNameFromOpenFoodFactsProduct(array $product): ?string
    {
        foreach (['product_name', 'product_name_en', 'generic_name', 'abbreviated_product_name'] as $key) {
            $value = $product[$key] ?? null;

            if (is_string($value)) {
                $label = $this->normalizeSuggestionLabel($value);

                if ($label !== '') {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * Cached product titles from Open Food Facts (for API lookup datalist — e.g. coconut milk vs water vs dried).
     *
     * @return array<int, string>
     */
    private function openFoodFactsLookupNameSuggestions(string $searchQuery): array
    {
        return [];
    }

    /**
     * Remove shorter labels when another suggestion is the same phrase continued (e.g. drop "chia" if "chia seeds" exists).
     *
     * @param  array<int, string>  $ordered
     * @return array<int, string>
     */
    /**
     * Common retail / cooking forms for popular ingredients (offline hints before API names).
     *
     * @return array<int, string>
     */
    private function typicalIngredientVariantPhrases(string $ingredientName): array
    {
        $base = Str::lower(trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $ingredientName)));
        $base = (string) preg_replace('/[^a-z0-9 ]+/', '', $base);
        $base = trim(Str::squish($base));

        return match ($base) {
            'coconut', 'coconuts' => [
                'Coconut fresh raw',
                'Coconut dried',
                'Coconut desiccated',
                'Coconut milk',
                'Coconut cream',
                'Coconut water',
            ],
            default => [],
        };
    }

    private function prunePrefixRedundantSuggestions(array $ordered): array
    {
        if (count($ordered) <= 1) {
            return $ordered;
        }

        $lowered = array_map(static fn (string $s): string => Str::lower($s), $ordered);

        $result = [];

        foreach ($ordered as $index => $s) {
            $sLower = $lowered[$index];
            $drop = false;

            foreach ($lowered as $j => $tLower) {
                if ($index === $j) {
                    continue;
                }

                if (mb_strlen($tLower) > mb_strlen($sLower) && str_starts_with($tLower, $sLower.' ')) {
                    $drop = true;

                    break;
                }
            }

            if (! $drop) {
                $result[] = $s;
            }
        }

        return $result;
    }

    /**
     * Datalist + Livewire re-renders: local aliases and variants only (no HTTP) so routine updates stay under PHP max_execution_time.
     *
     * @return array<int, string>
     */
    public function lookupSuggestionsForDatalist(string $ingredientName): array
    {
        $trimmed = trim($ingredientName);

        if ($trimmed === '') {
            return [];
        }

        $ctx = $this->lookupSuggestionLocalContext($trimmed);
        $seen = [];
        $ordered = [];

        foreach ($ctx['localOrdered'] as $label) {
            if ($label === '') {
                continue;
            }

            $key = Str::lower($label);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $ordered[] = $label;
        }

        return $this->prunePrefixRedundantSuggestions($ordered);
    }

    /**
     * Full suggestions (Open Food Facts + CalorieKing when configured), cached to avoid repeat external calls.
     *
     * @return array<int, string>
     */
    public function lookupSuggestionsFor(string $ingredientName): array
    {
        return $this->lookupSuggestionsForDatalist($ingredientName);
    }

    /**
     * @return array{baseName: string, localOrdered: \Illuminate\Support\Collection<int, string>}
     */
    private function lookupSuggestionLocalContext(string $ingredientName): array
    {
        $normalized = Str::of($ingredientName)
            ->lower()
            ->replaceMatches('/[^a-z0-9 ]+/', ' ')
            ->squish()
            ->value();

        $baseName = trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $ingredientName));
        $beforeComma = trim(Str::before($ingredientName, ','));
        $firstWord = trim((string) Str::before($normalized, ' '));
        $parentheticalParts = [];

        preg_match_all('/\(([^)]*)\)/', $ingredientName, $matches);

        if (isset($matches[1]) && is_array($matches[1])) {
            $parts = $matches[1];
            $parentheticalParts = collect($parts)
                ->flatMap(function (string $segment): array {
                    return preg_split('/[,\/]/', $segment) ?: [];
                })
                ->map(fn ($part): string => trim($part))
                ->filter()
                ->values()
                ->all();
        }

        $localParts = collect([$ingredientName, $baseName]);

        if ($beforeComma !== '' && Str::lower($beforeComma) !== Str::lower(trim($ingredientName))) {
            $localParts->push($beforeComma);
        }

        if (mb_strlen($firstWord) >= 2) {
            $localParts->push($firstWord);
        }

        $localParts->push($this->resolveAlias($ingredientName));
        $localParts->push($this->resolveAlias($baseName));

        $localParts = $localParts->merge($this->typicalIngredientVariantPhrases($ingredientName));

        $localOrdered = $localParts->merge(
            collect($parentheticalParts)->flatMap(function (string $part) use ($baseName): array {
                $part = trim($part);

                if ($part === '') {
                    return [];
                }

                $combined = trim("{$baseName} {$part}");

                if (mb_strlen($part) < 2) {
                    return $combined !== '' ? [$combined] : [];
                }

                return array_values(array_filter([$part, $combined]));
            })
        )
            ->map(fn ($term): string => $this->normalizeSuggestionLabel((string) $term))
            ->filter();

        return [
            'baseName' => $baseName,
            'localOrdered' => $localOrdered,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function lookupSuggestionsForUncached(string $ingredientName): array
    {
        return $this->lookupSuggestionsForDatalist($ingredientName);
    }


    /**
     * @return \Illuminate\Support\Collection<int, Ingredient>
     */
    public function getIngredientsProperty()
    {
        // Smart Kitchen sorting: keep "in stock" (verified) at top.
        $query = Ingredient::query()
            ->orderByDesc('is_verified')
            ->orderBy('name');

        if (trim($this->libraryTableSearch) !== '') {
            $term = '%'.addcslashes(trim($this->libraryTableSearch), '%_\\').'%';
            $query->where('name', 'like', $term);
        }

        return $query->get();
    }

}; ?>

<section class="w-full space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Ingredients — Smart Kitchen') }}</flux:heading>
            <flux:text class="text-neutral-600 dark:text-neutral-300">
                {{ __('Smart Kitchen ingredient library — local-only (CSV import + manual entry). Search filters the table below.') }}
            </flux:text>
        </div>
        <flux:badge>{{ __('Meal Craft') }}</flux:badge>
    </div>

    <x-mc-input
        wire:model.live.debounce.300ms="libraryTableSearch"
        type="search"
        :label="__('Search saved ingredients')"
        placeholder="{{ __('Filter by name…') }}"
        class="max-w-xl"
        autocomplete="off"
    />

    <details
        id="ingredients-advanced"
        class="group scroll-mt-6 rounded-xl border border-neutral-200 bg-white open:shadow-sm dark:border-neutral-700 dark:bg-neutral-900"
    >
        <summary
            class="cursor-pointer list-none px-6 py-4 font-semibold text-neutral-800 marker:content-none dark:text-neutral-100 [&::-webkit-details-marker]:hidden"
        >
            <span class="flex flex-wrap items-center justify-between gap-2">
                <span>{{ __('Quick Add & CSV Import') }}</span>
                <span class="text-xs font-normal text-neutral-500">{{ __('Tap to expand or collapse') }}</span>
            </span>
        </summary>
        <div class="space-y-6 border-t border-neutral-200 px-6 pb-6 pt-4 dark:border-neutral-700">
            <div class="mt-2 rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Bulk import ingredients (CSV)') }}</flux:heading>
                <div class="mt-3 flex items-end gap-3">
                    <flux:input wire:model="importCsvFile" type="file" accept=".csv,.txt" />
                    <flux:button type="button" wire:click="importCsv" variant="subtle">{{ __('Upload CSV') }}</flux:button>
                </div>
                <flux:text class="mt-2 text-xs text-neutral-500">
                    {{ __('This is the primary way to load your library quickly. Download the template for the full nutrient columns.') }}
                </flux:text>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button type="button" wire:click="downloadCsvTemplate" variant="ghost" size="sm">
                        {{ __('Download CSV Template') }}
                    </flux:button>
                    <flux:button type="button" wire:click="exportCsv" variant="ghost" size="sm">
                        {{ __('Export CSV') }}
                    </flux:button>
                </div>
            </div>

            <div id="ingredient-editor-panel" class="scroll-mt-6 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        @if ($this->editingIngredientId)
            <flux:callout color="blue" class="mb-4">
                {{ __('You are editing this ingredient. Update the fields below and save.') }}
            </flux:callout>
        @endif
        <div class="grid gap-4 md:grid-cols-2">
            <x-mc-input wire:model.blur="name" :label="__('Ingredient Name')" type="text" class="max-w-none" />
            <x-mc-dropdown-textinput
                wire:key="ingredient-category-{{ $this->category }}"
                class="max-w-none"
                :label="__('Ingredient Category')"
                :value="$this->category"
                wireModel="category"
                :options="['Proteins', 'Fats', 'Grains', 'Vegetables', 'Fruits', 'Dairy', 'Legumes', 'Spices', 'Pantry', 'Liquids', 'Other']"
            />

            <x-mc-input wire:model="calories" :label="__('Calories (per 100g)')" type="number" step="0.01" min="0" class="max-w-none" />
            <x-mc-input wire:model="protein" :label="__('Protein (g)')" type="number" step="0.01" min="0" class="max-w-none" />
            <x-mc-input wire:model="carbs" :label="__('Carbs (g)')" type="number" step="0.01" min="0" class="max-w-none" />
            <x-mc-input wire:model="fat" :label="__('Fat (g)')" type="number" step="0.01" min="0" class="max-w-none" />
        </div>

        <div class="mt-5">
            <x-mc-textarea wire:model="micronutrients" :label="__('Micronutrients JSON')" rows="5" class="max-w-none" />
            <flux:text class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                {{ __('Tip: include keys like vitamin_b9 (folate), vitamin_b12, magnesium, iron, zinc so SC Highlights can light up automatically.') }}
            </flux:text>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <flux:button type="button" wire:click="saveIngredient" variant="primary" class="!rounded-[12px] !bg-[#5A6B44] !text-white hover:!bg-[#4F5F3D] active:!bg-[#3E4F28]">
                {{ $this->editingIngredientId ? __('Update Ingredient') : __('Add Ingredient') }}
            </flux:button>
            @if ($this->editingIngredientId)
                <flux:button type="button" wire:click="cancelIngredientEditor" variant="ghost">
                    {{ __('Cancel editing') }}
                </flux:button>
            @endif
            @if ($status)
                <flux:text class="font-medium !text-green-700 !dark:text-green-400">{{ __($status) }}</flux:text>
            @endif
            @if ($error)
                <flux:text class="font-medium !text-red-700 !dark:text-red-400">{{ __($error) }}</flux:text>
            @endif
        </div>

        <!-- Bulk import moved to top of this panel -->
    </div>

    
        </div>
    </details>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <flux:heading size="lg">{{ __('Ingredients Manager') }}</flux:heading>

        @php
            $libraryHighlights = [
                'powerfoods' => 0,
                'folateHeroes' => 0,
            ];

            foreach ($this->ingredients as $i) {
                $h = $i->highlights;
                $count = (int) collect($h)->filter()->count();

                if ($count >= 2) {
                    $libraryHighlights['powerfoods']++;
                }

                if (($h['folate'] ?? false) === true) {
                    $libraryHighlights['folateHeroes']++;
                }
            }
        @endphp

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Library Health') }}</flux:text>
                <div class="mt-2 flex items-baseline justify-between gap-3">
                    <flux:text class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Total Powerfoods') }}</flux:text>
                    <flux:badge color="green" size="sm">{{ $libraryHighlights['powerfoods'] }}</flux:badge>
                </div>
                <flux:text class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('2+ SC highlights per ingredient') }}
                </flux:text>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Library Health') }}</flux:text>
                <div class="mt-2 flex items-baseline justify-between gap-3">
                    <flux:text class="font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Folate Heroes') }}</flux:text>
                    <flux:badge color="green" size="sm">{{ $libraryHighlights['folateHeroes'] }}</flux:badge>
                </div>
                <flux:text class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ __('Folate > 100 mcg per 100 g') }}
                </flux:text>
            </div>
        </div>
        <div class="mt-6 flex items-center justify-between gap-3">
            <div class="text-sm text-neutral-600 dark:text-neutral-300">
                @if (count($selectedIngredients) > 0)
                    {{ __(':n selected', ['n' => count($selectedIngredients)]) }}
                @endif
            </div>
            @if (count($selectedIngredients) > 0)
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="text-sm font-medium text-neutral-600 underline underline-offset-4 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white"
                    >
                        {{ __('Clear Selection') }}
                    </button>
                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="deleteSelected"
                        wire:confirm="{{ __('Delete the selected ingredients? This cannot be undone.') }}"
                    >
                        {{ __('Delete Selected') }}
                    </flux:button>
                </div>
            @endif
        </div>
        <div class="relative mt-4 max-h-[600px] overflow-x-auto overflow-y-auto rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] shadow-sm">
            <table class="meal-craft-data-table min-w-full text-sm">
                <thead class="sticky top-0 z-20 text-left">
                    <tr class="border-b border-[#E5E7EB] bg-[#F8F9F6]">
                        <th class="sticky left-0 z-30 w-12 bg-[#F8F9F6] px-3 py-2.5 align-middle font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">
                            <flux:checkbox
                                wire:model.live="selectAll"
                                :label="__('Select all')"
                            />
                        </th>
                        <th class="sticky left-12 z-30 border-r border-[#E5E7EB] bg-[#F8F9F6] px-3 py-2.5 font-montserrat text-sm font-bold text-[#5A6B44] shadow-sm transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">
                            {{ __('Ingredient') }}
                        </th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Status') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('FDC') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('SC Highlights') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Calories') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Protein') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Carbs') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Fat') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Vitamin A') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('B6') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('B9 Folate') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('B12') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Vitamin C') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Vitamin D') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Vitamin E') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Fiber') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Calcium') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Iron') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Magnesium') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Potassium') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Zinc (mg)') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Sodium (mg)') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Sugar (g)') }}</th>
                        <th class="bg-[#F8F9F6] px-3 py-2.5 text-right font-montserrat text-sm font-bold text-[#5A6B44] transition-colors duration-150 hover:bg-[#F0F1ED] hover:text-[#4F5F3D]">{{ __('Vitamin K2 (mcg)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->ingredients as $ingredient)
                        @php
                            $isInStock = (bool) $ingredient->is_verified;
                            $rowMuted = $isInStock ? '' : 'text-[#71717a]';
                            $rowStrike = $isInStock ? '' : 'line-through';
                        @endphp
                        <tr wire:key="ingredient-{{ $ingredient->id }}" class="border-b border-neutral-100 dark:border-neutral-800">
                            <td class="sticky left-0 z-10 px-3 py-2 align-top">
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-600 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100"
                                    wire:model.live="selectedIngredients"
                                    value="{{ $ingredient->id }}"
                                    aria-label="{{ __('Select :name', ['name' => $ingredient->name]) }}"
                                />
                            </td>
                            <td class="sticky left-12 z-10 border-r border-neutral-200 px-3 py-2 shadow-sm dark:border-neutral-800">
                                <div class="space-y-2">
                                    <flux:text class="font-medium whitespace-nowrap font-montserrat {{ $rowMuted }} {{ $rowStrike }}">{{ $ingredient->name }}</flux:text>
                                    <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ __('Use Advanced (above) for manual edits.') }}
                                    </flux:text>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button type="button" wire:click="editIngredient({{ $ingredient->id }})" size="xs" variant="subtle">{{ __('Edit in form above') }}</flux:button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 font-montserrat text-xs font-bold {{ $isInStock ? 'text-[#5A6B44]' : 'text-[#71717a]' }}">
                                {{ $isInStock ? __('In Stock') : __('Out of Stock') }}
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $ingredient->fdc_id ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $h = $ingredient->highlights;
                                @endphp

                                <div class="flex flex-wrap gap-1">
                                    @if (($h['folate'] ?? false) === true)
                                        <flux:badge color="green" size="sm">{{ __('Folate') }}</flux:badge>
                                    @endif
                                    @if (($h['b12'] ?? false) === true)
                                        <flux:badge color="blue" size="sm">{{ __('B12') }}</flux:badge>
                                    @endif
                                    @if (($h['magnesium'] ?? false) === true)
                                        <flux:badge color="purple" size="sm">{{ __('Magnesium') }}</flux:badge>
                                    @endif
                                    @if (($h['iron'] ?? false) === true)
                                        <flux:badge color="red" size="sm">{{ __('Iron') }}</flux:badge>
                                    @endif
                                    @if (($h['zinc'] ?? false) === true)
                                        <flux:badge color="orange" size="sm">{{ __('Zinc') }}</flux:badge>
                                    @endif

                                    @if (! in_array(true, $h, true))
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $ingredient->calories, 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $ingredient->protein, 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $ingredient->carbs, 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $ingredient->fat, 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['vitamin_a'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->b6 ?? 0), 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->b9_folate ?? 0), 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->b12 ?? 0), 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['vitamin_c'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['vitamin_d'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['vitamin_e'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['fiber'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['calcium'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->iron ?? 0), 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->magnesium ?? 0), 2) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['potassium'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['zinc'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['sodium'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['sugar'] ?? 0), 1) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($ingredient->micronutrients['vitamin_k2'] ?? 0), 1) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="25" class="px-3 py-6 text-center text-neutral-500">{{ __('No ingredients yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
