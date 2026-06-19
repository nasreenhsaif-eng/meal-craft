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

/**
 * @param {Record<string, unknown>} apiMeal
 */
export function mapAdaptedApiMealToConsultationMeal(apiMeal) {
    const slot = String(apiMeal.slot ?? '');
    const label = mealTypeLabelForSlot(slot);
    const adapted = /** @type {Record<string, number>} */ (apiMeal.adapted_nutrition ?? {});
    const calories = Number(adapted.calories ?? 0);

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
        baselineCalories: Number(
            /** @type {Record<string, number>} */ (apiMeal.baseline_nutrition ?? {}).calories ?? 0,
        ),
    };
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
 * @param {string} url
 * @param {{ includeSoup?: boolean; craftKey?: string }} [options]
 */
export async function fetchAdaptedMenu(url, options = {}) {
    const params = new URLSearchParams();
    if (options.includeSoup) {
        params.set('include_soup', '1');
    }
    if (options.craftKey) {
        params.set('craft_key', options.craftKey);
    }
    const query = params.toString() ? `?${params.toString()}` : '';
    const response = await fetch(`${url}${query}`, {
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
