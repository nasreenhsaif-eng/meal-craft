export const CONSULTATION_DRAFT_STORAGE_KEY = 'mealcraft:consultation-draft';

const DRAFT_VERSION = 1;

/**
 * @typedef {object} ConsultationDaySelections
 * @property {string[]} breakfasts
 * @property {string[]} meals
 * @property {string[]} sideSalads
 * @property {string[]} desserts
 * @property {string[]} soup
 */

/**
 * @typedef {object} ConsultationDraft
 * @property {number} version
 * @property {number} [savedAt]
 * @property {string} craftKey
 * @property {number} weekDuration
 * @property {number[]} selectedDays
 * @property {Record<number, ConsultationDaySelections>} selectedByDay
 * @property {Record<number, 'soup'|'sidesalad'|'dessert'>} [businessSideChoiceByDay]
 * @property {Record<number, boolean>} [afternoonSoupOptInByDay]
 * @property {boolean} [environmentYes]
 */

/**
 * @param {unknown} value
 * @returns {string[]}
 */
function mealIdsFromCategory(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((entry) => entry && typeof entry === 'object' && 'id' in entry)
        .map((entry) => String(/** @type {{ id: unknown }} */ (entry).id))
        .filter((id) => id !== '');
}

/**
 * @param {ConsultationDaySelections | undefined} selections
 * @returns {'soup'|'sidesalad'|'dessert'}
 */
function inferBusinessSideChoice(selections) {
    if ((selections?.soup?.length ?? 0) > 0) {
        return 'soup';
    }

    if ((selections?.desserts?.length ?? 0) > 0) {
        return 'dessert';
    }

    if ((selections?.sideSalads?.length ?? 0) > 0) {
        return 'sidesalad';
    }

    return 'soup';
}

/**
 * @param {ConsultationDraft | null} draft
 * @returns {ConsultationDraft | null}
 */
function normalizeConsultationDraft(draft) {
    if (!draft || typeof draft !== 'object') {
        return null;
    }

    const craftKey = typeof draft.craftKey === 'string' ? draft.craftKey : '';
    const weekDuration = Number(draft.weekDuration);

    if (craftKey === '' || !Number.isFinite(weekDuration) || weekDuration <= 0) {
        return null;
    }

    /** @type {number[]} */
    const selectedDays = Array.isArray(draft.selectedDays)
        ? draft.selectedDays
              .map((day) => Number(day))
              .filter((day) => Number.isFinite(day) && day >= 1 && day <= 7)
              .sort((a, b) => a - b)
        : [];

    if (selectedDays.length === 0) {
        return null;
    }

    /** @type {Record<number, ConsultationDaySelections>} */
    const selectedByDay = {};

    if (draft.selectedByDay && typeof draft.selectedByDay === 'object') {
        for (const [dayKey, selections] of Object.entries(draft.selectedByDay)) {
            const dayNumber = Number(dayKey);
            if (!Number.isFinite(dayNumber) || dayNumber < 1 || dayNumber > 7) {
                continue;
            }

            if (!selections || typeof selections !== 'object') {
                continue;
            }

            selectedByDay[dayNumber] = {
                breakfasts: Array.isArray(selections.breakfasts)
                    ? selections.breakfasts.map(String)
                    : [],
                meals: Array.isArray(selections.meals) ? selections.meals.map(String) : [],
                sideSalads: Array.isArray(selections.sideSalads)
                    ? selections.sideSalads.map(String)
                    : [],
                desserts: Array.isArray(selections.desserts)
                    ? selections.desserts.map(String)
                    : [],
                soup: Array.isArray(selections.soup) ? selections.soup.map(String) : [],
            };
        }
    }

    return {
        version: DRAFT_VERSION,
        craftKey,
        weekDuration,
        selectedDays,
        selectedByDay,
        businessSideChoiceByDay:
            draft.businessSideChoiceByDay && typeof draft.businessSideChoiceByDay === 'object'
                ? /** @type {Record<number, 'soup'|'sidesalad'|'dessert'>} */ (draft.businessSideChoiceByDay)
                : {},
        afternoonSoupOptInByDay:
            draft.afternoonSoupOptInByDay && typeof draft.afternoonSoupOptInByDay === 'object'
                ? /** @type {Record<number, boolean>} */ (draft.afternoonSoupOptInByDay)
                : {},
        environmentYes: Boolean(draft.environmentYes),
    };
}

/**
 * @returns {boolean}
 */
export function shouldRestoreConsultationDraft() {
    if (typeof window === 'undefined') {
        return false;
    }

    return new URLSearchParams(window.location.search).get('edit') === '1';
}

/**
 * @returns {ConsultationDraft | null}
 */
export function loadConsultationDraft() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = sessionStorage.getItem(CONSULTATION_DRAFT_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object' || parsed.version !== DRAFT_VERSION) {
            return null;
        }

        if (typeof parsed.craftKey !== 'string') {
            return null;
        }

        return normalizeConsultationDraft(/** @type {ConsultationDraft} */ (parsed));
    } catch {
        return null;
    }
}

