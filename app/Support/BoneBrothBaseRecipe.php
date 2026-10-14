<?php

namespace App\Support;

/**
 * Defatted beef bone broth base — 10 L finished yield / 20 × 500 ml cups.
 */
final class BoneBrothBaseRecipe
{
    public const NAME = 'Bone Broth (Base)';

    public const SERVINGS = 20;

    public const SERVING_GRAMS = 500.0;

    public const FINISHED_WEIGHT_GRAMS = 10000.0;

    /**
     * @var array<string, float>
     */
    public const COMPONENTS = [
        'Beef Leg Bones' => 6000.0,
        'Apple Cider Vinegar' => 97.5,
        'Water (Filtered)' => 15200.0,
        'White Onion' => 900.0,
        'Carrots' => 600.0,
        'Celery' => 300.0,
        'Loomi (Black Lime)' => 30.0,
        'Turmeric' => 15.0,
        'Cinnamon' => 10.0,
    ];

    public const DESCRIPTION = 'Fully defatted, strained beef bone broth from cracked leg bones with cinnamon, black lemon (loomi), and turmeric — batch yields 10 L (20 × 500 ml cups). Nutrition is for finished skimmed broth per 100 ml, not raw marrow or vegetable solids.';

    public const INSTRUCTIONS = 'Step 1: Roast the bones — Preheat oven to 200°C (400°F). Arrange 6000g cracked beef leg bones cut-side up across large rimmed baking sheets. Roast 35–45 minutes until deeply browned and fragrant. Discard rendered sheet-pan grease before adding bones to the pot.
Step 2: Setup & initial simmer — Place the roasted bones into a 30-liter stockpot (or divide evenly across two 16-liter pots). Add 15200g filtered water and 97.5g apple cider vinegar. Let stand at room temperature for 20 minutes so the acid can begin pulling minerals from the bone surfaces. Bring to a gentle boil, skim foamy impurities for 10 minutes, then drop to the lowest heat. Cover loosely with the lid slightly ajar and simmer gently at 85°C–90°C (185°F–195°F) for 20 to 24 hours.
Step 3: Infuse aromatics (final 4 hours) — Add 900g white onion (halved, skins on), 600g carrots, 300g celery, 30g punctured loomi, 10g cinnamon sticks, and 15g turmeric. Simmer on low for the final 4 hours.
Step 4: Strain & calibrate volume — Turn off the heat. Remove large bones with tongs, then pour the hot broth through a fine-mesh sieve lined with cheesecloth or a nut-milk bag into clean heat-safe vessels. Measure total extracted volume. Top off with hot filtered water up to exactly 10 liters if evaporation reduced it, and stir thoroughly.
Step 5: Defat (calorie lock) — Cool completely, then refrigerate 12 to 24 hours. Lift and scrape off 100% of the solid white tallow fat cap.
Step 6: Portion & store — Gently warm the amber collagen-dense gelatin only until liquefied, ladle into 20 containers at 500 ml each, and freeze or refrigerate.';

    public static function recipeComponentsString(): string
    {
        $parts = [];

        foreach (self::COMPONENTS as $name => $grams) {
            $formatted = fmod($grams, 1.0) === 0.0
                ? (string) (int) $grams
                : rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');
            $parts[] = "{$name} ({$formatted}g)";
        }

        sort($parts);

        return implode(' | ', $parts);
    }
}
