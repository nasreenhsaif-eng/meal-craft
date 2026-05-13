import { unparse } from 'papaparse';
import type { CyclePhase } from '../Components/Molecules/MealDetailView/CyclePhaseTag';

/**
 * Must match {@see App\Services\MealCraftMasterCsvExport::HEADERS}.
 */
export const MEAL_CRAFT_MASTER_CSV_HEADERS = [
    'Meal Name',
    'Description',
    'Meal Plan Tags (comma or pipe separated; e.g. Balanced | Ketogenic)',
    'Cycle Phase (comma or pipe separated; Menstrual, Follicular, Ovulatory, or Luteal — values or labels)',
    'Dietary Tags',
    'Safety Alerts',
    'Ingredients',
    'Instructions',
    'Photo URL',
    'Target Calories (kcal)',
    'Target Protein (g)',
    'Target Fat (g)',
    'Target Net Carbs (g)',
    'Calculated Calories',
    'Calculated Protein',
    'Calculated Fat',
    'Calculated Net Carbs',
    'Variance Notes',
] as const;

export const MEAL_CRAFT_MASTER_MISSING_PHOTO_PLACEHOLDER = 'NO_PHOTO_URL';

const CANONICAL_PHASE_LABELS: readonly CyclePhase[] = ['Menstrual', 'Follicular', 'Ovulatory', 'Luteal'] as const;

const API_PHASE_TO_LABEL: Record<string, CyclePhase> = {
    menstrual: 'Menstrual',
    follicular: 'Follicular',
    ovulatory: 'Ovulatory',
    luteal: 'Luteal',
};

export type MealCraftMasterCsvMeal = {
    title?: string;
    name?: string;
    /** Short teaser / highlight (maps to Description column). */
    highlight?: string | null;
    /** Full instructions / body (maps to Instructions column). */
    description?: string | null;
    /** API enum values (e.g. `follicular`) or English labels; multiple supported. */
    cyclePhases?: readonly string[] | null;
    /** API enum value (e.g. `follicular`) or canonical label — used when `cyclePhases` is absent. */
    cyclePhase?: string | null;
    /** Multiple canonical meal-plan tags. */
    mealPlanTags?: readonly string[] | null;
    mealPlanTag?: string | null;
    dietaryTags?: string[];
    safetyAlertTags?: string[];
    imageUrl?: string | null;
    ingredients?: Array<{ name?: string; grams?: number; amountGrams?: number }>;
    macros?: {
        calories?: number;
        protein?: number;
        carbs?: number;
        fat?: number;
        fiber?: number;
    };
    targetCalories?: number | null;
    targetProtein?: number | null;
    targetFat?: number | null;
    targetNetCarbs?: number | null;
};

/** Alias for spreadsheet export payloads (see {@link exportMealDataToCSV}). */
export type Meal = MealCraftMasterCsvMeal;

