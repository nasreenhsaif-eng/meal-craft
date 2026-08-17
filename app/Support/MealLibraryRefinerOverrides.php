<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Version-controlled refiner recipe snapshots written from Meal Library UI saves.
 */
final class MealLibraryRefinerOverrides
{
    public const RELATIVE_PATH = 'data/menu/library_refiner_overrides.php';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return [];
        }

        $data = require $path;

        return is_array($data) ? $data : [];
    }

    public static function has(string $mealName): bool
    {
        return array_key_exists($mealName, self::all());
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function put(string $mealName, array $snapshot): void
    {
        $mealName = trim($mealName);
        if ($mealName === '') {
            return;
        }

        $overrides = self::all();
        $overrides[$mealName] = $snapshot;
        ksort($overrides, SORT_NATURAL | SORT_FLAG_CASE);

        self::write($overrides);
    }

    public static function forget(string $mealName): void
    {
        $mealName = trim($mealName);
        if ($mealName === '') {
            return;
        }

        $overrides = self::all();
        if (! array_key_exists($mealName, $overrides)) {
            return;
        }

        unset($overrides[$mealName]);
        self::write($overrides);
    }

    /**
     * @param  list<string>  $mealNames
     */
    public static function forgetMany(array $mealNames): void
    {
        $overrides = self::all();
        $changed = false;

        foreach ($mealNames as $mealName) {
            $mealName = trim($mealName);
            if ($mealName === '' || ! array_key_exists($mealName, $overrides)) {
                continue;
            }

            unset($overrides[$mealName]);
            $changed = true;
        }

        if ($changed) {
            self::write($overrides);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, array<string, mixed>>
     */
    public static function mergeRecipeDefinitionMap(array $definitions): array
    {
        foreach (self::all() as $mealName => $override) {
            if (! isset($definitions[$mealName]) || ! is_array($override)) {
                continue;
            }

            $definitions[$mealName] = self::applyRecipeOverride($definitions[$mealName], $override);
        }

        return $definitions;
    }

    /**
     * @param  array<string, string>  $definitions
     * @return array<string, string>
     */
    public static function mergeInstructionDefinitionMap(array $definitions): array
    {
        foreach (self::all() as $mealName => $override) {
            if (! is_array($override)) {
                continue;
            }

            $instructions = $override['instructions'] ?? null;
            if (! is_string($instructions) || trim($instructions) === '') {
                continue;
            }

            if (isset($definitions[$mealName])) {
                $definitions[$mealName] = trim($instructions);
            }
        }

        return $definitions;
    }

    public static function path(): string
    {
        $configured = config('menu-development.refiner_overrides_path');

        if (is_string($configured) && $configured !== '') {
            return self::resolvePath($configured);
        }

        return database_path(self::RELATIVE_PATH);
    }

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     */
    private static function write(array $overrides): void
    {
        $path = self::path();
        $directory = dirname($path);

        if ($directory !== '' && ! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Could not create directory: {$directory}");
        }

        $temporaryPath = $path.'.tmp';
        $export = var_export($overrides, true);
        $contents = "<?php\n\ndeclare(strict_types=1);\n\n"
            ."/**\n * Auto-generated when meals are saved in the Meal Library UI.\n *\n"
            ." * Refiners merge these snapshots over their built-in recipe definitions so UI corrections\n"
            ." * are preserved in git and survive db:seed / configure commands.\n *\n"
            ." * @see \\App\\Services\\MealLibraryRefinerSourceSync\n */\n\n"
            .'return '.$export.";\n";

        if (file_put_contents($temporaryPath, $contents) === false) {
            throw new InvalidArgumentException("Could not write temporary refiner overrides: {$temporaryPath}");
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new InvalidArgumentException("Could not write refiner overrides: {$path}");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function applyRecipeOverride(array $definition, array $override): array
    {
        if (isset($override['ingredients']) && is_array($override['ingredients']) && $override['ingredients'] !== []) {
            if (isset($definition['salad_ingredients']) || isset($definition['dressing_ingredients'])) {
                $dressingKeys = array_fill_keys(array_keys($definition['dressing_ingredients'] ?? []), true);
                $salad = [];
                $dressing = [];

                foreach ($override['ingredients'] as $ingredientName => $grams) {
                    if (! is_string($ingredientName) || ! is_numeric($grams)) {
                        continue;
                    }

                    $name = trim($ingredientName);
                    $amount = round((float) $grams, 4);
                    if ($name === '' || $amount <= 0) {
                        continue;
                    }

                    if (isset($dressingKeys[$name]) || str_ends_with($name, ' Dressing (Base)')) {
                        $dressing[$name] = $amount;
                    } else {
                        $salad[$name] = $amount;
                    }
                }

                if ($salad !== []) {
                    $definition['salad_ingredients'] = $salad;
                }

                if ($dressing !== []) {
                    $definition['dressing_ingredients'] = $dressing;
                }
            } else {
                $definition['ingredients'] = self::normalizeIngredientMap($override['ingredients']);
            }
        }

        foreach (['highlight', 'short_description'] as $key) {
            if (! array_key_exists($key, $override)) {
                continue;
            }

            $value = is_string($override[$key]) ? trim($override[$key]) : '';
            if ($value !== '') {
                $definition[$key] = $value;
            }
        }

        foreach (['diet_tags', 'food_filter_tags'] as $key) {
            if (! isset($override[$key]) || ! is_array($override[$key])) {
                continue;
            }

            /** @var list<string> $tags */
            $tags = array_values(array_filter(array_map(
                static fn (mixed $tag): string => is_string($tag) ? trim($tag) : '',
                $override[$key],
            )));

            if ($tags !== []) {
                $definition[$key] = $tags;
            }
        }

        if (isset($override['instructions']) && is_string($override['instructions']) && trim($override['instructions']) !== '') {
            $lines = MealInstructionsText::linesFromRaw($override['instructions']);

            if (isset($definition['instructions']) && is_array($definition['instructions'])) {
                $definition['instructions'] = $lines;
            }

            if (isset($definition['salad_instructions']) && is_array($definition['salad_instructions'])) {
                $definition['salad_instructions'] = $lines;
            }
        }

        return $definition;
    }

    /**
     * @param  array<mixed, mixed>  $ingredients
     * @return array<string, float>
     */
    private static function normalizeIngredientMap(array $ingredients): array
    {
        $normalized = [];

        foreach ($ingredients as $ingredientName => $grams) {
            if (! is_string($ingredientName) || ! is_numeric($grams)) {
                continue;
            }

            $name = trim($ingredientName);
            $amount = round((float) $grams, 4);

            if ($name === '' || $amount <= 0) {
                continue;
            }

            $normalized[$name] = $amount;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    private static function resolvePath(string $path): string
    {
        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return base_path($path);
    }
}
