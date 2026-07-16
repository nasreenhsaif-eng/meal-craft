/**
 * @param {number | string | null | undefined} value
 * @param {'g' | ''} [suffix]
 */
function formatMacro(value, suffix = 'g') {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return suffix ? `0${suffix}` : '0';
    }

    const rounded = Math.round(n);

    return suffix ? `${rounded}${suffix}` : String(rounded);
}

/**
 * @param {string} slot
 */
function mealTypeLabelForSlot(slot) {
    switch (slot) {
        case 'breakfast':
            return 'Breakfast';
        case 'main':
            return 'Meal';
        case 'side_salad':
            return 'Side salad';
        case 'dessert':
            return 'Dessert';
        case 'soup':
            return 'Soup';
        default:
            return 'Meal';
    }
}

import { buildNutritionalDataFromNutrition } from '../meal-library/buildNutritionalDataFromNutrition.js';

/**
 * @param {Array<{ name?: string; adapted_amount_grams?: number; unit?: string; adapted_amount?: number | null }>} [ingredients]
 * @returns {string[]}
 */
function buildAdaptedIngredientLines(ingredients) {
    if (!Array.isArray(ingredients) || ingredients.length === 0) {
        return [];
    }

    return ingredients
        .map((row) => {
            const name = String(row?.name ?? '').trim();
            if (!name) {
                return null;
            }

            const grams = Number(row.adapted_amount_grams ?? 0);
            if (Number.isFinite(grams) && grams > 0) {
                const formatted = Math.round(grams * 100) / 100;

                return `${formatted}g ${name}`;
            }

            const amount = row.adapted_amount;
            const unit = String(row.unit ?? '').trim();
            if (amount != null && unit !== '') {
                return `${amount} ${unit} ${name}`;
            }

            return name;
        })
        .filter((line) => typeof line === 'string' && line !== '');
}

/**
 * @param {Record<string, unknown>} apiMeal
 */
export function mapAdaptedApiMealToConsultationMeal(apiMeal) {
    const slot = String(apiMeal.slot ?? '');
    const label = mealTypeLabelForSlot(slot);
    const adapted = /** @type {Record<string, number>} */ (apiMeal.adapted_nutrition ?? {});
    const calories = Number(adapted.calories ?? 0);
    const nutritionalData = buildNutritionalDataFromNutrition(adapted);
    const ingredientLines = buildAdaptedIngredientLines(
        Array.isArray(apiMeal.ingredients) ? apiMeal.ingredients : [],
    );

    return {
        id: String(apiMeal.id ?? ''),
        title: String(apiMeal.name ?? ''),
        imageUrl: typeof apiMeal.image_url === 'string' ? apiMeal.image_url : '',
        mealType: label,
        category: label,
        prepMinutes: 0,
        macros: {
            calories: Math.round(calories),
            protein: formatMacro(adapted.protein),
            carbs: formatMacro(adapted.carbs),
            fat: formatMacro(adapted.fat),
        },
        tags: [{ label, type: 'category' }],
        nutrientHighlights: [],
        caloriesNumber: Math.round(calories),
        slot,
        portionBehavior: typeof apiMeal.portion_behavior === 'string' ? apiMeal.portion_behavior : undefined,
        isScaled: Boolean(apiMeal.is_scaled),
        scalingMultiplier:
            typeof apiMeal.scaling_multiplier === 'number' ? apiMeal.scaling_multiplier : 1,
        proteinBalanced: Boolean(apiMeal.protein_balanced),
        isVegan: Boolean(apiMeal.is_vegan),
        savoryEggCount:
            typeof apiMeal.savory_egg_count === 'number' ? apiMeal.savory_egg_count : undefined,
        baselineCalories: Number(
            /** @type {Record<string, number>} */ (apiMeal.baseline_nutrition ?? {}).calories ?? 0,
        ),
        detailView: {
            nutritionalData,
            nutritionSubheading: 'Adapted for your plan',
            macros: {
                calories: Math.round(calories),
                protein: Math.round(Number(adapted.protein ?? 0) * 10) / 10,
                carbs: Math.round(Number(adapted.carbs ?? 0) * 10) / 10,
                fat: Math.round(Number(adapted.fat ?? 0) * 10) / 10,
            },
            nutrition: adapted,
            ...(ingredientLines.length > 0 ? { ingredients: ingredientLines } : {}),
        },
    };
}