/** Local `YYYY-MM-DD` for filenames (e.g. `meal-craft-export-2026-05-13.csv`). */
export function mealCraftExportDatePart(at: Date = new Date()): string {
    const y = at.getFullYear();
    const m = String(at.getMonth() + 1).padStart(2, '0');
    const d = String(at.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

export function buildMealCraftExportFilename(at: Date = new Date()): string {
    return `meal-craft-export-${mealCraftExportDatePart(at)}.csv`;
}

/**
 * Triggers a browser download of the Meal Craft master CSV using a Blob (UTF-8).
 * Filename defaults to {@link buildMealCraftExportFilename}.
 */
export function downloadMealCraftExportCsv(meals: Meal[], options?: { filename?: string; at?: Date }): void {
    if (typeof document === 'undefined') {
        return;
    }

    const csv = exportMealDataToCSV(meals);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = options?.filename ?? buildMealCraftExportFilename(options?.at);
    anchor.rel = 'noopener';
    anchor.click();
    URL.revokeObjectURL(objectUrl);
}

/**
 * Only the four supported phases are emitted; values such as "Metabolic Reset" become empty.
 */
export function normalizeCyclePhaseForMealCraftCsv(raw: unknown): string {
    if (typeof raw !== 'string') {
        return '';
    }
    const t = raw.trim();
    if (t === '' || /^metabolic\s+reset$/i.test(t)) {
        return '';
    }
    if ((CANONICAL_PHASE_LABELS as readonly string[]).includes(t)) {
        return t;
    }
    const mapped = API_PHASE_TO_LABEL[t.toLowerCase()];
    return mapped ?? '';
}

function mealPlanTagsCell(meal: MealCraftMasterCsvMeal): string {
    const labels = new Set<string>();
    if (Array.isArray(meal.mealPlanTags)) {
        for (const t of meal.mealPlanTags) {
            if (typeof t === 'string' && t.trim() !== '') {
                labels.add(t.trim());
            }
        }
    }
    const single = typeof meal.mealPlanTag === 'string' ? meal.mealPlanTag.trim() : '';
    if (single !== '') {
        labels.add(single);
    }
    return [...labels].sort().join(', ');
}

function cyclePhasesCell(meal: MealCraftMasterCsvMeal): string {
    const labels: CyclePhase[] = [];
    if (Array.isArray(meal.cyclePhases)) {
        for (const p of meal.cyclePhases) {
            const n = normalizeCyclePhaseForMealCraftCsv(String(p));
            if (n !== '') {
                labels.push(n as CyclePhase);
            }
        }
    } else {
        const n = normalizeCyclePhaseForMealCraftCsv(meal.cyclePhase ?? '');
        if (n !== '') {
            labels.push(n as CyclePhase);
        }
    }
    return [...new Set(labels)].join(' | ');
}

function dietaryTagsCell(meal: MealCraftMasterCsvMeal): string {
    const labels = new Set<string>();
    const diet = Array.isArray(meal.dietaryTags) ? meal.dietaryTags : [];
    for (const tag of diet) {
        if (typeof tag === 'string' && tag.trim() !== '') {
            labels.add(tag.trim());
        }
    }
    return [...labels].sort().join(', ');
}

function ingredientsCell(meal: MealCraftMasterCsvMeal): string {
    const rows = Array.isArray(meal.ingredients) ? meal.ingredients : [];
    const parts: string[] = [];
    for (const row of rows) {
        const name = typeof row.name === 'string' ? row.name.trim() : '';
        const g = row.grams ?? row.amountGrams;
        const grams = typeof g === 'number' && Number.isFinite(g) && g > 0 ? g : 0;
        if (name === '' || grams <= 0) {
            continue;
        }
        parts.push(`${name} (${grams}g)`);
    }
    return parts.join(' | ');
}

function photoUrlForMeal(meal: MealCraftMasterCsvMeal): string {
    const u = meal.imageUrl;
    if (typeof u === 'string' && u.trim() !== '') {
        return u.trim();
    }
    return MEAL_CRAFT_MASTER_MISSING_PHOTO_PLACEHOLDER;
}

function formatFloat(value: number): string {
    const s = (Math.round(value * 100) / 100).toFixed(2).replace(/\.?0+$/, '');
    return s === '' ? '0' : s;
}

export function formatMealCraftVarianceNotes(
    targets: {
        calories?: number | null;
        protein?: number | null;
        fat?: number | null;
        netCarbs?: number | null;
    },
    calculated: { calories: number; protein: number; fat: number; netCarbs: number },
): string {
    const parts: string[] = [];
    const pairs: Array<[string, number | null | undefined, number]> = [
        ['kcal', targets.calories, calculated.calories],
        ['protein_g', targets.protein, calculated.protein],
        ['fat_g', targets.fat, calculated.fat],
        ['net_carbs_g', targets.netCarbs, calculated.netCarbs],
    ];
    for (const [key, t, c] of pairs) {
        if (t === null || t === undefined || !Number.isFinite(t)) {
            continue;
        }
        parts.push(`${key}: ${formatFloat(t - c)}`);
    }
    return parts.join('; ');
}

function calculatedFromMeal(meal: MealCraftMasterCsvMeal): { calories: number; protein: number; fat: number; netCarbs: number } {
    const m = meal.macros ?? {};
    const calories = Number(m.calories);
    const protein = Number(m.protein);
    const fat = Number(m.fat);
    const carbs = Number(m.carbs);
    const fiber = Number(m.fiber);
    const cals = Number.isFinite(calories) ? calories : 0;
    const p = Number.isFinite(protein) ? protein : 0;
    const f = Number.isFinite(fat) ? fat : 0;
    const c = Number.isFinite(carbs) ? carbs : 0;
    const fib = Number.isFinite(fiber) ? fiber : 0;
    const net = Math.max(0, c - fib);

    return { calories: cals, protein: p, fat: f, netCarbs: net };
}

function rowStringsForMeal(meal: MealCraftMasterCsvMeal): string[] {
    const title = (meal.title ?? meal.name ?? '').trim();
    const highlight = typeof meal.highlight === 'string' ? meal.highlight : '';
    const description = typeof meal.description === 'string' ? meal.description : '';
    const phase = cyclePhasesCell(meal);
    const calc = calculatedFromMeal(meal);
    const tCal = meal.targetCalories;
    const tProt = meal.targetProtein;
    const tFat = meal.targetFat;
    const tNet = meal.targetNetCarbs;
    const variance = formatMealCraftVarianceNotes(
        { calories: tCal, protein: tProt, fat: tFat, netCarbs: tNet },
        calc,
    );

    return [
        title,
        highlight,
        mealPlanTagsCell(meal),
        phase,
        dietaryTagsCell(meal),
        (Array.isArray(meal.safetyAlertTags) ? meal.safetyAlertTags : [])
            .filter((s): s is string => typeof s === 'string' && s.trim() !== '')
            .sort()
            .join(', '),
        ingredientsCell(meal),
        description,
        photoUrlForMeal(meal),
        tCal !== null && tCal !== undefined && Number.isFinite(tCal) ? formatFloat(tCal) : '',
        tProt !== null && tProt !== undefined && Number.isFinite(tProt) ? formatFloat(tProt) : '',
        tFat !== null && tFat !== undefined && Number.isFinite(tFat) ? formatFloat(tFat) : '',
        tNet !== null && tNet !== undefined && Number.isFinite(tNet) ? formatFloat(tNet) : '',
        formatFloat(calc.calories),
        formatFloat(calc.protein),
        formatFloat(calc.fat),
        formatFloat(calc.netCarbs),
        variance,
    ];
}

/**
 * Maps internal meal objects to the Meal Craft master CSV (UTF-8, comma-separated, RFC 4180 quoting via Papa Parse).
 */
export function exportMealDataToCSV(meals: Meal[]): string {
    return unparse(
        {
            fields: [...MEAL_CRAFT_MASTER_CSV_HEADERS],
            data: meals.map((meal) => rowStringsForMeal(meal)),
        },
        {
            newline: '\r\n',
            header: true,
            escapeFormulae: true,
        },
    );
}
