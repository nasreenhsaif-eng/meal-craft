<?php

namespace App\Services;

use App\Enums\MealLibraryKey;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;
use App\Support\MealLibraryRefinerOverrides;
use Illuminate\Support\Facades\DB;

/**
 * Replaces marketing-style or broken instructions with clear home-cooking steps
 * for every meal in the Balanced weekly rotation.
 */
final class BalancedMealInstructionRefiner
{
    /**
     * @return list<string>
     */
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $updated = [];
            $definitions = $this->instructionDefinitions();

            $saladDressingMeals = array_flip(SaladDressingMealRefiner::refinedMealNames());
            $chiaDessertMeals = array_flip(BalancedChiaDessertRecipeRefiner::refinedMealNames());
            $tandooriMeals = array_flip(BalancedTandooriMealRecipeRefiner::refinedMealNames());

            $scheduledNames = array_unique(array_merge(
                BalancedWeeklyRotationSchedule::allScheduledMealNames(),
                NutrientDenseWeeklyRotationSchedule::allScheduledMealNames(),
            ));

            foreach ($scheduledNames as $mealName) {
                if (isset($saladDressingMeals[$mealName]) || isset($chiaDessertMeals[$mealName]) || isset($tandooriMeals[$mealName])) {
                    continue;
                }

                $instructions = $definitions[$mealName] ?? null;

                if ($instructions === null) {
                    continue;
                }

                if ($this->applyInstructionsToLibraryMeals($mealName, $instructions, force: true)) {
                    $updated[] = $mealName;
                }
            }

            foreach ($definitions as $mealName => $instructions) {
                if (isset($saladDressingMeals[$mealName]) || isset($chiaDessertMeals[$mealName]) || isset($tandooriMeals[$mealName])) {
                    continue;
                }

                if (in_array($mealName, $updated, true)) {
                    continue;
                }

                if ($this->applyInstructionsToLibraryMeals($mealName, $instructions, force: false)) {
                    $updated[] = $mealName;
                }
            }

