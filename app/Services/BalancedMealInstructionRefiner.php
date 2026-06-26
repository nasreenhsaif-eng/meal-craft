<?php

namespace App\Services;

use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
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
            $chiaBreakfastMeals = array_flip(BalancedChiaBreakfastRecipeRefiner::refinedMealNames());
            $tandooriMeals = array_flip(BalancedTandooriMealRecipeRefiner::refinedMealNames());

            foreach (BalancedWeeklyRotationSchedule::allScheduledMealNames() as $mealName) {
                if (isset($saladDressingMeals[$mealName]) || isset($chiaBreakfastMeals[$mealName]) || isset($tandooriMeals[$mealName])) {
                    continue;
                }

                $instructions = $definitions[$mealName] ?? null;

                if ($instructions === null) {
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
                    continue;
                }

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $meal->update([
                    'instructions' => $instructions,
                    'description' => $instructions,
                ]);
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @return array<string, string>
     */
    private function instructionDefinitions(): array
    {
        return [
            // Chia breakfasts
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
                'Sauté diced pepper, tomato, and shallot in olive oil for 3 minutes.',
                'Pour in eggs. Cook over medium heat until almost set.',
                'Add olives and avocado on one half. Fold omelet in half.',
                'Finish with fresh herbs. Serve warm.',
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
                'Preheat oven to 200°C. Toss diced sweet potato with olive oil, rosemary, thyme, sea salt, and black pepper. Spread on a tray and roast until tender (25–30 min).',
                'Sauté diced white onion and red bell pepper in a frying pan with olive oil until softened (5–6 min).',
                'Add roasted sweet potato to the pan and toss to combine. Season with a pinch of salt and pepper.',
                'Beat eggs, pour into the pan, and scramble gently over medium-low heat until just set.',
                'Finish with fresh coriander and serve hot.',
            ]),
            'Butternut Squash Fritters & Eggs' => $this->steps([
                'Preheat the oven to 200°C.',
                'Cut the butternut squash into 2–3 cm chunks. Place in a roasting tin, drizzle with olive oil, and season with sea salt, chili flakes, and fennel seeds. Roast for about 20 minutes, then leave to cool.',
                'When cool, place the butternut squash in a blender with any roasting juices. Add eggs, garlic, fresh coriander, lemon juice, cumin seeds, and coriander seeds. Blend to a rough paste and season to taste.',
                'Transfer the mixture to a bowl and stir in enough quinoa flour to make a smooth mix. Refrigerate for 1 hour to firm up. The mix should be sticky rather than wet — add a little more flour if needed.',
                'With wet hands, shape into ping-pong-ball-sized fritters. Bake at 200°C until golden and set through, or pan-fry in olive oil until crisp.',
                'Prepare Marinara Sauce (Base) per base recipe instructions. Warm and serve on the side.',
            ]),
            'Smashed Beans & Eggs' => $this->steps([
                'Prepare Smashed White Beans (Base) per base recipe instructions.',
                'Dice tomato and chop fresh coriander.',
                'Fry or poach eggs until whites are set and yolks are runny.',
                'Spoon warm smashed beans onto plates, top with eggs, tomato, and coriander.',
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
                'Grill or pan-sear chicken with rosemary and garlic until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss rocca and cucumber with a little olive oil and lemon.',
                'Plate greens. Top with chicken and dressing from the recipe.',
            ]),
            'Turmeric Chicken Kale Salad' => $this->steps([
                'Rub chicken with turmeric and garlic. Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Massage kale with lemon and olive oil until softened.',
                'Add cucumber and carrots. Top with warm chicken.',
                'Serve immediately.',
            ]),
            'Chicken Thai Mango Salad' => $this->steps([
                'Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice thinly.',
                'Shred cabbage and slice mango and cucumber.',
                'Whisk lime dressing. Toss salad with dressing.',
                'Top with chicken. Garnish with herbs.',
            ]),
            'Tandoori Coconut Mint Salad' => $this->steps([
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss romaine, cucumber, celery, tomatoes, onion, mint, and coriander.',
                'Top with chicken, cashews, and pomegranate. Finish with a pinch of black seeds.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),
            'Mediterranean Crunch Salad' => $this->steps([
                'Dice cucumber, tomatoes, and pepper.',
                'Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Combine vegetables with olives and lemon dressing.',
                'Top with chicken. Serve chilled or at room temperature.',
            ]),
            'Tandoori Chicken Salad' => $this->steps([
                'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                'Toss romaine, cucumber, celery, tomatoes, onion, herbs, and pomegranate.',
                'Top with chicken and cashews.',
                SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
            ]),

            // Salmon mains
            BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME => $this->steps([
                'Prepare Steamed Basmati Rice (Base) and keep warm.',
                'Coat salmon generously with Fermented Chimichurri (Base).',
                'Bake at 190°C for 12–15 minutes until flaky.',
                'Steam or roast broccoli until bright green and tender.',
                'Plate basmati rice and broccoli. Top with salmon and extra chimichurri if desired.',
            ]),
            'Citrus Herb Salmon' => $this->steps([
                'Roast sweet potato cubes at 200°C for 20 minutes.',
                'Season salmon with herbs, lemon, and orange juice.',
                'Pan-sear or bake salmon 4–5 minutes per side until cooked.',
                'Steam or roast asparagus for 4–5 minutes.',
                'Serve salmon with sweet potato and asparagus.',
            ]),
            'Grilled Salmon Mango Salsa' => $this->steps([
                'Cook wild rice according to package. Keep warm.',
                'Dice mango, pepper, and cucumber. Mix with lime and coriander for salsa.',
                'Grill or pan-sear salmon until cooked through.',
                'Serve salmon over rice with mango salsa on top.',
            ]),

            // Beef mains
            'Grilled Beef Steak Ratatouille & Saffron rice' => $this->steps([
                'Warm Saffron Rice (Base) per base recipe instructions.',
                'Sauté diced eggplant, zucchini, pepper, and tomato until soft (12–15 min). Stir in basil.',
                'Season steak. Sear in a hot pan 3–4 minutes per side for medium-rare (adjust to taste).',
                'Rest steak 5 minutes. Slice against the grain.',
                'Serve steak with vegetable medley and rice.',
            ]),
            'Beef Bibimbap' => $this->steps([
                'Prepare Cooked Quinoa (Base) per base recipe instructions; keep warm.',
                'Brown ground beef with garlic in a pan. Season lightly.',
                'Sauté spinach, carrots, and zucchini separately until tender.',
                'Fry eggs sunny-side up.',
                'Layer quinoa, vegetables, and beef in a bowl. Top with egg and sesame seeds.',
            ]),
            'Persian Herb Beef Stew' => $this->steps([
                'Brown beef cubes in olive oil. Set aside.',
                'Sauté onion until golden. Return beef with water to cover.',
                'Simmer low 60–90 minutes until beef is tender.',
                'Add beans, herbs, and spinach in the last 10 minutes.',
                'Warm quinoa bread separately. Serve stew with bread and lemon.',
            ]),
            'Chili Beef Stuffed Peppers' => $this->steps([
                'Cook brown rice until tender.',
                'Brown ground beef with onion and garlic. Stir in chili powder and diced tomato.',
                'Mix beef with cooked rice.',
                'Halve peppers, remove seeds. Fill with beef mixture.',
                'Bake at 190°C for 25–30 minutes until peppers are soft.',
            ]),

            // Vegan mains
            BalancedCanonicalMealRecipeRefiner::VEGAN_BUTTERNUT_PEANUT_STEW_NAME => $this->steps([
                'Cook brown rice. Keep warm.',
                'Fry finely chopped onion in olive oil for 5 minutes until soft. Grate in garlic and stir.',
                'Add chopped tomatoes and cook for a couple of minutes. Add water, rinsed red lentils, chopped red pepper, and butternut squash cubes. Bring to the boil, then reduce to a simmer.',
                'Stir in vegetable stock and peanut butter until combined. Add zucchini and simmer for 20 minutes.',
                'Add mushrooms and spinach–cabbage greens. Simmer a couple of minutes until wilted. Season with sea salt, black pepper, and chilli flakes.',
                'Serve stew over brown rice. Top with fresh coriander, cherry tomatoes, crushed peanuts, and lime juice.',
            ]),
            'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini' => $this->steps([
                'Roast cauliflower florets at 200°C for 20 minutes until golden.',
                'Simmer lentils with aromatics and stock until tender.',
                'Combine roasted cauliflower with lentils. Season with smoked paprika.',
                'Warm quinoa flatbread. Drizzle tahini over stew.',
                'Serve hot.',
            ]),
            'Vegan Sri Lankan Red Lentil Dal w Quinoa Bread' => $this->steps([
                'Rinse red lentils. Simmer with water, turmeric, and ginger until soft (20 min).',
                'Sauté onion, garlic, and spices in oil. Stir into lentils.',
                'Simmer 5 more minutes until creamy.',
                'Warm quinoa bread. Serve dal with bread and fresh coriander.',
            ]),
            'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing' => $this->steps([
                'Prepare Cooked Chickpeas (Base) per base recipe instructions.',
                'Toss cauliflower and cooked chickpeas with Harissa Paste (Base) and oil.',
                'Roast at 200°C for 25 minutes until crisp.',
                'Whisk tahini with lemon and water for dressing.',
                'Toss roasted mix with greens and dressing. Serve warm or at room temperature.',
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
                'Halve or wedge tomatoes. Thinly slice red onion.',
                'Chop parsley and mint.',
                'Whisk sumac za’atar dressing.',
                'Toss everything together. Serve at room temperature.',
            ]),
            'Citrus Beet Arugula Salad' => $this->steps([
                'Roast or boil beetroot until tender. Cool, peel, and slice.',
                'Arrange arugula on plates. Add beets and orange segments.',
                'Scatter walnuts. Drizzle with lemon and olive oil.',
            ]),
            'Shaved Fennel Rocca Salad' => $this->steps([
                'Shave fennel very thin (mandoline or sharp knife).',
                'Toss fennel and rocca with orange segments.',
                'Dress with lemon and olive oil. Serve immediately.',
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
                'Chop lettuce, tomato, cucumber, pepper, and cabbage.',
                'Thinly slice red onion.',
                'Whisk olive oil, lemon, and herbs for dressing.',
                'Toss just before serving.',
            ]),

            // Desserts
            BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME => $this->steps([
                'Heat oven to 175°C (350°F). Grease a baking pan with ghee.',
                'Whisk eggs, date syrup, melted ghee, and coconut cream until smooth.',
                'Fold in grated carrots, almond flour, tapioca starch, coconut flour, cinnamon, nutmeg, walnuts, and raisins.',
                'Pour into the pan. Bake 30–35 minutes until set and golden.',
                'Cool, then cut into '.BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_SERVINGS_COUNT.' equal slices. One slice is one serving.',
            ]),
            BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_NAME => $this->steps([
                'Heat oven to 175°C. Line a small tin.',
                'Whisk eggs with orange juice, zest, honey, and olive oil.',
                'Fold in almond flour, cocoa powder, and chopped walnuts until combined.',
                'Pour into tin. Bake until a skewer comes out mostly clean.',
                'Cool, then cut into '.BalancedRotationMealRecipeRefiner::CHOCOLATE_ORANGE_BROWNIE_SERVINGS_COUNT.' equal small squares. One square is one serving.',
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
                'Heat oven to 180°C. Line a muffin tin.',
                'Mash banana. Mix with peanut butter, egg, and dry ingredients.',
                'Fill muffin cups. Bake until set (18–22 min).',
                'Cool on a rack.',
            ]),
            'Fruit Salad Bowl' => $this->steps([
                'Wash and chop all fruit into bite-size pieces.',
                'Toss gently with lemon juice and honey if using.',
                'Chill 15 minutes. Serve cold.',
            ]),

            // Soups
            'Vegan Mushroom Soup' => $this->steps([
                'Sauté onion and mushrooms in oil until browned (8 min).',
                'Add garlic, thyme, and turmeric. Cook 1 minute.',
                'Pour in stock and coconut milk. Simmer 15 minutes.',
                'Blend partially for a creamy texture, or leave chunky.',
                'Serve hot.',
            ]),
            'Butternut Squash Soup' => $this->steps([
                'Sauté onion in oil until soft.',
                'Add cubed squash and stock. Simmer until squash is very tender (20 min).',
                'Blend until smooth. Season with spices from recipe.',
                'Reheat gently and serve.',
            ]),
            'Tomato Basil Soup' => $this->steps([
                'Sauté onion and garlic in olive oil for 3 minutes.',
                'Add chopped tomatoes and broth. Simmer 20 minutes.',
                'Blend with fresh basil until smooth.',
                'Reheat and serve with extra basil on top.',
            ]),
            'Red Lentil Turmeric Soup' => $this->steps([
                'Rinse red lentils.',
                'Sauté onion, garlic, ginger, and spices for 2 minutes.',
                'Add lentils, carrots, broth, and water. Simmer 25 minutes.',
                'Stir in spinach until wilted. Finish with lemon juice.',
            ]),
            'Cauliflower Ginger Soup' => $this->steps([
                'Sauté onion and ginger in oil for 3 minutes.',
                'Add cauliflower and stock. Simmer until very soft (18 min).',
                'Blend with coconut milk until smooth.',
                'Reheat and serve.',
            ]),
            'Carrot Cumin Soup' => $this->steps([
                'Toast cumin seeds in a dry pan for 30 seconds.',
                'Sauté onion, garlic, and carrots in oil for 5 minutes.',
                'Add lentils, stock, and spices. Simmer until carrots and lentils are soft.',
                'Blend partially or fully. Finish with parsley and lemon.',
            ]),
            'Sweet Potato Fennel Soup' => $this->steps([
                'Sauté fennel and onion in oil until softened.',
                'Add sweet potato, ginger, and broth. Simmer 20 minutes.',
                'Blend with coconut milk until smooth.',
                'Reheat and serve.',
            ]),
            BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME => $this->steps([
                'Measure 500 ml (one serving) of defatted Bone Broth (Base).',
                'Pour into a small pot.',
                'Heat gently on the stove until steaming (do not boil hard).',
                'Pour into a mug or bowl and serve hot.',
            ]),
        ];
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
