<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Ingredient;
use App\Services\RecipeNutritionCalculator;
use App\Services\UsdaFoodDetailClient;
use App\Support\IngredientLibraryCategory;
use App\Support\UsdaNutrientMath;
use App\Support\VitaminK2Resolver;
use Illuminate\Contracts\Console\Kernel;

$skipFdc = getenv('AUDIT_SKIP_FDC') === '1';

/** @return list<array{name: string, col: string, stored: float, computed: float, pct: float}> */
function auditBaseRecipes(): array
{
    $bases = Ingredient::query()
        ->whereIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
        ->with('components')
        ->orderBy('name')
        ->get();

    $mismatches = [];
    $noComponents = [];

    foreach ($bases as $base) {
        if ($base->components->isEmpty()) {
            $noComponents[] = $base->name;

            continue;
        }

        $computed = RecipeNutritionCalculator::per100gNutritionForIngredient($base);

        foreach (['calories', 'protein', 'carbs', 'fat', 'b6', 'b9_folate', 'b12', 'iron', 'magnesium'] as $col) {
            $stored = (float) $base->{$col};
            $calc = (float) ($computed[$col] ?? 0);
            $diff = abs($stored - $calc);
            $pct = $diff / max($stored, $calc, 0.01) * 100;

            if ($diff > 2 && $pct > 5) {
                $mismatches[] = [
                    'name' => $base->name,
                    'col' => $col,
                    'stored' => $stored,
                    'computed' => $calc,
                    'pct' => $pct,
                ];
            }
        }
    }

    echo "=== BASE RECIPES ===\n";
    echo 'Total: '.$bases->count().', no components: '.count($noComponents).', mismatches: '.count($mismatches)."\n";

    foreach ($noComponents as $name) {
        echo "  NO_COMP: {$name}\n";
    }

    foreach ($mismatches as $m) {
        echo sprintf(
            "  %s %s: stored=%.2f computed=%.2f (%.1f%%)\n",
            $m['name'],
            $m['col'],
            $m['stored'],
            $m['computed'],
            $m['pct'],
        );
    }

    return $mismatches;
}

/** @return list<array{name: string, field: string, stored: float, fdc: float, fdc_id: int}> */
function auditFdcLinkedIngredients(UsdaFoodDetailClient $client, float $tolerancePct = 15.0): array
{
    $issues = [];

    $ingredients = Ingredient::query()
        ->whereNotNull('fdc_id')
        ->where('fdc_id', '>', 0)
        ->whereNotIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
        ->orderBy('name')
        ->get();

    $checked = 0;
    $fetchFailed = 0;

    foreach ($ingredients as $ing) {
        $fdcId = (int) $ing->fdc_id;
        $map = $client->nutrientMapForFdcId($fdcId);

        if ($map === []) {
            $fetchFailed++;
            $issues[] = [
                'name' => $ing->name,
                'field' => 'fdc_fetch',
                'stored' => 0,
                'fdc' => 0,
                'fdc_id' => $fdcId,
            ];

            continue;
        }

        $checked++;

        $comparisons = [
            'calories' => UsdaNutrientMath::valueForNutrientKeys($map, '1008', '208'),
            'protein' => UsdaNutrientMath::valueForNutrientKeys($map, '1003', '203'),
            'carbs' => UsdaNutrientMath::valueForNutrientKeys($map, '1005', '205'),
            'fat' => UsdaNutrientMath::valueForNutrientKeys($map, '1004', '204'),
            'b6' => UsdaNutrientMath::valueForNutrientKeys($map, UsdaNutrientMath::FDC_VITAMIN_B6, '415'),
            'b9_folate' => UsdaNutrientMath::valueForNutrientKeys($map, UsdaNutrientMath::FDC_FOLATE, '417'),
            'b12' => UsdaNutrientMath::valueForNutrientKeys($map, UsdaNutrientMath::FDC_VITAMIN_B12, '418'),
            'iron' => UsdaNutrientMath::valueForNutrientKeys($map, UsdaNutrientMath::FDC_IRON, '303'),
            'magnesium' => UsdaNutrientMath::valueForNutrientKeys($map, UsdaNutrientMath::FDC_MAGNESIUM, '304'),
        ];

        foreach ($comparisons as $field => $fdcValue) {
            $stored = (float) $ing->{$field};

            if ($fdcValue <= 0 && $stored <= 0) {
                continue;
            }

            $diff = abs($stored - $fdcValue);
            $pct = $diff / max($stored, $fdcValue, 0.01) * 100;

            if ($pct > $tolerancePct && $diff > 0.5) {
                $issues[] = [
                    'name' => $ing->name,
                    'field' => $field,
                    'stored' => $stored,
                    'fdc' => $fdcValue,
                    'fdc_id' => $fdcId,
                ];
            }
        }

        $micros = is_array($ing->micronutrients) ? $ing->micronutrients : [];
        $storedK2 = (float) ($micros['vitamin_k2'] ?? 0);
        $expectedK2 = VitaminK2Resolver::resolve(
            $ing->name,
            (string) ($ing->usda_food_category ?? ''),
            $map,
        );

        if ($storedK2 !== $expectedK2 && abs($storedK2 - $expectedK2) > 0.5) {
            $issues[] = [
                'name' => $ing->name,
                'field' => 'vitamin_k2',
                'stored' => $storedK2,
                'fdc' => $expectedK2,
                'fdc_id' => $fdcId,
            ];
        }
    }

    echo "\n=== FDC LINKED INGREDIENTS ===\n";
    echo "Total with fdc_id: {$ingredients->count()}, fetched: {$checked}, fetch failed: {$fetchFailed}\n";
    echo 'Field mismatches (>15%): '.count($issues)."\n";

    foreach (array_slice($issues, 0, 40) as $issue) {
        if ($issue['field'] === 'fdc_fetch') {
            echo "  {$issue['name']}: FDC {$issue['fdc_id']} fetch failed\n";

            continue;
        }

        echo sprintf(
            "  %s %s: stored=%.2f expected=%.2f (fdc %d)\n",
            $issue['name'],
            $issue['field'],
            $issue['stored'],
            $issue['fdc'],
            $issue['fdc_id'],
        );
    }

    if (count($issues) > 40) {
        echo '  ... +'.(count($issues) - 40)." more\n";
    }

    return $issues;
}

auditBaseRecipes();

if (! $skipFdc) {
    auditFdcLinkedIngredients(app(UsdaFoodDetailClient::class));
}