            return array_values(array_unique($updated));
        });
    }

    private function applyInstructionsToLibraryMeals(string $mealName, string $instructions, bool $force): bool
    {
        $applied = false;

        foreach ([MealLibraryKey::Classic, MealLibraryKey::Tiers] as $libraryKey) {
            /** @var Meal|null $meal */
            $meal = Meal::query()
                ->where('name', $mealName)
                ->where('library_key', $libraryKey)
                ->first();

            if ($meal === null) {
                continue;
            }

            if (MealLibraryEditGuard::shouldSkipMealInstructionRefinement($meal)) {
                continue;
            }

            if (! $force && ! MealInstructionsText::needsBackfill($meal->instructions, $meal->description)) {
                continue;
            }

            $meal->update([
                'instructions' => $instructions,
                'description' => $instructions,
            ]);
            $applied = true;
        }

        return $applied;
    }

    /**
     * @return array<string, string>
     */
    private function instructionDefinitions(): array
    {
        $definitions = [
            // Chia desserts
            'Blueberry Walnut Chia Pudding' => $this->steps([
                'Whisk chia seeds with coconut water and coconut milk in a jar.',
                'Fold in blueberries and chopped walnuts.',
                'Add cinnamon and torn mint. Stir well.',
                'Refrigerate at least 4 hours (or overnight) until thick.',
                'Stir before serving. Eat cold.',
            ]),
            'Mango Pumpkin Seed Chia Pudding' => $this->steps([
                'Mix chia seeds with coconut water and coconut milk until no clumps remain.',
                'Stir in diced mango and pumpkin seeds.',
                'Refrigerate 4 hours or overnight until set.',
                'Top with extra mango if you like. Serve chilled.',
            ]),
            'Spiced Crunch Chia Pudding' => $this->steps([
                'Combine chia seeds, coconut water, and coconut milk in a bowl.',
                'Add cinnamon and chopped nuts or seeds from the recipe.',
                'Stir every 5 minutes for 15 minutes, then refrigerate until thick.',
                'Serve cold.',
            ]),
            'Strawberry Almond Chia Pudding' => $this->steps([
                'Whisk chia seeds into coconut water and coconut milk.',
                'Fold in sliced strawberries and chopped almonds.',
                'Refrigerate at least 4 hours until pudding-like.',
                'Stir and serve cold.',
            ]),
            'Peach Pecan Chia Pudding' => $this->steps([
                'Mix chia seeds with coconut water and coconut milk.',
                'Add diced peach and chopped pecans.',
                'Refrigerate until thick (4+ hours).',
                'Serve chilled.',
            ]),
            'Raspberry Cacao Chia Pudding' => $this->steps([
                'Whisk chia seeds with coconut water and coconut milk.',
                'Stir in raspberries and a pinch of cacao if included.',
                'Refrigerate until set. Serve cold.',
            ]),
            'Cacao & Almond Chia' => $this->steps([
                'Combine chia seeds, coconut water, and coconut milk.',
                'Add chopped almonds and cacao. Mix well.',
                'Refrigerate 4+ hours. Stir before serving.',
            ]),

            // Egg breakfasts
            'Mediterranean Omelet' => $this->steps([
                'Beat eggs in a bowl.',
                'Heat olive oil in a non-stick pan over medium heat. Sauté diced pepper, tomato, and shallot for 3 minutes.',
                'Pour in eggs. Cook over medium-low heat until almost set.',
                'Add olives and avocado on one half. Fold omelet in half.',
                'Finish with fresh herbs. Serve warm.',
            ]),
            'Moroccan Meatballs' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Mix ground beef with garlic, grated onion, and Ras El Hanout (Base); roll into meatballs.',
                'Brown meatballs in olive oil until golden all over.',
                'Drizzle with pomegranate molasses and simmer briefly until glazed.',
                'Plate warm quinoa and top with glazed meatballs.',
                'Garnish with chopped parsley, toasted pine nuts, and pomegranate seeds.',
            ]),
            'Okra Beef Curry' => $this->steps([
                'Prepare Okra Beef Curry (Base) per base recipe instructions; keep hot.',
                'Prepare Steamed Basmati Rice (Base) per base recipe instructions.',
                'Portion beef, okra, and sauce separately from the stew.',
                'Plate rice, arrange beef and okra, and ladle sauce over.',
                'Serve with a lemon wedge and chopped fresh coriander.',
            ]),
            'Pan Seared Hamour' => $this->steps([
                'Prepare Roasted Mixed Vegetables (Base) and Steamed Basmati Rice (Base) per base recipe instructions.',
                'Season hamour with cumin seeds, garlic, lemon juice, and olive oil.',
                'Pan-sear hamour until golden and cooked through.',
                'Serve hamour over rice with roasted mixed vegetables on the side.',
            ]),
            'Gouda & Spinach Scramble' => $this->steps([
                'Heat half the grass-fed butter in a non-stick skillet over medium heat. Wilt spinach for 1 minute, then set aside.',
                'Dice gouda and melt it in the remaining butter over medium-low heat until just beginning to soften.',
                'Beat eggs, add to the skillet with a little more butter if the pan looks dry, and scramble gently until just set.',
                'Fold gouda and spinach through the eggs. Season with black pepper and serve warm.',
            ]),
            'Greek Yogurt & Parmesan Frittata' => $this->steps([
                'Heat oven to 180°C (350°F).',
                'Whisk eggs with Greek yogurt, salt, and pepper. Fold in spinach and diced pepper.',
                'Brush an oven-safe pan with olive oil, pour in the mixture, and bake 12–15 minutes until set.',
                'Finish with grated parmesan and serve warm from the pan.',
            ]),
            'Feta & Herb Open Omelet' => $this->steps([
                'Heat olive oil in a non-stick pan over medium heat. Sauté pepper and spinach until tender (2–3 min).',
                'Beat eggs with black pepper. Pour over the vegetables and cook until the bottom is set.',
                'Crumble feta and dill over the top. Fold one side over and slide onto a plate.',
            ]),
            'Brie & Mushroom Skillet Eggs' => $this->steps([
                'Heat olive oil in a skillet over medium heat. Sauté onion and mushrooms until golden (5–6 min).',
                'Add thyme. Make small wells and crack in eggs. Cover and cook on low until whites are set.',
                'Top with brie slices, cover briefly to melt, and season with black pepper. Serve from the skillet.',
            ]),
            'Parmesan Shakshuka' => $this->steps([
                'Heat olive oil in a skillet. Sauté onion, pepper, and garlic until softened (5 min).',
                'Add crushed tomato and smoked paprika. Simmer 8–10 minutes until saucy.',
                'Make wells in the sauce, crack in eggs, cover, and cook on low until whites are set (5–7 min).',
                'Finish with grated parmesan and serve straight from the pan.',
            ]),
            'Halloumi Egg Stack' => $this->steps([
                'Brush halloumi lightly with olive oil and grill until golden on both sides.',
                'Wilt spinach in a pan with the remaining olive oil. Halve cherry tomatoes.',
                'Poach eggs until whites are set and yolks are runny.',
                'Layer halloumi, spinach, and poached eggs. Spoon Greek yogurt on top and finish with black pepper.',
            ]),
            'Feta & Dill Egg Muffins' => $this->steps([
                'Heat oven to 180°C (350°F). Brush a muffin tin with olive oil.',
                'Whisk eggs with salt, pepper, chopped dill, and spring onion.',
                'Fold in spinach and crumbled feta. Divide between cups, filling about three-quarters full.',
                'Bake 15–18 minutes until set in the centre. Cool 5 minutes before serving.',
            ]),
            'Deconstructed Shakshuka Skillet' => $this->steps([
                'Sauté onion and pepper in olive oil until soft (5 min).',
                'Add crushed tomato and spices. Simmer 8–10 minutes.',
                'Make small wells in the sauce. Crack eggs into the wells.',
                'Cover and cook on low until whites are set (5–7 min).',
                'Serve straight from the pan.',
            ]),
            'Hummus Egg Stack' => $this->steps([
                'Prepare Creamy Cumin Hummus (Base) per base recipe instructions. Warm and spread a generous layer in a shallow bowl.',
                'Halve the cherry tomatoes. Sauté spinach and tomatoes in olive oil over medium heat until the spinach is wilted and the tomatoes are softened (3–4 min).',
                'Spoon the spinach and tomato layer over the hummus.',
                'Soft-boil eggs until the whites are set and yolks are jammy (6–7 min). Halve and place on top.',
                'Add cucumber slices and finish with cracked black pepper. Serve immediately.',
            ]),
            'Kuku Sabzi Egg Muffins' => $this->steps([
                'Heat oven to 180°C (350°F). Brush a muffin tin with olive oil.',
                'Finely mince spinach, fresh coriander, dill, and spring onion.',
                'Whisk eggs with sea salt and black pepper until frothy.',
                'Fold in the minced herbs, spring onion, chopped walnuts, and barberries (zereshk).',
                'Divide between muffin cups, filling about three-quarters full. Bake 18–22 minutes until set in the centre.',
                'Cool 5 minutes before removing. Serve warm or at room temperature.',
            ]),
            'Sweet Potato Egg Hash' => $this->steps([
                'Preheat oven to 200°C. Toss diced sweet potato with half the olive oil, rosemary, thyme, sea salt, and black pepper. Roast until tender (25–30 min).',
                'Heat the remaining olive oil in a frying pan. Sauté diced onion and red bell pepper until softened (4–5 min), then wilt in the spinach (1–2 min).',
                'Add roasted sweet potato and toss to combine.',
                'Beat the whole egg with the egg whites, pour into the pan, and scramble gently over medium-low heat until just set.',
                'Finish with fresh coriander and flaxseeds, and serve hot.',
            ]),
            'Butternut Squash Frittata' => $this->steps([
                'Preheat the oven to 180°C (350°F).',
                'Cut butternut squash into 2 cm cubes. Toss with half the olive oil, paprika, and sea salt. Roast on a tray until tender and lightly golden (25–30 min).',
                'Dice red onion (or thinly slice spring onion). Sauté in the remaining olive oil in an oven-safe skillet until softened (4–5 min).',
                'Whisk two large eggs with Greek yogurt, chickpea flour, chopped dill, and half the shredded gruyère. Fold in roasted squash and sautéed onion.',
                'Pour into the skillet, scatter the remaining gruyère on top, and bake until the centre is just set (15–18 min).',
                'Fry two large eggs in a little olive oil until whites are crisp and yolks are runny.',
                'Prepare Marinara Sauce (Base) per base recipe instructions. Warm and serve on the side.',
                'Top the frittata with fried eggs and serve with marinara.',
            ]),
            'Butternut Squash & Eggs' => $this->steps([
                'Preheat the oven to 180°C (350°F).',
                'Cut butternut squash into 2 cm cubes. Toss with half the olive oil, paprika, and sea salt. Roast on a tray until tender and lightly golden (25–30 min).',
                'Dice red onion (or thinly slice spring onion). Sauté in the remaining olive oil in an oven-safe skillet until softened (4–5 min).',
                'Whisk two large eggs with chickpea flour and chopped dill. Fold in roasted squash and sautéed onion.',
                'Pour into the skillet and bake until the centre is just set (15–18 min).',
                'Fry two large eggs in a little olive oil until whites are crisp and yolks are runny.',
                'Prepare Marinara Sauce (Base) per base recipe instructions. Warm and serve on the side.',
                'Top the bake with fried eggs and serve with marinara.',
            ]),
            'Smashed Beans & Eggs' => $this->steps([
                'Prepare Smashed White Beans (Base) per base recipe instructions.',
                'Dice tomato and chop fresh coriander.',
                'Heat olive oil in a non-stick pan over medium heat. Fry eggs until whites are crisp and yolks are runny.',
                'Spoon warm smashed beans onto plates, top with eggs, tomato, and coriander.',
                'Crumble feta over the top and finish with pumpkin seeds.',
                'Serve immediately.',
            ]),

            // Chicken plate mains
            'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => $this->steps([
                'Combine tamarind paste, honey, rice vinegar, sesame oil, crushed garlic, grated ginger, salt, and spring onion pieces in a jug.',
                'Marinate chicken breast in the sauce overnight.',
                'Preheat oven. Bake 25 minutes until cooked through (74°C internal).',
                'Sprinkle chicken with finely sliced spring onion. Combine tray juices with leftover marinade and drizzle over liberally.',
                'Serve with steamed broccoli, cucumber pickle, and Garlicky Green Beans (Base) sprinkled with sesame seeds and spring onion.',
            ]),
            'Grilled Chicken Chimichurri' => $this->steps([
                'Finely chop parsley, coriander, and garlic. Mix with olive oil, lemon, and vinegar.',
                'Season chicken breast. Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Roast or steam sweet potato and broccoli until tender.',
                'Plate chicken over veg. Spoon fresh herb sauce on top.',
            ]),
            'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini' => $this->steps([
                'Coat chicken with Harissa Paste (Base) and a little olive oil. Rest 15 minutes.',
                'Cube sweet potato and zucchini. Toss with oil. Roast at 200°C for 25 minutes.',
                'Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Rest, slice, and serve over roasted vegetables.',
                'Garnish with fresh chopped mint.',
            ]),
            'Pepper Chicken in Creamy Cajun Sauce w Roasted Potato' => $this->steps([
                'Cube potatoes. Toss with oil and roast at 200°C until crisp (25–30 min).',
                'Rub chicken with Cajun Spice (Base). Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Sauté sliced bell pepper, red onion, and garlic in olive oil until softened.',
                'Add Cajun Spice (Base) and cherry tomatoes. Cook 2 minutes.',
                'Stir in homemade coconut milk and lime juice. Simmer until the sauce thickens.',
                'Serve sliced chicken and creamy Cajun sauce over roasted potatoes.',
            ]),
            'Grilled Sumac Chicken Skewers w Zereshk & Turmeric Rice & Roasted Mixed Vegetables' => $this->steps([
                'Prepare Turmeric Rice (Base) per base recipe instructions; fold through barberries (zereshk) and keep warm.',
                'Prepare Roasted Mixed Vegetables (Base) per base recipe instructions.',
                'Marinate chicken in Pomegranate Sumac Sauce (Base) for at least 2 hours or overnight.',
                'Preheat oven to 190°C. Arrange thinly sliced red onion in a roasting dish. Thread chicken onto skewers, place over the onion with the marinade, and season with salt and pepper.',
                'Roast 45–60 minutes until richly golden and cooked through, basting with pan juices several times.',
                'Serve skewers with zereshk turmeric rice, roasted vegetables on the side, and fresh parsley.',
            ]),
            'Grilled Chicken Tikka bowl w Quinoa & Mint Sauce' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions.',
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Shred cabbage; julienne carrots and cucumber.',
                'Layer quinoa, vegetables, and chicken.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Grilled Chicken Tikka Salad w Quinoa & Cilantro Lime Dressing' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions.',
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Shred cabbage; julienne carrots and cucumber.',
                'Layer quinoa, vegetables, and chicken.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Blackened Chicken, Grilled Peppers & Onion Salad w Quinoa, Kale & Mustard Dressing' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; let cool slightly.',
                'Rub chicken with Blackened Seasoning (Base). Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Grill pepper strips and onion until charred and soft.',
                'Massage kale with a little lemon and oil until tender.',
                'Toss quinoa, kale, and vegetables with mustard dressing. Top with chicken.',
            ]),

            // Chicken salad mains
            BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME => $this->steps([
                'Toss sweet potato wedges with half the olive oil, salt, and pepper. Roast at 200°C for 22–25 minutes until golden.',
                'Grill or pan-sear Rosemary Garlic Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Sauté mushrooms in remaining olive oil until golden. Wilt spinach briefly in the same pan.',
                'Plate roasted sweet potato, spinach, and mushrooms. Top with sliced chicken and serve warm.',
            ]),
            'Rosemary Chicken Rocca Salad' => $this->steps([
                'Grill or pan-sear Rosemary Garlic Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss rocca, purslane, cucumber, and cherry tomatoes in a bowl.',
                'Top with chicken and walnuts.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Turmeric Chicken Kale Salad' => $this->steps([
                'Grill or pan-sear Turmeric Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Massage kale until tender; lightly steam or blanch broccoli until bright green.',
                'Toss kale, broccoli, avocado, coriander, pumpkin seeds, and sesame seeds.',
                'Top with warm turmeric chicken.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Chicken Thai Mango Salad' => $this->steps([
                'Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice thinly.',
                'Shred cabbage; slice mango, cucumber, tomatoes, and red onion.',
                'Toss vegetables with coriander.',
                'Top with chicken and cashew nuts.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Tandoori Coconut Mint Salad' => $this->steps([
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss romaine, cucumber, celery, tomatoes, onion, mint, and coriander.',
                'Top with chicken, cashews, and pomegranate. Finish with a pinch of black seeds.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Mediterranean Crunch Salad' => $this->steps([
                'Dice cucumber, cherry tomatoes, red pepper, and red onion.',
                'Grill or pan-sear Rosemary Garlic Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss romaine, rocca, vegetables, basil, olives, walnuts, and pumpkin seeds. Top with chicken.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Tandoori Chicken Salad' => $this->steps([
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss romaine, cucumber, celery, about 8 halved cherry tomatoes, onion, herbs, and pomegranate.',
                'Top with chicken and cashews.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),

            // Salmon mains
            BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME => $this->steps([
                'Prepare Roasted Mixed Vegetables (Base) per base recipe instructions; keep warm.',
                'Coat salmon generously with Fermented Chimichurri (Base).',
                'Bake at 190°C for 12–15 minutes until flaky.',
                'Steam or roast broccoli until bright green and tender.',
                'Plate roasted mixed vegetables and broccoli. Top with salmon, pumpkin seeds, and extra chimichurri if desired.',
            ]),
            'Citrus Herb Salmon' => $this->steps([
                'Roast sweet potato cubes at 200°C for 20 minutes.',
                'Season salmon with herbs, lemon, and orange juice.',
                'Pan-sear or bake salmon 4–5 minutes per side until cooked.',
                'Steam or roast asparagus for 4–5 minutes.',
                'Serve salmon with sweet potato and asparagus.',
            ]),
            'Grilled Salmon Mango Salsa' => $this->steps([
                'Cube pumpkin and roast at 200°C until tender and lightly caramelized at the edges.',
                'Prepare Lemon Herb Salmon Marinade (Base) per base recipe instructions. Coat salmon and marinate 20–30 minutes.',
                'Prepare Citrus Herb Sauce (Base) per base recipe instructions; keep warm.',
                'Dice mango, pepper, cucumber, and avocado. Toss with purslane, cashew nuts, a spoonful of citrus herb sauce, and coriander.',
                'Grill or pan-sear the marinated salmon until cooked through.',
                'Serve salmon over roasted pumpkin with the mango salsa salad. Spoon the remaining citrus herb sauce over the salmon so it stays moist and glossy.',
            ]),

            // Beef mains
            'Grilled Beef Steak Ratatouille & Saffron rice' => $this->steps([
                'Prepare Saffron Rice (Base) per base recipe instructions; keep warm.',
                'Sauté diced eggplant, zucchini, pepper, and tomato with garlic in olive oil until soft (12–15 min). Stir in basil, chard, and parsley.',
                'Season steak with black pepper. Sear in a hot pan 3–4 minutes per side for medium-rare (adjust to taste).',
                'Rest steak 5 minutes. Slice against the grain.',
                'Finish the ratatouille with lemon juice. Serve sliced steak with the vegetable medley and saffron rice.',
            ]),
            'Beef Bibimbap' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Brown ground beef with garlic in a pan. Season lightly.',
                'Sauté spinach, carrots, and zucchini separately until tender.',
                'Fry eggs sunny-side up.',
                'Layer quinoa, vegetables, and beef in a bowl. Top with egg and sesame seeds.',
            ]),
            'Beef Shawarma Platter' => $this->steps([
                'Prepare Beef Shawarma (Base), Creamy Cumin Hummus (Base), Cucumber Pickle (Base), and Fire Roasted Tomatoes (Base) per base recipe instructions.',
                'Plate hummus, drizzle with olive oil, and garnish with parsley.',
                'Add shredded beef shawarma, fresh cucumber slices, grilled tomato, and cucumber pickle.',
            ]),
            'Persian Herb Beef Stew' => $this->steps([
                'Prepare Ghormeh Sabzi Stew (Base) and Steamed Basmati Rice (Base) per base recipe instructions; keep both hot.',
                'Brown beef chuck cubes in a little olive oil; add water to cover and simmer 60–90 minutes until tender. Season lightly.',
                'Portion steamed rice, beef, and sabzi stew per kitchen gram targets for the calorie tier.',
                'Serve rice with beef and ghormeh sabzi stew spooned over or alongside.',
            ]),
            'Chili Beef Stuffed Peppers' => $this->steps([
                'Prepare Cooked Quinoa (Base) and Fermented Beetroot (Base) per base recipe instructions.',
                'Brown ground beef and beef liver with onion and garlic. Stir in chili powder, diced tomato, parsley, and spinach until wilted.',
                'Fold in cooked quinoa. Halve peppers, remove seeds, and stuff with the mixture.',
                'Bake at 190°C for 25–30 minutes until peppers are soft.',
                'Plate stuffed peppers with purslane, fermented beetroot, and sunflower seeds on the side.',
            ]),
            'Rosemary Garlic Chicken w Pomegranate Glaze, Beetroot & Rocca' => $this->steps([
                'Prepare Rosemary Garlic Chicken (Base) and Quinoa Flatbread (Base) per base recipe instructions; keep the flatbread warm.',
                'Roast or boil beetroot until tender. Cool, peel, and slice.',
                'Sauté diced onion in olive oil until softened (4–5 min). Add sliced red pepper; cook 2–3 minutes. Stir in garlic and oregano.',
                'Grill or pan-sear the rosemary garlic chicken until golden, then finish in the oven for 20 minutes exactly. Rest and slice.',
                'Finish the sautéed vegetables with pomegranate molasses, black pepper, and a pinch of nutmeg.',
                'Serve sliced chicken with warm quinoa flatbread, pomegranate vegetables, beetroot, rocca, and sliced raw tomato.',
            ]),

            // Vegan mains
            BalancedCanonicalMealRecipeRefiner::VEGAN_BUTTERNUT_PEANUT_STEW_NAME => $this->steps([
                'Prepare Cooked Brown Basmati Rice (Base) per base recipe instructions; keep warm.',
                'Fry finely chopped onion in olive oil for 5 minutes until soft. Grate in garlic and stir.',
                'Add chopped tomatoes and cook for a couple of minutes. Add water, rinsed red lentils, chopped red pepper, and butternut squash cubes. Bring to the boil, then reduce to a simmer.',
                'Stir in vegetable stock and peanut butter until combined. Add zucchini and simmer for 20 minutes.',
                'Add mushrooms and spinach–cabbage greens. Simmer a couple of minutes until wilted. Season with sea salt, black pepper, and chilli flakes.',
                'Serve stew over cooked brown basmati rice. Top with fresh coriander, cherry tomatoes, crushed peanuts, and lime juice.',
            ]),
            'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini' => $this->steps([
                'Prepare Quinoa Flatbread (Base) per base recipe instructions; keep warm (one full folded crepe for scooping).',
                'Heat olive oil in a small pot over medium heat. Sauté diced white onion, minced garlic, and grated ginger with cumin seeds, coriander powder, smoked paprika, and chili flakes for 1 minute until fragrant.',
                'Add dry red lentils, halved cherry tomatoes, and cauliflower florets. Pour in water and season with sea salt.',
                'Bring to a boil, then reduce heat to low, cover, and simmer 12–14 minutes until the lentils break down into a creamy dal and the cauliflower is tender.',
                'Fold in chopped chard and cook 2 minutes until wilted.',
                'Stir in lemon juice, transfer to a bowl, and finish with a tahini drizzle. Serve warm alongside the quinoa flatbread.',
            ]),
            'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread' => $this->steps([
                'Rinse red lentils. Simmer with water, turmeric, and ginger until soft (20 min).',
                'Sauté onion, garlic, and spices in oil. Stir into lentils.',
                'Simmer 5 more minutes until creamy.',
                'Warm quinoa bread. Serve dal with bread and fresh coriander.',
            ]),
            'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing' => $this->steps([
                'Prepare Cooked Chickpeas (Base) per base recipe instructions from 35g dry chickpeas (about 75–80g cooked), or measure 75g cooked chickpeas.',
                'Toss the cooked chickpeas, cubed beetroot, and cauliflower florets with olive oil, Harissa Paste (Base), and a pinch of sea salt.',
                'Spread onto a baking sheet and roast at 200°C for 22–25 minutes until caramelized and tender.',
                'Transfer roasted vegetables and chickpeas to a wide serving bowl. Toss gently with sliced shallots, fresh dill, and fresh mint.',
                'Scatter sunflower seeds and black seeds over the top.',
                'Drizzle Lemon-Tahini Dressing (Base) over the salad, or serve on the side.',
            ]),
            'Vegan Curry Lentil Salad' => $this->steps([
                'Cook lentils until tender but not mushy. Drain and cool.',
                'Cook wild rice if included. Cool slightly.',
                'Whisk curry powder with lemon and olive oil.',
                'Toss lentils, rice, spinach, carrots, and pepper with dressing.',
                'Serve at room temperature or chilled.',
            ]),
            'Spiced Cauliflower Chickpea Salad' => $this->steps([
                'Prepare Cooked Chickpeas (Base) per base recipe instructions.',
                'Toss cauliflower with cumin, paprika, and oil.',
                'Roast at 200°C for 22 minutes. Add cooked chickpeas for the last 10 minutes.',
                'Cool slightly. Serve over romaine with lemon and olive oil.',
            ]),
            'Thai Rainbow Peanut Salad' => $this->steps([
                'Shred cabbage and julienne carrots and cucumber.',
                'Whisk peanut butter with lime juice and water until smooth.',
                'Toss vegetables with dressing and fresh coriander.',
                'Serve chilled. Add crushed peanuts on top if included.',
            ]),

            // Side salads (legume-free vegan)
            'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad' => $this->steps([
                'Dice pineapple, pepper, cucumber, and red onion.',
                'Toss with thinly sliced cabbage and dressing.',
                'Refrigerate 15–30 minutes to meld flavours.',
                'Add coriander and chilli before serving.',
            ]),
            'Tomato Parsely Salad w Sumac Za’ater Dressing' => $this->steps([
                'Prepare Sumac Za\'atar Dressing (Base) per base recipe instructions; rest 10 minutes.',
                'Halve or wedge tomatoes. Slice cucumber and thinly slice red onion.',
                'Roughly chop parsley and mint; tear rocca into bite-sized pieces.',
                'Combine tomatoes, cucumber, onion, rocca, herbs, and pomegranate seeds in a bowl.',
                'Serve at room temperature with dressing on the side.',
            ]),
            'Citrus Beet Arugula Salad' => $this->steps([
                'Roast or boil beetroot until tender. Cool, peel, and slice.',
                'Arrange arugula on plates. Add beets and orange segments.',
                'Scatter walnuts. Drizzle with lemon and olive oil.',
            ]),
            'Shaved Fennel Rocca Salad' => $this->steps([
                'Shave fennel very thin (mandoline or sharp knife).',
                'Toss fennel and rocca with orange segments, pomegranate, and walnuts.',
                'Crumble goat feta over the top. Serve dressing on the side.',
            ]),
            'Roasted Eggplant Rocca Salad' => $this->steps([
                'Cube eggplant. Roast at 200°C with oil until soft and golden (25 min).',
                'Halve cherry tomatoes. Toss with rocca and lemon.',
                'Combine with warm eggplant and pomegranate seeds.',
            ]),
            'Marinated Strawberry Beet Salad' => $this->steps([
                'Cook beetroot until tender. Cool and dice.',
                'Slice strawberries and onion. Toss with vinegar and oil.',
                'Marinate 20 minutes. Serve over romaine.',
            ]),
            'Coconut Grapefruit Salad' => $this->steps([
                'Segment grapefruit. Slice cucumber.',
                'Toss romaine with lime dressing.',
                'Top with grapefruit, cucumber, and coconut.',
            ]),
            'Classic Garden Salad' => $this->steps([
                'Chop lettuce, tomato, and cucumber.',
                'Shred or thinly slice the carrots.',
                'Toss the vegetables together in a large bowl.',
                'Serve with Classic Lemon Garlic Dressing (Base) on the side.',
            ]),

            // Desserts
            BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME => $this->steps([
                'Create the sweet date paste (15 min). Soak the pitted dates in boiling water for 10 minutes. Blend the dates and all the soaking water in a food processor until entirely smooth. Let it cool slightly so it does not cook the eggs.',
                'Prep your oven and vanilla bean (5 min). Preheat your oven to 350°F (175°C) and grease a 9×13-inch pan. Split the vanilla bean pod lengthwise and scrape out all the tiny black seeds.',
                'Whisk the dry base (2 min). In a large bowl, thoroughly whisk together the almond flour, cinnamon, ginger, nutmeg, baking soda, baking powder, and salt.',
                'Emulsify the wet ingredients (3 min). In a separate bowl, whisk the room-temperature eggs, homemade date paste, pumpkin puree, and scraped vanilla seeds until unified. Slowly drizzle in the melted grass-fed butter while whisking constantly.',
                'Combine and fold textures (2 min). Pour the wet mixture into the dry flour blend. Stir gently with a spatula just until combined, then fold in the grated carrots and chopped walnuts.',
                'Bake to perfection (38–42 min). Spread the batter evenly into your pan. Bake for 38 to 42 minutes. Because pumpkin holds excellent moisture, it needs those extra few minutes. Test the center with a toothpick—it should come out clean.',
                'Cool completely, then cut into '.BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_SERVINGS_COUNT.' equal slices. One slice is one serving.',
            ]),
            BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_NAME => $this->steps([
                'Make the citrus & date sweetener. Pour boiling water over the pitted dates and soak 10 minutes. Blend with the orange zest and fresh orange juice until completely smooth.',
                'Build the rich chocolate base. Melt the grass-fed butter, then whisk in the Dutch-process cocoa until glossy. Beat in the room-temperature eggs one at a time.',
                'Whisk the grain-free flours. In a bowl, combine the super-fine blanched almond flour, tapioca starch, psyllium husks, baking powder, and fine sea salt.',
                'Fold the batters together. Stir the date-orange puree into the chocolate base, then fold in the dry ingredients just until no dry streaks remain.',
                'Bake at 175°C. Spread into a lined tin and bake until a skewer from the center comes out mostly clean with moist crumbs.',
                'Cool completely, then cut into '.BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT.' equal small squares. One square is one serving.',
            ]),
            BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME => $this->steps([
                'Heat oven to 175°C. Line an 8x8 inch pan with parchment paper.',
                'Mix almond flour, 3 tablespoons coconut oil, 2 tablespoons date syrup, vanilla, and salt into a crumb. Press evenly into the pan and bake 10 minutes. Cool 10 minutes.',
                'Warm tahini, date syrup, coconut oil, vanilla, and sea salt in a saucepan over medium-low heat for about 2 minutes, stirring often. Pour over the crust.',
                'Refrigerate 30 to 60 minutes until the caramel layer sets.',
                'Whisk cocoa powder, remaining coconut oil, and a little date syrup until smooth and glossy.',
                'Pour the chocolate layer over the caramel, tilt the pan to coat evenly, and chill 1 hour until firm. Sprinkle with flaky sea salt.',
                'Lift from the pan and cut into '.BalancedRotationMealRecipeRefiner::SALTED_CARAMEL_CHOCOLATE_BAR_SERVINGS_COUNT.' squares. One square is one serving.',
            ]),
            'Apple Pie Balls' => $this->steps([
                'Pulse khelas dates, almond flour, chopped apple, walnuts, cinnamon, and almond butter in a food processor until the mixture holds together.',
                'Roll into '.BalancedRotationMealRecipeRefiner::APPLE_PIE_BALLS_PER_SERVING_COUNT.' small bite-size balls (~14g each). One serving is all '.BalancedRotationMealRecipeRefiner::APPLE_PIE_BALLS_PER_SERVING_COUNT.' balls.',
                'Chill 30 minutes until firm. Serve cold.',
            ]),
            'Banana Blueberry Balls' => $this->steps([
                'Pulse almond flour, flaxseeds, cinnamon, maple syrup, almond butter, banana, and blueberries in a food processor until the mixture holds together.',
                'Roll into '.BalancedRotationMealRecipeRefiner::BANANA_BLUEBERRY_BALLS_PER_SERVING_COUNT.' bite-size balls (~19g each). One serving is all '.BalancedRotationMealRecipeRefiner::BANANA_BLUEBERRY_BALLS_PER_SERVING_COUNT.' balls.',
                'Chill 30 minutes until firm. Serve cold.',
            ]),
            'Cinnamon Raisin Balls' => $this->steps([
                'Combine dates or binder, raisins, nuts, and cinnamon in a food processor.',
                'Pulse until mixture holds together.',
                'Roll into balls. Refrigerate until firm.',
            ]),
            'Saffron Pumpkin Muffin' => $this->steps([
                'Heat oven to 180°C. Line a muffin tin.',
                'Mix pumpkin, eggs, saffron, and dry ingredients.',
                'Divide into cups. Bake 18–22 minutes until springy.',
                'Cool before serving.',
            ]),
            'Chocolate PB Banana Muffin' => $this->steps([
                'Prep time: 10 mins | Bake time: 18–20 mins | Equipment: 6-cup muffin tin, muffin liners.',
                'Prep the oven: Preheat to 175°C (350°F) and line a '.BalancedRotationMealRecipeRefiner::CHOCOLATE_PB_BANANA_MUFFIN_BATCH_SERVINGS_COUNT.'-cup muffin tin with paper liners.',
                'Mix the wets: In a medium bowl, vigorously whisk the mashed bananas (200g), eggs, peanut butter, and maple syrup together until smooth and completely combined.',
                'Add the dries: Sift in the almond flour, cocoa powder, baking soda, and salt. Stir gently with a spatula just until the batter comes together and no dry pockets of flour remain.',
                'Bake: Divide the batter evenly among the '.BalancedRotationMealRecipeRefiner::CHOCOLATE_PB_BANANA_MUFFIN_BATCH_SERVINGS_COUNT.' muffin cups. Bake for 18 to 20 minutes, or until the tops spring back when lightly touched and a toothpick inserted into the center comes out clean.',
                'Cool: Let them cool in the pan for 5 minutes, then transfer to a wire rack to cool completely. One muffin is one serving.',
            ]),
            'Fruit Salad Bowl' => $this->steps([
                'Wash and chop all fruit into bite-size pieces.',
                'Toss gently with lemon juice and honey if using.',
                'Chill 15 minutes. Serve cold.',
            ]),

            // Soups — all batch recipes; whisk in 1 tbsp (15 g) psyllium husks per serving before portioning.
            'Vegan Mushroom Soup' => $this->steps([
                'Sauté onion and mushrooms in oil until browned (8 min).',
                'Add garlic, thyme, and turmeric. Cook 1 minute.',
                'Pour in stock and coconut milk. Simmer 15 minutes.',
                'Blend partially for a creamy texture, or leave chunky.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Reheat gently and portion.',
            ]),
            'Butternut Squash Soup' => $this->steps([
                'Sauté onion in oil until soft.',
                'Add cubed squash and stock. Simmer until squash is very tender (20 min).',
                'Blend until smooth. Whisk in psyllium husks (1 tablespoon / 15 g per serving). Season with spices from recipe.',
                'Reheat gently and portion.',
            ]),
            'Tomato Basil Soup' => $this->steps([
                'Sauté onion and garlic in olive oil for 3 minutes.',
                'Add chopped tomatoes and broth. Simmer 20 minutes.',
                'Blend with fresh basil until smooth.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Reheat and portion with extra basil on top.',
            ]),
            'Red Lentil Turmeric Soup' => $this->steps([
                'Rinse red lentils.',
                'Sauté onion, garlic, ginger, and spices for 2 minutes.',
                'Add lentils, carrots, broth, and water. Simmer 25 minutes.',
                'Stir in spinach until wilted. Finish with lemon juice.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving) and portion.',
            ]),
            'Cauliflower Ginger Soup' => $this->steps([
                'Sauté onion and ginger in oil for 3 minutes.',
                'Add cauliflower and stock. Simmer until very soft (18 min).',
                'Blend with coconut milk until smooth.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Reheat and portion.',
            ]),
            'Carrot Cumin Soup' => $this->steps([
                'Toast cumin seeds in a dry pan for 30 seconds.',
                'Sauté onion, garlic, and carrots in oil for 5 minutes.',
                'Add lentils, stock, and spices. Simmer until carrots and lentils are soft.',
                'Blend partially or fully. Finish with parsley and lemon.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving) and portion.',
            ]),
            'Lentil Carrot Soup' => $this->steps([
                'Toast cumin seeds in a dry pan for 30 seconds.',
                'Sauté onion, garlic, and carrots in oil for 5 minutes.',
                'Add lentils, stock, and spices. Simmer until carrots and lentils are soft.',
                'Blend partially or fully. Finish with parsley and lemon.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving) and portion.',
            ]),
            'Sweet Potato Fennel Soup' => $this->steps([
                'Sauté fennel and onion in oil until softened.',
                'Add sweet potato, ginger, and broth. Simmer 20 minutes.',
                'Blend with coconut milk until smooth.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Reheat and portion.',
            ]),
            'Miso Mushroom Soup' => $this->steps([
                'Simmer mushrooms in water with ginger until tender.',
                'Remove from heat. Whisk miso paste into the broth until smooth (do not boil miso).',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Top with spring onion and portion.',
            ]),
            'Miso Carrot Ginger Soup' => $this->steps([
                'Heat olive oil over medium-high heat in a soup pot. Sauté onion, garlic, and carrot until the onion is translucent, about 10 minutes.',
                'Add ginger and Vegetable Broth (Base). Mix well and bring to a boil. Reduce heat to a simmer, cover, and cook until the carrot is very tender, about 30 minutes.',
                'Turn off the heat. Puree the soup with an immersion blender (or carefully in a blender, then return to the pot).',
                'In a small bowl, whisk white miso paste with a ladle of the hot soup until fully dissolved. Stir the miso mixture back into the pot. Season with sea salt and black pepper if needed.',
                'Whisk in psyllium husks (1 tablespoon / 15 g per serving). Reheat gently without boiling.',
                'Serve hot. Garnish each bowl with spring onion, roasted nori, Shichimi Togarashi (Base), and a drizzle of sesame oil.',
            ]),
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => $this->steps([
                'Heat the full batch of defatted Bone Broth (Base) gently (do not boil hard).',
                'Whisk psyllium husks into the batch (1 tablespoon / 15 g per serving).',
                'Portion 500 ml per cup and serve hot.',
            ]),

            'Pesto Chicken Koosa Noodles' => $this->steps([
                'Preheat oven to 200°C. Dice pumpkin into 2 cm cubes, toss with half the olive oil, and roast until tender and golden at the edges (20–25 min).',
                'Spiralize zucchini into koosa noodles (or cut thin ribbons with a peeler). Pat dry.',
                'Season chicken breast with sea salt and black pepper. Heat the remaining olive oil in a pan over medium-high heat.',
                'Pan-sear chicken until golden, then finish in the oven for 20 minutes exactly. Rest and slice.',
                'In the same pan, blister cherry tomatoes for 2–3 minutes. Add koosa noodles and toss 1–2 minutes until just tender.',
                'Prepare Basil Pesto (House) per base recipe instructions. Toss noodles and tomatoes with pesto.',
                'Plate roasted pumpkin cubes, pesto koosa noodles, and sliced chicken. Finish with black pepper.',
            ]),
            'Lemon Chicken Eggplant' => $this->steps([
                'Prepare Eggplant Dip (Mutabal) (Base) and Zucchini Almond Bread (Base) per base recipe instructions. Toast the bread and keep warm.',
                'Cut chicken breast into cubes. Slice lemon into thin rounds.',
                'Thread the chicken onto skewers, alternating with lemon slices between the chicken pieces.',
                'Whisk lemon juice, olive oil, minced garlic, and fresh oregano. Brush the skewers with the marinade.',
                'Grill or pan-sear the skewers until golden, then finish in the oven for 20 minutes exactly. Rest.',
                'Warm cherry tomatoes and diced red pepper in the pan for 2–3 minutes. Finish with fresh parsley.',
                'Serve the lemon chicken skewers over mutabal with peppers, tomatoes, purslane, and toasted zucchini almond bread.',
            ]),
            'Chicken Quinoa Plate' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Season chicken breast with ground cumin, sea salt, and black pepper.',
                'Heat olive oil in a pan. Pan-sear chicken 5–6 minutes per side until cooked through. Rest and slice.',
                'Steam or roast broccoli until bright green and tender.',
                'Plate quinoa, broccoli, and sliced chicken.',
            ]),
            'Craft Shrimp Avocado Bowl' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Season raw shrimp with sea salt and black pepper. Heat olive oil in a pan over medium-high heat.',
                'Sauté shrimp 1–2 minutes per side until pink and curled. Remove from heat.',
                'Wilt spinach in the same pan with a splash of water (30 seconds). Halve cherry tomatoes.',
                'Assemble bowl with quinoa, spinach, tomatoes, and sliced avocado. Top with shrimp and a squeeze of lime juice.',
            ]),
            'High Protein Miso Crunch Salad' => $this->steps([
                'Whisk miso paste, tahini, rice vinegar, and water until smooth for the dressing.',
                'Thinly shred purple cabbage and julienne carrots. Toss with edamame.',
                'Season chicken breast and grill or pan-sear until cooked through. Rest and slice into strips.',
                'Toss salad vegetables with half the dressing. Top with chicken strips and drizzle remaining dressing.',
            ]),
            'Salmon Plate' => $this->steps([
                'Pat salmon dry. Season with sea salt and black pepper.',
                'Pan-sear or bake at 190°C for 12–15 minutes until flaky and cooked through.',
                'Rest 2 minutes and serve.',
            ]),
            'Salmon Plate B' => $this->steps([
                'Pat salmon dry. Season with sea salt and black pepper.',
                'Pan-sear or bake at 190°C for 12–15 minutes until flaky and cooked through.',
                'Rest 2 minutes and serve.',
            ]),
            'Salmon Quinoa Bowl' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Pat salmon dry. Season with sea salt and black pepper.',
                'Pan-sear or bake salmon at 190°C for 12–15 minutes until flaky.',
                'Plate quinoa and top with salmon.',
            ]),
            'Chia Dessert' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions.',
                'Portion and serve warm or chilled as directed for your plan.',
            ]),
            NutrientDenseLiverMealRecipeRefiner::SAUTEED_CHICKEN_LIVER_NAME => $this->steps([
                'Prepare Quinoa Flatbread (Base) per base recipe instructions; keep warm.',
                'Pat chicken liver dry and season with sea salt, black pepper, and nutmeg.',
                'Warm olive oil in a wide pan. Sauté red onion and garlic until fragrant. Add sliced cabbage and bell pepper; cook until softened (4–5 min).',
                'Push vegetables to the side. Sear livers 1–2 minutes per side until browned outside and just cooked through.',
                'Stir cherry tomatoes and oregano into the vegetables. Finish with pomegranate molasses.',
                'Serve livers with garlicky cabbage and peppers alongside warm quinoa flatbread.',
            ]),
            NutrientDenseLiverMealRecipeRefiner::BEEF_LIVER_STUFFED_ZUCCHINI_NAME => $this->steps([
                'Prepare Zucchini Almond Bread (Base) per base recipe instructions; toast before serving and keep warm.',
                'Chop and fry 1 small onion in olive oil until softening. Add oregano and garlic; cook until golden.',
                'Add minced beef and finely minced liver; fry until browned. Season with black pepper, smoked paprika, cumin powder, and coriander powder.',
                'Halve the zucchini lengthwise and scoop into boats. Oil the zucchini boats and bake until golden.',
                'Stuff the boats with the beef–liver mixture, spoon Marinara Sauce (Base) over the top, and bake until heated through.',
                'Finish with fresh basil leaves. Serve with toasted zucchini almond bread.',
            ]),
            NutrientDenseFermentedRecipeRefiner::TAHINI_PURSLANE_PEPPER_SALAD_NAME => $this->steps([
                'Prepare Roasted Cherry Tomato (Base) and Lemon-Tahini Dressing (Base) per base recipe instructions.',
                'Toss purslane, sliced bell pepper, and roasted cherry tomatoes in a bowl.',
                'Scatter sesame seeds over the salad.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            NutrientDenseFermentedRecipeRefiner::MACKEREL_QUINOA_NAME => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions. Fold in chopped parsley and half the lemon juice.',
                'Score mackerel fillets. Whisk olive oil, remaining lemon juice, sea salt, and black pepper. Coat fish and rest 10 minutes.',
                'Grill or pan-sear skin-side down over medium-high heat until skin is crisp and flesh is cooked through, about 4–5 minutes per side.',
                'Serve mackerel over lemon herb quinoa.',
            ]),
        ];

        return MealLibraryRefinerOverrides::mergeInstructionDefinitionMap($definitions);
    }

    /**
     * @param  list<string>  $steps
     */
    private function steps(array $steps): string
    {
        $lines = [];

        foreach ($steps as $index => $step) {
            $lines[] = ($index + 1).'. '.$step;
        }

        return implode("\n", $lines);
    }
}
