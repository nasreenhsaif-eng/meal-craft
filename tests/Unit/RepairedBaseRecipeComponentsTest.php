<?php

use App\Support\MenuDevelopmentCsv;

test('repaired base recipes declare instruction-aligned components in master csv', function (): void {
    $path = MenuDevelopmentCsv::ingredientsPath();
    $handle = fopen($path, 'r');
    expect($handle)->not->toBeFalse();

    $header = fgetcsv($handle);
    expect($header)->toBe(MenuDevelopmentCsv::INGREDIENT_HEADERS);

    $index = array_flip($header);
    $rowsByName = [];

    while (($row = fgetcsv($handle)) !== false) {
        $name = trim((string) ($row[$index['name']] ?? ''));
        if ($name !== '') {
            $rowsByName[$name] = $row;
        }
    }

    fclose($handle);

    $expectations = [
        'Cooked Cannellini Beans (Base)' => [
            'must_include' => ['Cannellini Beans'],
            'must_exclude' => ['Napa Cabbage'],
        ],
        'Cooked Couscous (Base)' => [
            'must_include' => ['Couscous'],
            'must_exclude' => ['Garlic & Herb Marinade (Base)'],
        ],
        'Harissa Paste (Base)' => [
            'must_include' => ['Bell Pepper (Red)', 'Cumin Seeds', 'Garlic (Raw)', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Black Beans', 'Basmati Rice (White)'],
        ],
        'Ras El Hanout (Base)' => [
            'must_include' => ['Ground Ginger', 'Green Cardamom', 'coriander powder', 'Turmeric'],
            'must_exclude' => ['Sardines (Canned)', 'Watermelon', 'Grapefruit Sections'],
        ],
        'Tandoori Spice Mix (Base)' => [
            'must_include' => ['Brown Cardamom', 'Kashmiri Chili Powder', 'Green Papaya Juice', 'Black Tamarind (Dried)'],
            'must_exclude' => ['Strawberries', "Sumac Za'atar Dressing (Base)", 'Sriracha'],
        ],
        'Garlic & Herb Marinade (Base)' => [
            'must_include' => ['Olive Oil (Extra Virgin)', 'Lime Juice', 'Garlic (Raw)'],
            'must_exclude' => ['French Lentils', 'Almond Flour'],
        ],
        'Red Pepper Dressing (Base)' => [
            'must_include' => ['Bell Pepper (Red)', 'Apple Cider Vinegar', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Black Beans', 'Lemon Juice'],
        ],
        'Creamy Cajun Sauce (Base)' => [
            'must_include' => ['Coconut Cream', 'Fire Roasted Tomatoes (Base)', 'Blackened Seasoning (Base)'],
            'must_exclude' => ['Black Beans', 'Thai basil'],
        ],
        'Creamy Cumin Hummus (Base)' => [
            'must_include' => ['Cooked Chickpeas (Base)', 'Tahini', 'cumin powder'],
            'must_exclude' => ['Black Pepper (100g)', 'Fresh Basil'],
        ],
        'Smashed White Beans (Base)' => [
            'must_include' => ['Cooked Cannellini Beans (Base)', 'Garlic (Raw)', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Napa Cabbage'],
        ],
        'Pickled Red Onion (Base)' => [
            'must_include' => ['Red Onion', 'Apple Cider Vinegar'],
            'must_exclude' => ['Black Beans'],
        ],
        'Italian Meatballs (Base)' => [
            'must_include' => ['Beef Chuck Roast', 'Eggs (Large)', 'Almond Flour'],
            'must_exclude' => ['Sardines (Canned)', 'Carom Seeds'],
        ],
        'Quinoa Bread (Base)' => [
            'must_include' => ['Quinoa Flour', 'Psyllium Husks', 'Flaxseeds'],
            'must_exclude' => ['Beef Ground', 'Lamb Chops'],
        ],
        'Zucchini Almond Bread (Base)' => [
            'must_include' => ['Almond Flour', 'Zucchini', 'Eggs (Large)', 'Ghee'],
            'must_exclude' => ['Lamb Chops', 'Cabbage (Purple)'],
        ],
        'Ratatouille (Base)' => [
            'must_include' => ['Zucchini', 'Eggplant', 'Harissa Paste (Base)'],
            'must_exclude' => ['Black Beans', 'Basmati Rice (White)', 'Mustard Oil'],
        ],
        'Red Thai Curry Paste (Base)' => [
            'must_include' => ['Galangal', 'Lemongrass', 'Fish Sauce', 'Coconut Milk'],
            'must_exclude' => ['Chicken thigh', 'Cassava Flour', 'Chia Pudding'],
        ],
        'Vegetable Broth (Base)' => [
            'must_include' => ['Carrots', 'Celery', 'White Onion', 'Water (Filtered)'],
            'must_exclude' => ['Protein Powder (Isolate)', 'Dates (Deglet)', 'Black Lentils'],
        ],
        'Cajun Spice (Base)' => [
            'must_include' => ['Paprika', 'Garlic Powder', 'Onion Powder', 'Cayenne Pepper'],
            'must_exclude' => ['Tuna (Canned)', 'Basmati Rice (White)', 'Sunflower Seeds'],
        ],
        'Sumac Za\'atar Dressing (Base)' => [
            'must_include' => ['Sumac', 'Za\'atar', 'Olive Oil (Extra Virgin)', 'Lemon Juice'],
            'must_exclude' => ['Mackerel', 'Panko / GF Crumbs'],
        ],
        'Pomegranate Sumac Sauce (Base)' => [
            'must_include' => ['Pomegranate Juice', 'Sumac', 'Tomato Paste', 'Garlic (Raw)'],
            'must_exclude' => ['Mackerel', 'Mint Coconut Chutney Dressing (Base)'],
        ],
        'Roasted Red Bell Peppers (Base)' => [
            'must_include' => ['Bell Pepper (Red)', 'Oregano', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Black Beans'],
        ],
        'Avocado-Mango Salsa (Base)' => [
            'must_include' => ['Avocado', 'Mango', 'Fresh Coriander', 'Lime Juice'],
            'must_exclude' => ['Black Beans', 'Jasmine Rice'],
        ],
        'Peanut Butter Dressing (Base)' => [
            'must_include' => ['Peanut Butter', 'Tamari Sauce', 'Sesame Oil', 'Date Syrup'],
            'must_exclude' => ['Kiwi', 'Grapefruit Sections', 'Artichokes'],
        ],
        'Thai Peanut Dressing (Base)' => [
            'must_include' => ['Peanut Butter', 'Lime Juice', 'Tamari Sauce'],
            'must_exclude' => ['Kiwi', 'Fresh Basil'],
        ],
        'Mexican Beans (Base)' => [
            'must_include' => ['Cooked Black Beans (Base)', 'cumin powder', 'Cocoa Powder', 'Tomato Sauce'],
            'must_exclude' => ['Turmeric Powder (400g)', 'Pumpkin Seeds'],
        ],
        'Roasted Cherry Tomato (Base)' => [
            'must_include' => ['Cherry Tomatoes', 'Oregano', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Beef Liver'],
        ],
        'Roasted Mixed Vegetables (Base)' => [
            'must_include' => ['Zucchini', 'Eggplant', 'Butternut Squash', 'Rosemary (Fresh)'],
            'must_exclude' => ['Date Syrup'],
        ],
        'Slaw (Base)' => [
            'must_include' => ['Cabbage (Purple)', 'Carrots', 'Red Wine Vinegar'],
            'must_exclude' => ['Brown Cardamom', 'Dates (Deglet)'],
        ],
        'Cashew Cream (Base)' => [
            'must_include' => ['Cashew Nuts', 'Water (Filtered)'],
            'must_exclude' => ['Eggplant'],
        ],
        'Spicy Green Chilli Sauce (Base)' => [
            'must_include' => ['Fresh Coriander', 'Garlic (Raw)', 'Olive Oil (Extra Virgin)', 'Red Chili'],
            'must_exclude' => ['French Lentils', 'Grapefruit Sections', 'Garam Masala'],
        ],
        'Mint Coconut Chutney Dressing (Base)' => [
            'must_include' => ['Fresh Mint', 'Coconut Milk', 'Lime Juice', 'Fresh Coriander'],
            'must_exclude' => ['Baking Powder', 'Quinoa (White)'],
        ],
        'Tahini Miso Garlic Ginger Rice Vinegar Dressing (Base)' => [
            'must_include' => ['Tahini', 'Rice Vinegar', 'Miso', 'Ground Ginger'],
            'must_exclude' => ['Eggplant', 'Grapefruit Sections', 'Chia Seeds'],
        ],
        'Zesty Lime Chili Salad Dressing (Base)' => [
            'must_include' => ['Lime Juice', 'Olive Oil (Extra Virgin)', 'Apple Cider Vinegar', 'Chili Flakes'],
            'must_exclude' => ['Classic Lemon Garlic Dressing (Base)'],
        ],
        'Apple Cider Beet Marinade (Base)' => [
            'must_include' => ['Apple Cider Vinegar', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Basmati Rice (White)', 'Fresh Parsley (100g)'],
        ],
        'Basil Pesto (House)' => [
            'must_include' => ['Fresh Basil', 'Garlic (Raw)', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Almond Flour', 'Pine Nuts'],
        ],
        'Blackened Seasoning (Base)' => [
            'must_include' => ['Smoked Paprika', 'Garlic Powder', 'Cayenne Pepper'],
            'must_exclude' => ['Cilantro Lime Dressing (Base)', 'Fennel Bulb'],
        ],
        'Cucumber Pickle (Base)' => [
            'must_include' => ['Cucumber', 'Rice Vinegar'],
            'must_exclude' => ['Grapefruit Sections', 'Black Beans'],
        ],
        'Curry Vinaigrette (Base)' => [
            'must_include' => ['Curry Powder', 'Lemon Juice', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Fresh Basil (25g)', 'Basmati Rice (White)'],
        ],
        'Eggplant Dip (Mutabal) (Base)' => [
            'must_include' => ['Eggplant', 'Tahini', 'Garlic (Raw)'],
            'must_exclude' => ['Basmati Rice (White)'],
        ],
        'Fermented Chimichurri (Base)' => [
            'must_include' => ['Fresh Parsley', 'Apple Cider Vinegar', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Black Lentils', 'Cooked Quinoa (Base)'],
        ],
        'Grapefruit Lime Dressing (Base)' => [
            'must_include' => ['Grapefruit Sections', 'Lime Juice', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Black Beans', 'Lemon Juice (12g)'],
        ],
        'Homemade Coconut Milk' => [
            'must_include' => ['Coconut Meat', 'Water (Filtered)'],
            'must_exclude' => ['Baking Powder', 'Basmati Rice (White)'],
        ],
        'Honey Mustard Dressing (Base)' => [
            'must_include' => ['Dijon Mustard', 'Date Syrup', 'Lemon Juice'],
            'must_exclude' => ['Fresh Basil (25g)', 'Dill (Fresh)'],
        ],
        'Mango Chutney Dressing' => [
            'must_include' => ['Mango', 'Apple Cider Vinegar', 'Ginger (Raw)'],
            'must_exclude' => ['Black Beans', 'Basmati Rice (White)'],
        ],
        'Miso Tahini Dressing (Base)' => [
            'must_include' => ['Miso', 'Tahini', 'Rice Vinegar'],
            'must_exclude' => ['Grapefruit Sections', 'Chia Seeds'],
        ],
        'Mustard Dressing (Base)' => [
            'must_include' => ['Dijon Mustard', 'Lemon Juice', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Fresh Basil (30g)'],
        ],
        'Rosemary Garlic Chicken (Base)' => [
            'must_include' => ['Chicken Breast', 'Rosemary (Fresh)', 'Garlic (Raw)'],
            'must_exclude' => ['Fire Roasted Tomatoes (Base)', 'Tahini'],
        ],
        'Sauteed Mint Basil Koosa (Base)' => [
            'must_include' => ['Zucchini', 'Fresh Mint', 'Fresh Basil'],
            'must_exclude' => ['Baking Powder', 'Black Lentils', 'Basmati Rice (White)'],
        ],
        'Tandoori Chicken (Base)' => [
            'must_include' => ['Chicken Breast', 'Tandoori Spice Mix (Base)', 'Coconut Milk'],
            'must_exclude' => ['Fire Roasted Tomatoes (Base) (500g)', 'Quinoa (White)'],
        ],
        'Tandoori Coconut Mint Dressing (Base)' => [
            'must_include' => ['Coconut Milk', 'Fresh Mint', 'Tandoori Spice Mix (Base)'],
            'must_exclude' => ['Baking Powder', 'Saffron Threads'],
        ],
        'Tandoori Lime Dressing (Base)' => [
            'must_include' => ['Lime Juice', 'Tandoori Spice Mix (Base)', 'Olive Oil (Extra Virgin)'],
            'must_exclude' => ['Saffron Threads', 'Fresh Basil (35g)'],
        ],
        'Tandoori Salmon (Base)' => [
            'must_include' => ['Salmon (Raw)', 'Tandoori Spice Mix (Base)', 'Coconut Milk'],
            'must_exclude' => ['Lemon-Tahini Dressing (Base)', 'Quinoa (White)'],
        ],
    ];

    foreach ($expectations as $baseName => $rules) {
        expect(isset($rowsByName[$baseName]))->toBeTrue("Missing base recipe row: {$baseName}");

        $componentsCell = trim((string) ($rowsByName[$baseName][$index['recipe_components']] ?? ''));
        expect($componentsCell)->not->toBe('', "{$baseName} must declare recipe_components.");

        foreach ($rules['must_include'] as $ingredientName) {
            expect($componentsCell)->toContain($ingredientName);
        }

        foreach ($rules['must_exclude'] as $ingredientName) {
            expect($componentsCell)->not->toContain($ingredientName);
        }
    }
});