/**
 * @param {Omit<ConsultationDraft, 'version' | 'savedAt'>} draft
 */
export function saveConsultationDraft(draft) {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(
        CONSULTATION_DRAFT_STORAGE_KEY,
        JSON.stringify({
            ...draft,
            version: DRAFT_VERSION,
            savedAt: Date.now(),
        }),
    );
}

export function clearConsultationDraft() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.removeItem(CONSULTATION_DRAFT_STORAGE_KEY);
}

/**
 * Build a restorable draft from the meal-plan summary payload.
 *
 * @param {{
 *   craftKey?: string;
 *   weekDuration?: number;
 *   days?: Array<{
 *     dayNumber: number;
 *     includeSoup?: boolean;
 *     categories?: Record<string, unknown[]>;
 *   }>;
 * }} craftPlan
 * @returns {ConsultationDraft | null}
 */
export function buildConsultationDraftFromSummary(craftPlan) {
    const craftKey = typeof craftPlan?.craftKey === 'string' ? craftPlan.craftKey : '';
    const weekDuration = Number(craftPlan?.weekDuration ?? 0);
    const days = Array.isArray(craftPlan?.days) ? craftPlan.days : [];

    if (craftKey === '' || weekDuration <= 0 || days.length === 0) {
        return null;
    }

    /** @type {Record<number, ConsultationDaySelections>} */
    const selectedByDay = {};
    /** @type {Record<number, 'soup'|'sidesalad'|'dessert'>} */
    const businessSideChoiceByDay = {};
    /** @type {Record<number, boolean>} */
    const afternoonSoupOptInByDay = {};

    for (const day of days) {
        const dayNumber = Number(day.dayNumber);
        if (!Number.isFinite(dayNumber) || dayNumber < 1 || dayNumber > 7) {
            continue;
        }

        const categories = day.categories ?? {};
        const selections = {
            breakfasts: mealIdsFromCategory(categories.breakfasts),
            meals: mealIdsFromCategory(categories.meals),
            sideSalads: mealIdsFromCategory(categories.sideSalads),
            desserts: mealIdsFromCategory(categories.desserts),
            soup: mealIdsFromCategory(categories.soup),
        };

        selectedByDay[dayNumber] = selections;
        businessSideChoiceByDay[dayNumber] = inferBusinessSideChoice(selections);
        afternoonSoupOptInByDay[dayNumber] =
            Boolean(day.includeSoup) || selections.soup.length > 0;
    }

    /** @type {number[]} */
    const selectedDays =
        Array.isArray(craftPlan.selectedWeekdays) && craftPlan.selectedWeekdays.length > 0
            ? [...craftPlan.selectedWeekdays]
                  .map((day) => Number(day))
                  .filter((day) => Number.isFinite(day) && day >= 1 && day <= 7)
                  .sort((a, b) => a - b)
            : Object.keys(selectedByDay)
                  .map((day) => Number(day))
                  .filter((day) => Number.isFinite(day))
                  .sort((a, b) => a - b);

    if (selectedDays.length === 0) {
        return null;
    }

    return normalizeConsultationDraft({
        version: DRAFT_VERSION,
        craftKey,
        weekDuration,
        selectedDays,
        selectedByDay,
        businessSideChoiceByDay,
        afternoonSoupOptInByDay,
        environmentYes: false,
    });
}

/**
 * Resolve the draft to restore when opening consultation in edit mode.
 *
 * @param {{
 *   craftKey?: string;
 *   weekDuration?: number;
 *   selectedWeekdays?: number[];
 *   days?: Array<{
 *     dayNumber: number;
 *     includeSoup?: boolean;
 *     categories?: Record<string, unknown[]>;
 *   }>;
 * } | null | undefined} [initialEditDraft]
 * @returns {ConsultationDraft | null}
 */
export function resolveInitialConsultationRestoreDraft(initialEditDraft) {
    if (initialEditDraft) {
        return buildConsultationDraftFromSummary(initialEditDraft);
    }

    if (!shouldRestoreConsultationDraft()) {
        return null;
    }

    return loadConsultationDraft();
}

/**
 * @param {string} consultationUrl
 */
export function navigateToConsultationEdit(consultationUrl) {
    const base = consultationUrl.split('?')[0] ?? consultationUrl;
    window.location.assign(`${base}?edit=1`);
}

/**
 * @param {string} consultationUrl
 * @param {{
 *   craftKey?: string;
 *   weekDuration?: number;
 *   days?: Array<{
 *     dayNumber: number;
 *     includeSoup?: boolean;
 *     categories?: Record<string, unknown[]>;
 *   }>;
 * }} craftPlan
 */
export function saveSummaryCraftPlanAndNavigateToEdit(consultationUrl, craftPlan) {
    const draft = buildConsultationDraftFromSummary(craftPlan);
    if (!draft) {
        window.location.assign(consultationUrl);
        return;
    }

    saveConsultationDraft(draft);
    navigateToConsultationEdit(consultationUrl);
}