/** @typedef {'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup'} ConsultationCategoryKey */

/**
 * @param {Array<{ slot?: string }>} meals
 * @returns {Partial<Record<ConsultationCategoryKey, typeof meals>>}
 */
export function groupConsultationMealsByCategory(meals) {
    /** @type {Partial<Record<ConsultationCategoryKey, typeof meals>>} */
    const grouped = {
        breakfasts: [],
        meals: [],
        sideSalads: [],
        desserts: [],
        soup: [],
    };

    for (const meal of meals ?? []) {
        switch (meal?.slot) {
            case 'breakfast':
                grouped.breakfasts?.push(meal);
                break;
            case 'main':
                grouped.meals?.push(meal);
                break;
            case 'side_salad':
                grouped.sideSalads?.push(meal);
                break;
            case 'dessert':
                grouped.desserts?.push(meal);
                break;
            case 'soup':
                grouped.soup?.push(meal);
                break;
            default:
                break;
        }
    }

    return grouped;
}

/**
 * @param {Record<string, unknown>} payload
 */
export function mapAdaptedMenuPayloadToConsultationMeals(payload) {
    const scalable = Array.isArray(payload.scalable_meals) ? payload.scalable_meals : [];
    const fixed = Array.isArray(payload.fixed_portion_meals) ? payload.fixed_portion_meals : [];
    const optional = Array.isArray(payload.optional_add_on_meals) ? payload.optional_add_on_meals : [];

    return [...scalable, ...fixed, ...optional].map((meal) =>
        mapAdaptedApiMealToConsultationMeal(/** @type {Record<string, unknown>} */ (meal)),
    );
}

/**
 * Admin-assigned soups for a weekday (1=Sun … 7=Sat) from the production weekly plan.
 *
 * @param {Record<string | number, unknown>} scheduledSoupsByWeekday
 * @param {number} dayOfWeek
 */
export function scheduledSoupConsultationMealsForDay(scheduledSoupsByWeekday, dayOfWeek) {
    const raw =
        scheduledSoupsByWeekday?.[dayOfWeek] ?? scheduledSoupsByWeekday?.[String(dayOfWeek)] ?? null;

    if (!raw) {
        return [];
    }

    if (Array.isArray(raw)) {
        return raw
            .filter((entry) => entry && typeof entry === 'object')
            .map((entry) => mapAdaptedApiMealToConsultationMeal(/** @type {Record<string, unknown>} */ (entry)));
    }

    if (typeof raw === 'object') {
        return [mapAdaptedApiMealToConsultationMeal(/** @type {Record<string, unknown>} */ (raw))];
    }

    return [];
}

/** @typedef {'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup'} FullCraftCategoryKey */

/**
 * @param {Record<string | number, unknown>} schedule
 * @param {number} dayOfWeek
 */
export function scheduledFullCraftDayPayload(schedule, dayOfWeek) {
    const raw = schedule?.[dayOfWeek] ?? schedule?.[String(dayOfWeek)] ?? null;

    return raw && typeof raw === 'object' ? /** @type {Record<string, unknown>} */ (raw) : null;
}

/**
 * @param {Record<string | number, unknown>} schedule
 * @param {number} dayOfWeek
 */
export function scheduledFullCraftSelectionsForDay(schedule, dayOfWeek) {
    const day = scheduledFullCraftDayPayload(schedule, dayOfWeek);

    if (!day) {
        return null;
    }

    /** @param {unknown} list */
    const ids = (list) =>
        (Array.isArray(list) ? list : [])
            .filter((entry) => entry && typeof entry === 'object' && 'id' in entry)
            .map((entry) => String(/** @type {{ id: unknown }} */ (entry).id));

    return {
        breakfasts: ids(day.breakfasts),
        meals: ids(day.meals),
        sideSalads: ids(day.sideSalads),
        desserts: ids(day.desserts),
        soup: ids(day.soup),
    };
}

/**
 * @param {Record<string | number, unknown>} schedule
 * @param {number} dayOfWeek
 * @returns {Partial<Record<FullCraftCategoryKey, ReturnType<typeof mapAdaptedApiMealToConsultationMeal>[]>> | null}
 */
export function scheduledFullCraftCategoryMealsForDay(schedule, dayOfWeek) {
    const day = scheduledFullCraftDayPayload(schedule, dayOfWeek);

    if (!day) {
        return null;
    }

    /** @param {unknown} list */
    const mapList = (list) =>
        (Array.isArray(list) ? list : [])
            .filter((entry) => entry && typeof entry === 'object')
            .map((entry) => mapAdaptedApiMealToConsultationMeal(/** @type {Record<string, unknown>} */ (entry)));

    return {
        breakfasts: mapList(day.breakfasts),
        meals: mapList(day.meals),
        sideSalads: mapList(day.sideSalads),
        desserts: mapList(day.desserts),
        soup: mapList(day.soup),
    };
}

/**
 * @param {{ includeSoup?: boolean; selectedFixedSlots?: string[]; craftKey?: string; soupCalories?: number; sideSaladCalories?: number; dessertCalories?: number; dayOfWeek?: number; planTier?: number; selectedMainMealIds?: string[] }} [options]
 */
export function buildAdaptedMenuQueryString(options = {}) {
    const params = new URLSearchParams();

    if (options.includeSoup) {
        params.set('include_soup', '1');
    }

    if (Array.isArray(options.selectedFixedSlots) && options.selectedFixedSlots.length > 0) {
        for (const slot of options.selectedFixedSlots) {
            params.append('selected_fixed_slots[]', slot);
        }
    }
    if (Array.isArray(options.selectedMainMealIds) && options.selectedMainMealIds.length > 0) {
        for (const mealId of options.selectedMainMealIds) {
            params.append('selected_main_meal_ids[]', String(mealId));
        }
    }
    if (typeof options.soupCalories === 'number' && options.soupCalories > 0) {
        params.set('soup_calories', String(Math.round(options.soupCalories)));
    }
    if (typeof options.sideSaladCalories === 'number' && options.sideSaladCalories > 0) {
        params.set('side_salad_calories', String(Math.round(options.sideSaladCalories)));
    }
    if (typeof options.dessertCalories === 'number' && options.dessertCalories > 0) {
        params.set('dessert_calories', String(Math.round(options.dessertCalories)));
    }
    if (typeof options.dayOfWeek === 'number' && options.dayOfWeek >= 1 && options.dayOfWeek <= 7) {
        params.set('day_of_week', String(Math.round(options.dayOfWeek)));
    }
    if (options.craftKey) {
        params.set('craft_key', options.craftKey);
    }
    if (typeof options.planTier === 'number' && options.planTier > 0) {
        params.set('plan_tier', String(Math.round(options.planTier)));
    }

    return params.toString();
}

/**
 * @param {string} url
 * @param {{ includeSoup?: boolean; craftKey?: string; soupCalories?: number; sideSaladCalories?: number; dessertCalories?: number; dayOfWeek?: number; planTier?: number; selectedMainMealIds?: string[] }} [options]
 */
export async function fetchAdaptedMenu(url, options = {}) {
    const query = buildAdaptedMenuQueryString(options);
    const suffix = query ? `?${query}` : '';
    const response = await fetch(`${url}${suffix}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        if (response.status === 401) {
            throw new Error('Your session expired. Refresh the page and log in again to load your meals.');
        }
        const message =
            typeof body.message === 'string'
                ? body.message
                : `Could not load adapted menu (${response.status})`;
        throw new Error(message);
    }

    return /** @type {Record<string, unknown>} */ (await response.json());
}
