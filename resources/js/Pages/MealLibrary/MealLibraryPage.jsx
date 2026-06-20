import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, Fragment } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { router, usePage } from '@inertiajs/react';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import DropdownTextInput from '../../Components/Atoms/TextInput/DropdownTextInput.jsx';
import MultiPillDropdown from '../../Components/Atoms/TextInput/MultiPillDropdown.jsx';
import Button from '../../Components/Atoms/Button.jsx';
import PillButton from '../../Components/Atoms/Button/Button.jsx';
import MealCard from '../../Components/MealCard.jsx';
import MealDetailView from '../../Components/Molecules/MealDetailView/MealDetailView';
import MealLibrarySortableTable from '../../Components/MealLibrary/MealLibrarySortableTable.jsx';
import CSVUploader from '../../Components/CSVUploader.jsx';
import RoundIconButton from '../../Components/Atoms/Icons/RoundIconButton.jsx';
import { IconLayoutGrid, IconLayoutList } from '../../Components/Atoms/SvgIcons.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import { aggregateNutritionFromIngredientRows, normalizeIngredientKey } from '../../meal-library/aggregateIngredientNutrition.ts';
import { calculateMealNutrition, calorieWarningsForCategory, resolveMealLibraryCategory } from '../../meal-library/calculateMealNutrition.ts';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';
import { gramsFromIngredientAmountAndUnit, parseIngredientQuantityString } from '../../meal-library/ingredientQuantityString.ts';
import { buildIngredientPasteHighlightParts } from '../../meal-library/ingredientPasteHighlight.ts';
import { filterIngredientsForCombobox } from '../../meal-library/ingredientSearch.ts';
import {
    cyclePhasesFromPage,
    mealLibraryUrls,
    resolveUrl,
} from '../../meal-craft/mealCraftPageProps.js';
import {
    collectSafetyAlertLabelsFromIngredientSelection,
    G6PD_HIGHLIGHT_BADGE,
    G6PD_TRIGGER_SAFETY_LABEL,
    mealHasG6pdTriggerInEditor,
    sickleCellHighlightBadgeLabels,
} from '../../meal-library/mealSafetyAndSickle.ts';
import {
    compressMealPhotoForUpload,
    isMealPhotoCompressible,
    MEAL_PHOTO_UPLOAD_TARGET_BYTES,
} from '../../lib/compressMealPhotoForUpload.js';
import { laravelAxiosJsonHeaders, resolveCsrfToken } from '../../lib/csrfToken.js';
import {
    canonicalDietTagsFromList,
    DIETARY_TAG_OPTIONS,
    MEAL_PLAN_TAG_OPTIONS,
} from '../../meal-library/mealTaxonomy.js';
import { resolvePerServingActualForTargets, scaleNutritionRecord } from '../../meal-library/bulkPlanningVariance.ts';
import { downloadMissingIngredientsCSV } from '../../meal-library/downloadMissingIngredientsCSV.ts';
import { downloadMealCraftCsvTemplate } from '../../meal-library/generateLibraryExportCSV.ts';
import SafetyAlerts from '../../Components/MealSystem/SafetyAlerts.jsx';

const PAGE_BG = 'bg-[#F8F9F6]';
const PAGE_SIZE = 12;

/**
 * @param {unknown} data
 * @returns {object[]}
 */
function normalizeMealCsvImportRowList(list) {
    if (!Array.isArray(list)) {
        return [];
    }
    return list
        .map((r) => {
            if (r && typeof r === 'object') {
                return /** @type {object} */ (r);
            }
            if (typeof r === 'string') {
                try {
                    const parsed = JSON.parse(r);
                    return typeof parsed === 'object' && parsed !== null ? parsed : null;
                } catch {
                    return null;
                }
            }
            return null;
        })
        .filter((r) => r !== null);
}

/**
 * @param {unknown} data
 * @returns {object[]}
 */
function mealCsvImportResponseRows(data) {
    if (!data || typeof data !== 'object') {
        return [];
    }
    const d = /** @type {Record<string, unknown>} */ (data);

    const tryList = (raw) => {
        if (!Array.isArray(raw)) {
            return [];
        }
        return normalizeMealCsvImportRowList(raw);
    };

    const buckets = [tryList(d.rows), tryList(d.Rows), tryList(d.import_rows), tryList(d.results)];

    if (d.data && typeof d.data === 'object') {
        const inner = /** @type {Record<string, unknown>} */ (d.data);
        buckets.push(tryList(inner.rows), tryList(inner.Rows), tryList(inner.import_rows), tryList(inner.results));
    }

    for (const b of buckets) {
        if (b.length > 0) {
            return b;
        }
    }

    for (const v of Object.values(d)) {
        if (!Array.isArray(v) || v.length === 0) {
            continue;
        }
        const first = v[0];
        if (
            first &&
            typeof first === 'object' &&
            ('status' in first || 'line' in first || 'message' in first || 'meal_name' in first || 'Meal_Name' in first)
        ) {
            const out = normalizeMealCsvImportRowList(v);
            if (out.length > 0) {
                return out;
            }
        }
    }

    return [];
}

/**
 * @param {unknown} data
 */
/** @param {Record<string, unknown> | null | undefined} summary */
function mealCsvImportModalHasVisibleOutcome(summary, uniquePending, rows, importErrorLines) {
    if ((Array.isArray(uniquePending) ? uniquePending.length : 0) > 0) {
        return true;
    }
    if ((Array.isArray(importErrorLines) ? importErrorLines.length : 0) > 0) {
        return true;
    }
    if (mealCsvImportMealLibrarySavedCount(summary) > 0) {
        return true;
    }
    if (mealCsvImportIngredientLibrarySavedCount(summary) > 0) {
        return true;
    }
    if ((Number(summary?.pending_ingredient_input) || 0) > 0) {
        return true;
    }
    if ((Number(summary?.errors) || 0) > 0) {
        return true;
    }
    if (Array.isArray(rows)) {
        for (const row of rows) {
            if (mealCsvImportRowNormalizedStatus(row) === 'pending_ingredient_input') {
                return true;
            }
        }
    }

    return false;
}

function buildMealCsvImportModalFromPayload(data) {
    if (!data || typeof data !== 'object') {
        return null;
    }

    const d = /** @type {Record<string, unknown>} */ (data);

    if (d.error === true) {
        return {
            error: typeof d.message === 'string' ? d.message : 'CSV import failed.',
            summary: d.summary && typeof d.summary === 'object' ? d.summary : {},
            uniquePending: Array.isArray(d.unique_pending_ingredients) ? d.unique_pending_ingredients : [],
            rows: mealCsvImportResponseRows(d),
            import_error_lines: Array.isArray(d.import_error_lines) ? d.import_error_lines : [],
            csvUnrecognizedHeaders: Array.isArray(d.csv_unrecognized_headers) ? d.csv_unrecognized_headers : [],
            validationErrors:
                d.validationErrors && typeof d.validationErrors === 'object' && !Array.isArray(d.validationErrors)
                    ? d.validationErrors
                    : null,
        };
    }

    return {
        summary: d.summary && typeof d.summary === 'object' ? d.summary : {},
        uniquePending: Array.isArray(d.unique_pending_ingredients) ? d.unique_pending_ingredients : [],
        rows: mealCsvImportResponseRows(d),
        import_error_lines: Array.isArray(d.import_error_lines) ? d.import_error_lines : [],
        csvUnrecognizedHeaders: Array.isArray(d.csv_unrecognized_headers) ? d.csv_unrecognized_headers : [],
    };
}

/**
 * @param {unknown} row
 */
function mealCsvImportRowNormalizedStatus(row) {
    if (!row || typeof row !== 'object') {
        return '';
    }
    const r = /** @type {Record<string, unknown>} */ (row);
    const raw = r.status ?? r.Status;
    if (raw != null && typeof raw === 'object' && 'value' in /** @type {object} */ (raw)) {
        return String(/** @type {{ value?: unknown }} */ (raw).value ?? '').trim().toLowerCase();
    }

    return String(raw ?? '').trim().toLowerCase();
}

/**
 * @param {unknown} row
 */
function mealCsvImportRowIsError(row) {
    return mealCsvImportRowNormalizedStatus(row) === 'error';
}

/**
 * @param {unknown} row
 */
function mealCsvImportRowMessage(row) {
    if (!row || typeof row !== 'object') {
        return '';
    }
    const r = /** @type {Record<string, unknown>} */ (row);
    const m = r.message ?? r.Message;
    if (typeof m === 'string') {
        return m;
    }
    if (m != null) {
        return String(m);
    }
    return '';
}

/**
 * @param {{ import_error_lines?: unknown; rows?: unknown[] }} modal
 * @returns {string[]}
 */
function mealCsvImportModalErrorDisplayLines(modal) {
    const fromApi = modal?.import_error_lines;
    if (Array.isArray(fromApi) && fromApi.length > 0) {
        return fromApi.map((x) => String(x));
    }
    const rows = Array.isArray(modal?.rows) ? modal.rows : [];
    const out = [];
    for (const row of rows) {
        if (!mealCsvImportRowIsError(row)) {
            continue;
        }
        const m = mealCsvImportRowMessage(row);
        if (!m) {
            continue;
        }
        const lineRaw = /** @type {{ line?: unknown; meal_name?: unknown }} */ (row).line;
        const lineNum =
            typeof lineRaw === 'number' && Number.isFinite(lineRaw)
                ? lineRaw
                : Number.parseInt(String(lineRaw ?? ''), 10);
        const lineLabel = Number.isFinite(lineNum) ? lineNum : 0;
        const nameRaw = /** @type {{ meal_name?: unknown }} */ (row).meal_name;
        const name = typeof nameRaw === 'string' && nameRaw.trim() !== '' ? nameRaw.trim() : '';
        out.push(name !== '' ? `Line ${lineLabel} (${name}): ${m}` : `Line ${lineLabel}: ${m}`);
    }

    return out;
}

/** @param {Record<string, unknown> | null | undefined} summary */
function mealCsvImportMealLibrarySavedCount(summary) {
    if (!summary || typeof summary !== 'object') {
        return 0;
    }
    return (Number(summary.imported) || 0) + (Number(summary.updated) || 0);
}

/** @param {Record<string, unknown> | null | undefined} summary */
function mealCsvImportIngredientLibrarySavedCount(summary) {
    if (!summary || typeof summary !== 'object') {
        return 0;
    }
    return (Number(summary.ingredient_library_imported) || 0) + (Number(summary.ingredient_library_updated) || 0);
}

/**
 * @param {{ rows?: unknown[] } | null | undefined} modal
 * @returns {string | null}
 */
function firstMealLibrarySavedNameFromImportModal(modal) {
    if (!modal?.rows || !Array.isArray(modal.rows)) {
        return null;
    }

    for (const row of modal.rows) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        const status = mealCsvImportRowNormalizedStatus(row);
        if (status !== 'imported' && status !== 'updated') {
            continue;
        }
        const r = /** @type {Record<string, unknown>} */ (row);
        const name = typeof r.meal_name === 'string' ? r.meal_name.trim() : '';
        if (name !== '') {
            return name;
        }
    }

    return null;
}

/** @param {unknown[]} rows */
function mealCsvImportPendingMealNames(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }
    const names = [];
    for (const row of rows) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        if (mealCsvImportRowNormalizedStatus(row) !== 'pending_ingredient_input') {
            continue;
        }
        const r = /** @type {Record<string, unknown>} */ (row);
        const name = typeof r.meal_name === 'string' ? r.meal_name.trim() : '';
        if (name !== '') {
            names.push(name);
        }
    }
    return names;
}

/** @param {unknown[]} rows */
function mealCsvImportIngredientLibraryRowNames(rows) {
    if (!Array.isArray(rows)) {
        return [];
    }
    const names = [];
    for (const row of rows) {
        if (!row || typeof row !== 'object') {
            continue;
        }
        const r = /** @type {Record<string, unknown>} */ (row);
        if (r.saved_to === 'ingredient_library' || r.ingredient_id != null) {
            const name = typeof r.meal_name === 'string' ? r.meal_name.trim() : '';
            if (name !== '') {
                names.push(name);
            }
        }
    }
    return names;
}

const MEAL_FORM_TYPE_OPTIONS = ['Breakfast', 'Meal', 'Side Salad', 'Soup', 'Dessert'];
const UNIT_OPTIONS = ['g', 'kg', 'ml', 'ltr'];

const DEFAULT_CYCLE_PHASES = [
    { value: 'menstrual', label: 'Menstrual' },
    { value: 'follicular', label: 'Follicular' },
    { value: 'ovulatory', label: 'Ovulatory' },
    { value: 'luteal', label: 'Luteal' },
];

/** @param {{ value: string; label: string }[]} items */
function mealPlanTagOptionsForMulti(items) {
    return items.map((tag) => ({ value: tag, label: tag }));
}

/** @param {{ value: string; label: string }[]} items */
function valueForLabel(items, label) {
    if (!label) {
        return '';
    }
    return items.find((item) => item.label === label)?.value ?? '';
}

function fmtMacroFromNutrition(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) {
        return '';
    }
    const s = (Math.round(n * 10) / 10).toFixed(1);
    return s.replace(/\.0$/, '');
}

/** @returns {number|null} */
function parseBulkServingsCount(raw) {
    const n = Number(String(raw ?? '').trim());
    if (!Number.isFinite(n) || n <= 0) {
        return null;
    }
    return n;
}

/** @returns {number|null} */
function divideBatchByServings(batchStr, servings) {
    const batch = Number(String(batchStr ?? '').trim());
    if (!Number.isFinite(batch) || servings <= 0) {
        return null;
    }
    return batch / servings;
}

function emptyNutritionSidebarShape() {
    return {
        calories: 0,
        protein: 0,
        carbs: 0,
        fat: 0,
        b6: 0,
        b9_folate: 0,
        b12: 0,
        iron: 0,
        magnesium: 0,
        fiber: 0,
        sugar: 0,
        calcium: 0,
        potassium: 0,
        sodium: 0,
        zinc: 0,
        vitamin_c: 0,
        vitamin_a: 0,
        vitamin_e: 0,
        vitamin_d: 0,
        vitamin_k2: 0,
    };
}

/** @returns {number|null} Manual planning target; never auto-filled from ingredients. */
function optionalTargetNumberString(raw) {
    const t = String(raw ?? '').trim();
    if (t === '') {
        return null;
    }
    const n = Number(t);
    if (!Number.isFinite(n) || n < 0) {
        return null;
    }
    return n;
}

function batchMacroDisplayFromPerServing(perServingStr, servings) {
    const per = Number.parseFloat(String(perServingStr ?? '0'));
    if (!Number.isFinite(per) || !Number.isFinite(servings) || servings <= 0) {
        return '';
    }
    return fmtMacroFromNutrition(per * servings);
}

function unitToGrams(amount, unit) {
    const n = Number(amount);
    if (!Number.isFinite(n) || n <= 0) {
        return 0;
    }
    if (unit === 'kg') {
        return n * 1000;
    }
    if (unit === 'ltr') {
        return n * 1000;
    }
    return n;
}

/**
 * Macros persisted as meal ``total_*`` when ingredient-backed nutrition exists (library save source of truth).
 *
 * @param {{ nutrition: Record<string, number>; resolvedLineCount: number }} aggregated
 * @param {{ ok: boolean; nutrition?: Record<string, number> } | null | undefined} csvNutrition
 * @returns {{ calories: number; protein: number; carbs: number; fat: number } | null}
 */
function persistedMacroTotalsFromIngredientSources(aggregated, csvNutrition, isBulkRecipe, bulkServingsCountRaw) {
    const raw =
        aggregated.resolvedLineCount > 0
            ? aggregated.nutrition
            : csvNutrition?.ok
              ? csvNutrition.nutrition
              : null;
    if (!raw || typeof raw.calories !== 'number' || !Number.isFinite(raw.calories)) {
        return null;
    }
    const svc = parseBulkServingsCount(bulkServingsCountRaw);
    const useBulk = Boolean(isBulkRecipe && svc != null);
    const n = useBulk && svc != null ? scaleNutritionRecord(raw, 1 / svc) : raw;
    const num = (v) => (typeof v === 'number' && Number.isFinite(v) ? v : 0);
    return {
        calories: Math.round(num(n.calories)),
        protein: Math.round(num(n.protein) * 100) / 100,
        carbs: Math.round(num(n.carbs) * 100) / 100,
        fat: Math.round(num(n.fat) * 100) / 100,
    };
}

/** @param {string} mealStoreUrl @param {string|number} mealId */
function mealLibraryItemUpdateUrl(mealStoreUrl, mealId) {
    return `${String(mealStoreUrl).replace(/\/?$/, '')}/${mealId}`;
}

/** @param {unknown} errors */
function mealLibraryValidationMessages(errors) {
    /** @type {string[]} */
    const messages = [];
    const walk = (value) => {
        if (typeof value === 'string' && value.trim() !== '') {
            messages.push(value.trim());
            return;
        }
        if (Array.isArray(value)) {
            value.forEach(walk);
            return;
        }
        if (value && typeof value === 'object') {
            Object.values(value).forEach(walk);
        }
    };
    walk(errors);
    return messages;
}

/** @param {Record<string, unknown>} payload */
function mealLibraryRequestPayloadForPost(payload) {
    const prepared = { ...payload };
    for (const key of ['servings_count', 'target_calories', 'target_protein', 'target_carbs', 'target_fat']) {
        if (prepared[key] === null || prepared[key] === undefined) {
            delete prepared[key];
        }
    }
    return prepared;
}

/**
 * @param {string} url
 * @param {Record<string, unknown>} payload
 * @param {{
 *   onSuccess?: () => void;
 *   onError?: (errors: Record<string, string | string[]>) => void;
 *   onSaveMessage?: (message: string | null) => void;
 * }} callbacks
 */
function postMealLibraryForm(url, payload, { onSuccess, onError, onSaveMessage }) {
    const body = mealLibraryRequestPayloadForPost(payload);
    const hasPhoto = body.photo instanceof File;

    router.post(url, body, {
        forceFormData: hasPhoto,
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            onSaveMessage?.(null);
            onSuccess?.();
        },
        onFlash: (flash) => {
            if (flash?.error) {
                const message = String(flash.error);
                onSaveMessage?.(message);
                window.alert(message);
            }
        },
        onError: (errors) => {
            const messages = mealLibraryValidationMessages(errors);
            const message = messages.length > 0 ? messages.join('\n') : 'Could not save the meal. Check the form and try again.';
            onSaveMessage?.(message);
            window.alert(message);
            onError?.(errors);
        },
    });
}

/** Delete selected — ghost/disabled styling (matches Ingredients Library). */
function deleteSelectedButtonClass(anySelected) {
    return [
        'h-[44px] min-h-[44px] rounded-[12px] px-5 text-[13px] transition-colors duration-200',
        'disabled:!cursor-not-allowed disabled:!opacity-100',
        anySelected
            ? '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white disabled:!opacity-100'
            : '!bg-[#C44F5D]/20 !text-[#8A8A8A] hover:!bg-[#C44F5D]/20 hover:!text-[#8A8A8A]',
    ].join(' ');
}

/**
 * @param {{
 *   cyclePhases?: { value: string; label: string }[];
 *   meals?: object[];
 *   ingredientProfiles?: object[];
 *   csvMealCraftTemplateUrl?: string;
 *   csvExportUrl?: string;
 *   csvImportUrl?: string;
 *   mealStoreUrl?: string;
 *   mealBulkDestroyUrl?: string;
 *   mealReorderUrl?: string;
 *   initialViewMode?: 'grid' | 'list';
 *   flashSuccess?: string | null;
 *   flashError?: string | null;
 *   mealCsvImportFlash?: object | null;
 *   pendingMealImports?: string[];
 *   mealLibrarySchemaNotice?: string | null;
 *   csrfToken?: string;
 *   onCreateMealSubmit?: (
 *     payload: Record<string, unknown>,
 *     meta?: { action: 'create' | 'update' | 'duplicate'; mealId?: string },
 * ) => void | Promise<void>;
 *   storyInitialCreateModalOpen?: boolean;
 *   storyInitialMealToEdit?: object | null;
 *   onRowReorder?: (updatedMeals: object[]) => void;
 * }} props
 *
 * Presentational body (no Inertia). Use in Storybook; the live app wraps this via {@link MealLibraryPage}.
 */
export function MealLibraryPageContent({
    cyclePhases = DEFAULT_CYCLE_PHASES,
    meals = [],
    ingredientProfiles = [],
    csvMealCraftTemplateUrl = '',
    csvExportUrl = '#',
    csvImportUrl = '#',
    mealStoreUrl = '#',
    mealBulkDestroyUrl = '',
    mealReorderUrl = '',
    initialViewMode,
    flashSuccess = null,
    flashError = null,
    mealCsvImportFlash = null,
    pendingMealImports = [],
    mealLibrarySchemaNotice = null,
    csrfToken = '',
    onCreateMealSubmit,
    storyInitialCreateModalOpen = false,
    storyInitialMealToEdit = null,
    onRowReorder,
}) {
    const [query, setQuery] = useState('');
    const [mealRows, setMealRows] = useState(meals);
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);
    const [selectedRows, setSelectedRows] = useState(/** @type {string[]} */ ([]));
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deleteBusy, setDeleteBusy] = useState(false);
    const [deleteError, setDeleteError] = useState(/** @type {string | null} */ (null));
    const [mealCsvImportResultModal, setMealCsvImportResultModal] = useState(null);

    useEffect(() => {
        const modal = buildMealCsvImportModalFromPayload(mealCsvImportFlash);
        if (modal) {
            setMealCsvImportResultModal(modal);
            const savedName = firstMealLibrarySavedNameFromImportModal(modal);
            if (savedName) {
                setQuery(savedName);
                setVisibleCount(PAGE_SIZE);
            }
        }
    }, [mealCsvImportFlash]);

    const reloadMealLibraryRows = useCallback(() => {
        void router.reload({
            only: ['meals'],
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const dismissMealCsvImportModal = useCallback(() => {
        const savedName = firstMealLibrarySavedNameFromImportModal(mealCsvImportResultModal);
        setMealCsvImportResultModal(null);
        setQuery(savedName ?? '');
        reloadMealLibraryRows();
    }, [mealCsvImportResultModal, reloadMealLibraryRows]);
    const [createOpen, setCreateOpen] = useState(false);
    const [mealToEdit, setMealToEdit] = useState(/** @type {object | null} */ (storyInitialMealToEdit ?? null));
    const [mealDetailModal, setMealDetailModal] = useState(
        /** @type {null | { title: string; detailView: object }} */ (null),
    );
    const [viewMode, setViewMode] = useState(() => initialViewMode ?? 'grid');

    useEffect(() => {
        setMealRows(meals);
    }, [meals]);

    useEffect(() => {
        if (initialViewMode !== undefined) {
            setViewMode(initialViewMode);
        }
    }, [initialViewMode]);

    useEffect(() => {
        setVisibleCount(PAGE_SIZE);
    }, [query, mealRows]);

    useEffect(() => {
        if (storyInitialCreateModalOpen) {
            setCreateOpen(true);
        }
    }, [storyInitialCreateModalOpen]);

    const [formName, setFormName] = useState('');
    const [formType, setFormType] = useState('Meal');
    const [selectedMealPlanTags, setSelectedMealPlanTags] = useState(/** @type {string[]} */ ([]));
    const [formCalories, setFormCalories] = useState('');
    const [formProtein, setFormProtein] = useState('');
    const [formCarbs, setFormCarbs] = useState('');
    const [formFat, setFormFat] = useState('');
    /** Manual planning targets — always interpreted as per-serving (never scaled by batch or servings). */
    const [targetCaloriesManual, setTargetCaloriesManual] = useState('');
    const [targetProteinManual, setTargetProteinManual] = useState('');
    const [targetCarbsManual, setTargetCarbsManual] = useState('');
    const [targetFatManual, setTargetFatManual] = useState('');
    const [isBulkRecipe, setIsBulkRecipe] = useState(false);
    const [bulkServingsCount, setBulkServingsCount] = useState('');
    const [selectedDietTags, setSelectedDietTags] = useState(/** @type {string[]} */ ([]));
    const [selectedCyclePhaseValues, setSelectedCyclePhaseValues] = useState(/** @type {string[]} */ ([]));
    const [formInstructions, setFormInstructions] = useState('');
    const [formHighlight, setFormHighlight] = useState('');
    const [formPhoto, setFormPhoto] = useState(/** @type {File|null} */ (null));
    const [mealPhotoPreviewUrl, setMealPhotoPreviewUrl] = useState(/** @type {string|null} */ (null));
    const [ingredientRows, setIngredientRows] = useState(
        /** @type {{ nameQuery: string; selectedName: string; ingredientId: number | null; amount: string; unit: string }[]} */ ([
            { nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' },
        ]),
    );
    const [ingredientPasteField, setIngredientPasteField] = useState('');
    const [ingredientPasteMissingLabels, setIngredientPasteMissingLabels] = useState(/** @type {string[]} */ ([]));
    const [ingredientPasteApplyError, setIngredientPasteApplyError] = useState('');
    const [mealSaveError, setMealSaveError] = useState(/** @type {string | null} */ (null));
    const mealPlanMultiOptions = useMemo(() => mealPlanTagOptionsForMulti(MEAL_PLAN_TAG_OPTIONS), []);

    const resetCreateForm = useCallback(() => {
        setFormName('');
        setFormType('Meal');
        setSelectedMealPlanTags([]);
        setFormCalories('');
        setFormProtein('');
        setFormCarbs('');
        setFormFat('');
        setTargetCaloriesManual('');
        setTargetProteinManual('');
        setTargetCarbsManual('');
        setTargetFatManual('');
        setIsBulkRecipe(false);
        setBulkServingsCount('');
        setSelectedDietTags([]);
        setSelectedCyclePhaseValues([]);
        setFormInstructions('');
        setFormHighlight('');
        setFormPhoto(null);
        setMealPhotoPreviewUrl(null);
        setIngredientRows([{ nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' }]);
        setIngredientPasteField('');
        setIngredientPasteMissingLabels([]);
        setIngredientPasteApplyError('');
        setMealSaveError(null);
    }, []);

    const closeCreateModal = useCallback(() => {
        setCreateOpen(false);
        setMealToEdit(null);
    }, []);

    useEffect(() => {
        if (!createOpen || !mealToEdit?.editForm) {
            return;
        }
        const ef = mealToEdit.editForm;
        setFormName(String(ef.name ?? ''));
        setFormType(String(ef.category ?? 'Meal'));
        const mpt = Array.isArray(ef.mealPlanTags) ? ef.mealPlanTags.filter((t) => typeof t === 'string' && t.trim() !== '') : [];
        setSelectedMealPlanTags(mpt.length ? [...mpt] : ef.mealPlanTag ? [String(ef.mealPlanTag)] : []);
        setSelectedDietTags(canonicalDietTagsFromList(Array.isArray(ef.dietTags) ? ef.dietTags : []));
        const cpv = Array.isArray(ef.cyclePhaseValues)
            ? ef.cyclePhaseValues.filter((v) => typeof v === 'string' && v.trim() !== '')
            : [];
        if (cpv.length > 0) {
            setSelectedCyclePhaseValues([...cpv]);
        } else if (ef.cyclePhaseLabel) {
            const v = valueForLabel(cyclePhases, String(ef.cyclePhaseLabel));
            setSelectedCyclePhaseValues(v ? [v] : []);
        } else {
            setSelectedCyclePhaseValues([]);
        }
        setFormInstructions(String(ef.instructions ?? ef.description ?? ''));
        setFormHighlight(String(ef.shortDescription ?? ef.highlight ?? ''));
        const servingsRaw = ef.servingsCount != null && String(ef.servingsCount).trim() !== '' ? Number(ef.servingsCount) : NaN;
        const isBulk = Boolean(ef.isBulk) && Number.isFinite(servingsRaw) && servingsRaw > 0;
        setIsBulkRecipe(isBulk);
        setBulkServingsCount(isBulk ? String(servingsRaw) : '');
        if (isBulk) {
            const svc = servingsRaw;
            setFormCalories(String(Math.round(Number(ef.totalCalories ?? 0) * svc)));
            setFormProtein(batchMacroDisplayFromPerServing(ef.totalProtein, svc));
            setFormCarbs(batchMacroDisplayFromPerServing(ef.totalCarbs, svc));
            setFormFat(batchMacroDisplayFromPerServing(ef.totalFat, svc));
        } else {
            setFormCalories(String(ef.totalCalories ?? ''));
            setFormProtein(String(ef.totalProtein ?? ''));
            setFormCarbs(String(ef.totalCarbs ?? ''));
            setFormFat(String(ef.totalFat ?? ''));
        }
        setTargetCaloriesManual(String(ef.targetCalories ?? ''));
        setTargetProteinManual(String(ef.targetProtein ?? ''));
        setTargetCarbsManual(String(ef.targetCarbs ?? ''));
        setTargetFatManual(String(ef.targetFat ?? ''));
        setFormPhoto(null);
        setMealPhotoPreviewUrl(null);
        const rows =
            Array.isArray(ef.ingredientRows) && ef.ingredientRows.length > 0
                ? ef.ingredientRows.map((r) => ({
                      nameQuery: String(r.nameQuery ?? ''),
                      selectedName: String(r.selectedName ?? ''),
                      ingredientId:
                          r.ingredientId != null && Number.isFinite(Number(r.ingredientId)) ? Number(r.ingredientId) : null,
                      amount: String(r.amount ?? '100'),
                      unit: String(r.unit ?? 'g'),
                  }))
                : [{ nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' }];
        setIngredientRows(rows);
        setIngredientPasteField('');
        setIngredientPasteMissingLabels([]);
        setIngredientPasteApplyError('');
    }, [createOpen, mealToEdit, cyclePhases]);

    useEffect(() => {
        if (!createOpen) {
            resetCreateForm();
        }
    }, [createOpen, resetCreateForm]);

    useEffect(() => {
        return () => {
            if (mealPhotoPreviewUrl) {
                URL.revokeObjectURL(mealPhotoPreviewUrl);
            }
        };
    }, [mealPhotoPreviewUrl]);

    function toggleDietTag(tag) {
        setSelectedDietTags((prev) => (prev.includes(tag) ? prev.filter((t) => t !== tag) : [...prev, tag]));
    }

    const canSave = useMemo(() => {
        const nameOk = formName.trim().length > 0;
        const svc = parseBulkServingsCount(bulkServingsCount);
        const hasResolvedIngredientLines = ingredientRows.some((r) => {
            const grams = unitToGrams(r.amount, r.unit);
            const label = (r.selectedName || r.nameQuery || '').trim();
            return grams > 0 && label.length > 0;
        });
        const ingredientBackedOk = hasResolvedIngredientLines && (!isBulkRecipe || svc != null);
        const cal = Number(formCalories);
        const batchCalOk = formCalories.trim() !== '' && Number.isFinite(cal) && cal >= 0;
        const editingExisting = Boolean(mealToEdit?.editForm?.id);
        if (!nameOk) {
            return false;
        }
        if (editingExisting && batchCalOk && hasResolvedIngredientLines) {
            return !isBulkRecipe || svc != null;
        }
        if (!batchCalOk && !ingredientBackedOk) {
            return false;
        }
        if (!isBulkRecipe) {
            return true;
        }
        return svc != null;
    }, [formName, formCalories, isBulkRecipe, bulkServingsCount, ingredientRows, mealToEdit]);

    const ingredientDatabase = useMemo(
        () =>
            (ingredientProfiles ?? []).map((p) => ({
                id: typeof p.id === 'number' ? p.id : p.id != null ? Number(p.id) : undefined,
                name: p.name,
                common_allergens: Array.isArray(p.common_allergens) ? [...p.common_allergens] : [],
                is_g6pd_trigger: Boolean(p.is_g6pd_trigger),
                calories: p.calories,
                protein: p.protein,
                carbs: p.carbs,
                fat: p.fat,
                b6: p.b6,
                b9_folate: p.b9_folate,
                b12: p.b12,
                iron: p.iron,
                magnesium: p.magnesium,
                micronutrients: p.micronutrients ?? {},
                density: typeof p.density === 'number' && Number.isFinite(p.density) ? p.density : 1,
            })),
        [ingredientProfiles],
    );

    const [activeSuggestRow, setActiveSuggestRow] = useState(/** @type {number|null} */ (null));
    const ingredientSuggestRootRef = useRef(null);
    const ingredientPasteTextareaRef = useRef(/** @type {HTMLTextAreaElement | null} */ (null));
    const ingredientPasteMirrorRef = useRef(/** @type {HTMLPreElement | null} */ (null));
    const [ingredientSuggestRect, setIngredientSuggestRect] = useState(
        /** @type {{ left: number; top: number; width: number } | null} */ (null),
    );

    // Close ingredient suggestion menu when clicking outside.
    // (Poka‑yoke: prevents floating menus over the form.)
    useEffect(() => {
        if (!createOpen) {
            setActiveSuggestRow(null);
        }
    }, [createOpen]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return undefined;
        }
        const onDocMouseDown = (event) => {
            const root = ingredientSuggestRootRef.current;
            if (!root) {
                return;
            }
            const t = event.target;
            if (!(t instanceof Node)) {
                return;
            }
            if (root.contains(t)) {
                return;
            }
            // Suggestions render in a portal on document.body; clicks must not count as "outside".
            if (t.closest('[data-meal-library-ingredient-suggest]')) {
                return;
            }
            setActiveSuggestRow(null);
        };
        document.addEventListener('mousedown', onDocMouseDown);
        return () => document.removeEventListener('mousedown', onDocMouseDown);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }
        if (activeSuggestRow === null) {
            setIngredientSuggestRect(null);
            return undefined;
        }

        const updateRect = () => {
            const el = document.getElementById(`ingredient-combobox-${activeSuggestRow}`);
            if (!el) {
                return;
            }
            const r = el.getBoundingClientRect();
            setIngredientSuggestRect({ left: r.left, top: r.bottom, width: r.width });
        };

        updateRect();
        window.addEventListener('resize', updateRect);
        window.addEventListener('scroll', updateRect, true);

        return () => {
            window.removeEventListener('resize', updateRect);
            window.removeEventListener('scroll', updateRect, true);
        };
    }, [activeSuggestRow]);

    const filteredMeals = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) {
            return mealRows;
        }
        return mealRows.filter((m) => {
            const titleMatch = String(m.title ?? '')
                .toLowerCase()
                .includes(q);
            const mealTypeMatch = String(m.mealType ?? '')
                .toLowerCase()
                .includes(q);
            const categoryMatch = String(m.category ?? '')
                .toLowerCase()
                .includes(q);
            const tagMatch =
                Array.isArray(m.tags) &&
                m.tags.some((t) => String(t.label ?? t ?? '').toLowerCase().includes(q));
            return titleMatch || mealTypeMatch || categoryMatch || tagMatch;
        });
    }, [mealRows, query]);

    const displayedMeals = useMemo(
        () => filteredMeals.slice(0, Math.min(visibleCount, filteredMeals.length)),
        [filteredMeals, visibleCount],
    );

    const selectedSet = useMemo(() => new Set(selectedRows), [selectedRows]);
    const anySelected = selectedRows.length > 0;
    const allVisibleSelected =
        displayedMeals.length > 0 && displayedMeals.every((m) => selectedSet.has(m.id));

    function toggleAllVisible() {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (allVisibleSelected) {
                displayedMeals.forEach((m) => next.delete(m.id));
            } else {
                displayedMeals.forEach((m) => next.add(m.id));
            }
            return Array.from(next);
        });
    }

    function toggleRow(id) {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return Array.from(next);
        });
    }

    const handleMealRowReorder = useCallback(
        (updatedMeals) => {
            setMealRows(updatedMeals);
            onRowReorder?.(updatedMeals);
        },
        [onRowReorder],
    );

    const persistMealRowReorder = useCallback(
        async (updatedMeals) => {
            handleMealRowReorder(updatedMeals);

            if (!mealReorderUrl) {
                return;
            }

            const ids = updatedMeals
                .map((m) => {
                    const n = Number(m?.id);
                    return Number.isInteger(n) && n > 0 ? n : null;
                })
                .filter((id) => id !== null);

            if (ids.length === 0) {
                return;
            }

            try {
                await axios.post(
                    mealReorderUrl,
                    { ids },
                    {
                        headers: {
                            ...laravelAxiosJsonHeaders(csrfToken),
                            'Content-Type': 'application/json',
                        },
                    },
                );
            } catch {
                void router.reload({ only: ['meals'], preserveScroll: true });
            }
        },
        [csrfToken, handleMealRowReorder, mealReorderUrl],
    );

    function handleConfirmDelete() {
        setDeleteError(null);

        if (selectedRows.length === 0) {
            setConfirmOpen(false);
            return;
        }

        const destroyUrl = mealBulkDestroyUrl || '';

        if (!destroyUrl) {
            setDeleteError('Delete is unavailable. Hard-refresh this page (Cmd+Shift+R), then try again.');
            return;
        }

        const ids = selectedRows
            .map((id) => {
                const n = Number(id);
                return Number.isInteger(n) && n > 0 ? n : null;
            })
            .filter((id) => id !== null);

        if (ids.length === 0) {
            setDeleteError('No valid meals were selected.');
            return;
        }

        const deletedIdSet = new Set(ids.map(String));
        setDeleteBusy(true);

        router.post(
            destroyUrl,
            { ids, _token: resolveCsrfToken(csrfToken) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    if (page?.props?.flash?.error) {
                        setDeleteError(String(page.props.flash.error));
                        return;
                    }

                    setConfirmOpen(false);
                    setSelectedRows([]);
                    setMealRows((prev) => prev.filter((m) => !deletedIdSet.has(String(m.id))));
                },
                onError: (errors) => {
                    const message =
                        errors && typeof errors === 'object' && 'message' in errors && typeof errors.message === 'string'
                            ? errors.message
                            : null;
                    setDeleteError(message ?? 'Could not delete meals. Please try again.');
                },
                onFinish: () => {
                    setDeleteBusy(false);
                },
            },
        );
    }

    function buildIngredientsForStore() {
        return ingredientRows
            .map((r) => {
                const name = (r.selectedName || r.nameQuery || '').trim();
                const grams = unitToGrams(r.amount, r.unit);
                if (!name || grams <= 0) {
                    return null;
                }
                const row = {
                    name,
                    amount_grams: Math.round(grams * 100) / 100,
                };
                const ingredientId =
                    r.ingredientId != null && Number.isFinite(Number(r.ingredientId)) ? Number(r.ingredientId) : null;
                if (ingredientId != null) {
                    row.ingredient_id = ingredientId;
                }
                return row;
            })
            .filter(Boolean);
    }

    /**
     * Plain object for Inertia `router.post(..., payload, { forceFormData: true })` so the client
     * builds multipart with a correct boundary (do not set Content-Type manually).
     *
     * @param {'duplicate' | null} submissionContext
     * @returns {Record<string, unknown>}
     */
    function buildMealLibraryRequestPayload(submissionContext) {
        const ingredients = buildIngredientsForStore().map((ing) => {
            const row = { name: ing.name, amount_grams: ing.amount_grams };
            if (ing.ingredient_id != null) {
                row.ingredient_id = ing.ingredient_id;
            }
            return row;
        });

        const servingsDivisor = isBulkRecipe ? parseBulkServingsCount(bulkServingsCount) : null;
        const useBulk = Boolean(isBulkRecipe && servingsDivisor != null);

        const storeTotals = persistedMacroTotalsForStore;

        let totalCalories = Number(formCalories);
        let totalProtein = formProtein.trim() === '' ? 0 : Number(formProtein);
        let totalCarbs = formCarbs.trim() === '' ? 0 : Number(formCarbs);
        let totalFat = formFat.trim() === '' ? 0 : Number(formFat);

        if (storeTotals != null) {
            totalCalories = storeTotals.calories;
            totalProtein = storeTotals.protein;
            totalCarbs = storeTotals.carbs;
            totalFat = storeTotals.fat;
        } else if (useBulk && servingsDivisor != null) {
            const c = divideBatchByServings(formCalories, servingsDivisor);
            const p = divideBatchByServings(formProtein || '0', servingsDivisor);
            const cb = divideBatchByServings(formCarbs || '0', servingsDivisor);
            const f = divideBatchByServings(formFat || '0', servingsDivisor);
            totalCalories = c != null && Number.isFinite(c) ? Math.round(c) : 0;
            totalProtein = p != null && Number.isFinite(p) ? Math.round(p * 100) / 100 : 0;
            totalCarbs = cb != null && Number.isFinite(cb) ? Math.round(cb * 100) / 100 : 0;
            totalFat = f != null && Number.isFinite(f) ? Math.round(f * 100) / 100 : 0;
        }

        const payload = /** @type {Record<string, unknown>} */ ({
            name: formName.trim(),
            total_calories: totalCalories,
            total_protein: totalProtein,
            total_carbs: totalCarbs,
            total_fat: totalFat,
            category: formType,
            instructions: formInstructions,
            short_description: formHighlight,
            description: formInstructions,
            highlight: formHighlight,
            diet_tags: canonicalDietTagsFromList(selectedDietTags),
            is_bulk: useBulk,
            servings_count: useBulk ? servingsDivisor : null,
        });

        if (selectedMealPlanTags.length > 0) {
            payload.meal_plan_tags = selectedMealPlanTags;
        }
        if (selectedCyclePhaseValues.length > 0) {
            payload.cycle_phases = selectedCyclePhaseValues;
        }
        if (ingredients.length > 0) {
            payload.ingredients = ingredients;
        }
        if (formPhoto) {
            payload.photo = formPhoto;
        }
        payload.target_calories = optionalTargetNumberString(targetCaloriesManual);
        payload.target_protein = optionalTargetNumberString(targetProteinManual);
        payload.target_carbs = optionalTargetNumberString(targetCarbsManual);
        payload.target_fat = optionalTargetNumberString(targetFatManual);
        if (submissionContext === 'duplicate') {
            payload.submission_context = 'duplicate';
        }

        return payload;
    }

    async function handleMealPhotoChange(event) {
        const input = event.target;
        const file = input.files?.[0] ?? null;
        input.value = '';

        if (!file) {
            setFormPhoto(null);
            setMealPhotoPreviewUrl(null);

            return;
        }

        try {
            const prepared = await compressMealPhotoForUpload(file);

            if (prepared.size > MEAL_PHOTO_UPLOAD_TARGET_BYTES) {
                const formatHint = isMealPhotoCompressible(file)
                    ? 'Try a smaller image or export it as JPG.'
                    : 'HEIC and AVIF photos must be under about 1.5 MB, or save as JPG/PNG first.';

                window.alert(`This photo is too large to upload (${formatHint})`);
                setFormPhoto(null);
                setMealPhotoPreviewUrl(null);

                return;
            }

            setFormPhoto(prepared);
            setMealPhotoPreviewUrl(URL.createObjectURL(prepared));
        } catch {
            window.alert('Could not process this image. Try another file or save it as JPG.');
            setFormPhoto(null);
            setMealPhotoPreviewUrl(null);
        }
    }

    function clearMealPhotoSelection() {
        setFormPhoto(null);
        setMealPhotoPreviewUrl(null);
    }

    function handleSaveCreateMeal() {
        if (!canSave) {
            return;
        }
        const payload = buildMealLibraryRequestPayload(null);

        if (onCreateMealSubmit) {
            void Promise.resolve(onCreateMealSubmit(payload, { action: 'create' })).then(() => closeCreateModal());
            return;
        }

        postMealLibraryForm(mealStoreUrl, payload, {
            onSuccess: () => closeCreateModal(),
            onSaveMessage: setMealSaveError,
        });
    }

    function handleSaveMealAsNewCopy() {
        if (!canSave) {
            return;
        }
        const payload = buildMealLibraryRequestPayload('duplicate');

        if (onCreateMealSubmit) {
            void Promise.resolve(onCreateMealSubmit(payload, { action: 'duplicate' })).then(() => closeCreateModal());
            return;
        }

        postMealLibraryForm(mealStoreUrl, payload, {
            onSuccess: () => closeCreateModal(),
            onSaveMessage: setMealSaveError,
        });
    }

    function handleUpdateExistingMeal() {
        if (!canSave || !mealToEdit?.editForm?.id) {
            return;
        }
        const payload = buildMealLibraryRequestPayload(null);
        const mealId = String(mealToEdit.editForm.id);

        if (onCreateMealSubmit) {
            void Promise.resolve(onCreateMealSubmit(payload, { action: 'update', mealId })).then(() => closeCreateModal());
            return;
        }

        const updateUrl = mealLibraryItemUpdateUrl(mealStoreUrl, mealId);
        setMealSaveError(null);
        postMealLibraryForm(updateUrl, payload, {
            onSuccess: () => closeCreateModal(),
            onSaveMessage: setMealSaveError,
        });
    }

    const handleApplyIngredientQuantityString = useCallback(() => {
        const raw = ingredientPasteField.replace(/\r\n/g, '\n').trim();
        setIngredientPasteApplyError('');
        if (raw === '') {
            return;
        }
        const segments = parseIngredientQuantityString(raw);
        if (segments.length === 0) {
            setIngredientPasteApplyError(
                'Could not parse any segments. Use Name:100g, Name 100ml, or Name (100ml), separated by | or a new line.',
            );
            return;
        }

        const byNorm = new Map();
        for (const p of ingredientDatabase) {
            const k = normalizeIngredientKey(p.name);
            if (!byNorm.has(k)) {
                byNorm.set(k, p);
            }
        }

        const missing = [];
        const rows = [];
        for (const seg of segments) {
            const key = normalizeIngredientKey(seg.name);
            const ing = byNorm.get(key);
            if (!ing) {
                const label = seg.name.trim();
                if (label !== '' && !missing.includes(label)) {
                    missing.push(label);
                }
                continue;
            }
            const id = typeof ing.id === 'number' && Number.isFinite(ing.id) ? ing.id : null;
            const density = typeof ing.density === 'number' && ing.density > 0 ? ing.density : 1;
            const g = gramsFromIngredientAmountAndUnit(seg.amount, seg.unit, density);
            const gramsRounded = Math.round(g * 100) / 100;
            rows.push({
                nameQuery: '',
                selectedName: ing.name,
                ingredientId: id,
                amount: String(gramsRounded),
                unit: 'g',
            });
        }

        setIngredientPasteMissingLabels(missing);
        setIngredientRows(rows);

        if (rows.length === 0) {
            setIngredientPasteApplyError(
                'No library matches for the parsed segments. Add missing ingredients to the library or fix spelling (matching is case-insensitive).',
            );
        }
    }, [ingredientPasteField, ingredientDatabase]);

    const mealCsvRowForCalculator = useMemo(() => {
        const pairs = ingredientRows
            .map((r) => {
                const name = (r.selectedName || r.nameQuery || '').trim();
                const grams = unitToGrams(r.amount, r.unit);
                if (!name || grams <= 0) {
                    return null;
                }
                return `${name}:${Math.round(grams * 100) / 100}`;
            })
            .filter(Boolean);

        const base = {
            meal_name: formName || 'Untitled',
            ingredient_quantities: pairs.join('|'),
            instructions: formInstructions,
            highlight: formHighlight,
        };
        if (resolveMealLibraryCategory(formType) !== null) {
            base.category = formType;
        }
        return base;
    }, [
        ingredientRows,
        formName,
        formType,
        formInstructions,
        formHighlight,
    ]);

    const nutritionResult = useMemo(
        () => calculateMealNutrition(mealCsvRowForCalculator, ingredientDatabase),
        [mealCsvRowForCalculator, ingredientDatabase],
    );

    const aggregatedIngredientNutrition = useMemo(
        () =>
            aggregateNutritionFromIngredientRows(
                ingredientRows.map((r) => ({
                    ingredientId: r.ingredientId,
                    selectedName: r.selectedName,
                    nameQuery: r.nameQuery,
                    amount: r.amount,
                    unit: r.unit,
                })),
                ingredientDatabase,
            ),
        [ingredientRows, ingredientDatabase],
    );

    const nutritionForSidebar = useMemo(() => {
        if (aggregatedIngredientNutrition.resolvedLineCount > 0) {
            return aggregatedIngredientNutrition.nutrition;
        }
        if (nutritionResult?.ok) {
            return nutritionResult.nutrition;
        }
        return null;
    }, [aggregatedIngredientNutrition, nutritionResult]);

    /**
     * System actual **per serving** for planning-target variance (and micronutrient highlights when bulk).
     * Planning targets are always per-serving; bulk ingredient rollups are batch totals → divide by servings.
     */
    const perServingNutritionForPlanningTargets = useMemo(
        () =>
            resolvePerServingActualForTargets({
                isBulkRecipe,
                bulkServingsCountRaw: bulkServingsCount,
                batchNutrition: nutritionForSidebar,
                parseServings: parseBulkServingsCount,
            }),
        [isBulkRecipe, bulkServingsCount, nutritionForSidebar],
    );

    /** Sidebar summary: bulk mode only shows per-serving system totals (never batch rollup). */
    const nutritionForSummaryDisplay = useMemo(() => {
        if (!nutritionForSidebar) {
            return null;
        }
        if (!isBulkRecipe) {
            return nutritionForSidebar;
        }
        const svc = parseBulkServingsCount(bulkServingsCount);
        if (svc == null || !Number.isFinite(svc) || svc <= 0) {
            return null;
        }
        return scaleNutritionRecord(nutritionForSidebar, 1 / svc);
    }, [isBulkRecipe, bulkServingsCount, nutritionForSidebar]);

    const ingredientPasteHighlightParts = useMemo(
        () => buildIngredientPasteHighlightParts(ingredientPasteField, ingredientDatabase),
        [ingredientPasteField, ingredientDatabase],
    );

    useLayoutEffect(() => {
        const ta = ingredientPasteTextareaRef.current;
        const pre = ingredientPasteMirrorRef.current;
        if (!ta || !pre) {
            return;
        }
        pre.style.height = `${ta.scrollHeight}px`;
        pre.scrollTop = ta.scrollTop;
    }, [ingredientPasteField, ingredientPasteHighlightParts]);

    useEffect(() => {
        // Intentionally empty: batch macro inputs are manual-only and must not sync from ingredient nutrition.
    }, [nutritionForSidebar, ingredientRows, isBulkRecipe]);

    const ingredientRowsForSafety = useMemo(
        () =>
            ingredientRows.map((r) => ({
                ingredientId: r.ingredientId,
                selectedName: r.selectedName,
                nameQuery: r.nameQuery,
            })),
        [ingredientRows],
    );

    const hasG6pdTrigger = useMemo(
        () => mealHasG6pdTriggerInEditor(ingredientRowsForSafety, ingredientPasteField, ingredientDatabase),
        [ingredientRowsForSafety, ingredientPasteField, ingredientDatabase],
    );

    const safetyFormAlerts = useMemo(
        () => collectSafetyAlertLabelsFromIngredientSelection(ingredientRowsForSafety, ingredientDatabase),
        [ingredientRowsForSafety, ingredientDatabase],
    );

    const [g6pdToastVisible, setG6pdToastVisible] = useState(false);
    const g6pdToastShownRef = useRef(false);

    useEffect(() => {
        if (!createOpen) {
            g6pdToastShownRef.current = false;
            setG6pdToastVisible(false);

            return;
        }
        if (!hasG6pdTrigger) {
            g6pdToastShownRef.current = false;
            setG6pdToastVisible(false);

            return;
        }
        if (g6pdToastShownRef.current) {
            return;
        }
        g6pdToastShownRef.current = true;
        setG6pdToastVisible(true);
        const timer = window.setTimeout(() => setG6pdToastVisible(false), 6000);

        return () => window.clearTimeout(timer);
    }, [createOpen, hasG6pdTrigger]);

    const scBadges = useMemo(() => {
        const n = perServingNutritionForPlanningTargets;
        const badges = [];
        if (hasG6pdTrigger) {
            badges.push(G6PD_HIGHLIGHT_BADGE);
        }
        if (n) {
            badges.push(...sickleCellHighlightBadgeLabels(n));
        }
        return badges;
    }, [hasG6pdTrigger, perServingNutritionForPlanningTargets]);

    const nutritionSummarySections = useMemo(() => {
        if (!nutritionForSummaryDisplay) {
            return [];
        }
        const n = nutritionForSummaryDisplay;
        const num = (v) => (typeof v === 'number' && Number.isFinite(v) ? v : 0);
        const fmt = (v, digits = 1) => num(v).toFixed(digits).replace(/\.0$/, '');

        return [
            {
                title: 'Macros',
                rows: [
                    { label: 'Total calories', value: fmt(n.calories, 0) },
                    { label: 'Protein (g)', value: fmt(n.protein, 1), valueClass: 'text-[#916A00]' },
                    { label: 'Carbs (g)', value: fmt(n.carbs, 1), valueClass: 'text-[#8F55A8]' },
                    { label: 'Fat (g)', value: fmt(n.fat, 1), valueClass: 'text-[#2F4C9B]' },
                    { label: 'Fiber (g)', value: fmt(n.fiber, 1) },
                    { label: 'Sugar (g)', value: fmt(n.sugar, 1) },
                ],
            },
            {
                title: 'Iron & B vitamins (Sickle cell focus)',
                rows: [
                    { label: 'Iron (mg)', value: fmt(n.iron, 2) },
                    { label: 'Vitamin C (mg)', value: fmt(n.vitamin_c, 1) },
                    { label: 'Folate B9 (mcg)', value: fmt(n.b9_folate, 1) },
                    { label: 'Vitamin B12 (mcg)', value: fmt(n.b12, 2) },
                    { label: 'Vitamin B6 (mg)', value: fmt(n.b6, 2) },
                    { label: 'Magnesium (mg)', value: fmt(n.magnesium, 1) },
                    { label: 'Zinc (mg)', value: fmt(n.zinc, 2) },
                ],
            },
            {
                title: 'Other minerals',
                rows: [
                    { label: 'Calcium (mg)', value: fmt(n.calcium, 1) },
                    { label: 'Potassium (mg)', value: fmt(n.potassium, 1) },
                    { label: 'Sodium (mg)', value: fmt(n.sodium, 1) },
                ],
            },
            {
                title: 'Fat-soluble vitamins',
                rows: [
                    { label: 'Vitamin A', value: fmt(n.vitamin_a, 1) },
                    { label: 'Vitamin D', value: fmt(n.vitamin_d, 1) },
                    { label: 'Vitamin E', value: fmt(n.vitamin_e, 1) },
                    { label: 'Vitamin K2', value: fmt(n.vitamin_k2, 1) },
                ],
            },
        ];
    }, [nutritionForSummaryDisplay]);

    const categoryWarningsForModal = useMemo(() => {
        if (!nutritionResult?.ok || !nutritionResult.category) {
            return nutritionResult?.categoryWarnings ?? [];
        }
        const cat = nutritionResult.category;
        if (!isBulkRecipe) {
            return nutritionResult.categoryWarnings ?? [];
        }
        const svc = parseBulkServingsCount(bulkServingsCount);
        if (svc == null || !Number.isFinite(svc) || svc <= 0) {
            return nutritionResult.categoryWarnings ?? [];
        }
        const cal = Number(nutritionResult.nutrition?.calories);
        if (!Number.isFinite(cal)) {
            return nutritionResult.categoryWarnings ?? [];
        }
        return calorieWarningsForCategory(cat, cal / svc);
    }, [nutritionResult, isBulkRecipe, bulkServingsCount]);

    const nutritionSummaryTableValueLabel = useMemo(() => {
        if (!isBulkRecipe) {
            return 'Total (meal)';
        }
        const svc = parseBulkServingsCount(bulkServingsCount);
        if (svc == null || !Number.isFinite(svc) || svc <= 0) {
            return 'Batch total (system)';
        }
        return 'Per serving (system)';
    }, [isBulkRecipe, bulkServingsCount]);

    const persistedMacroTotalsForStore = useMemo(
        () =>
            persistedMacroTotalsFromIngredientSources(
                aggregatedIngredientNutrition,
                nutritionResult,
                isBulkRecipe,
                bulkServingsCount,
            ),
        [aggregatedIngredientNutrition, nutritionResult, isBulkRecipe, bulkServingsCount],
    );

    const loadMoreFooter =
        filteredMeals.length > visibleCount ? (
            <div className="flex justify-center border-t border-gray-100 pt-6">
                <Button
                    label={`Load more (${Math.min(PAGE_SIZE, filteredMeals.length - visibleCount)} meals)`}
                    variant="secondary"
                    type="button"
                    onClick={() => setVisibleCount((c) => c + PAGE_SIZE)}
                />
            </div>
        ) : null;

    return (
        <div className={`min-h-screen ${PAGE_BG} px-4 pb-8 pt-4 font-sans md:px-8`}>
            <div className="mx-auto max-w-[1400px] space-y-6">
                {flashSuccess ? (
                    <div
                        role="status"
                        className="rounded-[12px] border border-[#5A6B44]/30 bg-[#F8F9F6] px-4 py-3 font-body text-sm text-[#262A22]"
                    >
                        {flashSuccess}
                    </div>
                ) : null}
                {flashError ? (
                    <div
                        role="alert"
                        className="rounded-[12px] border border-[#C44F5D]/40 bg-[#FDF2F2] px-4 py-3 font-body text-sm text-[#7F1D1D]"
                    >
                        {flashError}
                    </div>
                ) : null}
                {Array.isArray(pendingMealImports) && pendingMealImports.length > 0 ? (
                    <div
                        role="status"
                        className="rounded-[12px] border border-amber-300 bg-amber-50 px-4 py-3 font-body text-sm text-amber-950"
                    >
                        <p className="font-semibold">
                            {pendingMealImports.length === 1
                                ? '1 meal is waiting to be added'
                                : `${pendingMealImports.length} meals are waiting to be added`}
                        </p>
                        <p className="mt-1">
                            These CSV rows were not saved to the library because one or more ingredients are missing from
                            the verified Ingredients Library:{' '}
                            <span className="font-medium">{pendingMealImports.join(', ')}</span>.
                        </p>
                        <p className="mt-2 text-xs">
                            Add the missing ingredients (or fix spelling), then upload the meal CSV again from this page.
                            You can also use &quot;Download missing ingredients&quot; from the last import summary if it is
                            still open.
                        </p>
                    </div>
                ) : null}
                {mealLibrarySchemaNotice ? (
                    <div
                        role="alert"
                        className="rounded-[12px] border border-amber-300 bg-amber-50 px-4 py-3 font-body text-sm text-amber-950"
                    >
                        {mealLibrarySchemaNotice}
                    </div>
                ) : null}
                <section className="relative z-0 rounded-[12px] border border-gray-200 bg-white shadow-sm" aria-labelledby="meal-library-heading">
                    <h2 id="meal-library-heading" className="sr-only">
                        Meal library
                    </h2>
                    <p id="meal-library-desc" className="sr-only">
                        Browse, filter, and curate meals for Smart Kitchen workflows.
                    </p>

                    <div
                        className="flex w-full flex-col gap-6 rounded-t-[12px] border-b border-gray-200 px-5 pb-6 pt-6"
                        aria-describedby="meal-library-desc"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-3">
                            <div className="shrink-0">
                                <Button
                                    label="Create meal"
                                    variant="primary"
                                    type="button"
                                    className="shrink-0 uppercase tracking-wide"
                                    onClick={() => {
                                        resetCreateForm();
                                        setMealToEdit(null);
                                        setCreateOpen(true);
                                    }}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <CSVUploader
                                    className="w-full pt-0"
                                    onDownloadMealCraftCsvTemplate={
                                        csvMealCraftTemplateUrl
                                            ? () => downloadMealCraftCsvTemplate(csvMealCraftTemplateUrl)
                                            : undefined
                                    }
                                    exportUrl={csvExportUrl}
                                    uploadBusyLabel="Parsing & importing…"
                                    onUpload={(file) => {
                                        router.post(
                                            csvImportUrl,
                                            { file },
                                            {
                                                forceFormData: true,
                                                preserveScroll: true,
                                                onSuccess: (page) => {
                                                    const flash = page.props?.flash?.mealCsvImportResult;
                                                    const modal = buildMealCsvImportModalFromPayload(flash);
                                                    if (modal) {
                                                        setMealCsvImportResultModal(modal);
                                                    }
                                                },
                                                onError: (errors) => {
                                                    const fileMessages = errors?.file;
                                                    const fileMessage = Array.isArray(fileMessages)
                                                        ? String(fileMessages[0] ?? '')
                                                        : typeof fileMessages === 'string'
                                                          ? fileMessages
                                                          : '';
                                                    setMealCsvImportResultModal({
                                                        error:
                                                            fileMessage ||
                                                            'CSV import failed. Check the file and try again.',
                                                        summary: {},
                                                        uniquePending: [],
                                                        rows: [],
                                                        import_error_lines: [],
                                                        csvUnrecognizedHeaders: [],
                                                        validationErrors:
                                                            errors && typeof errors === 'object' ? errors : null,
                                                    });
                                                },
                                            },
                                        );
                                    }}
                                />
                            </div>
                        </div>

                        <div className="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="min-w-0 flex-1">
                                <TextInput
                                    label="Search meals"
                                    placeholder="Search by name, meal type, category, or tag…"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    className="!max-w-none"
                                />
                            </div>
                            <div
                                className="flex shrink-0 items-center gap-1 self-stretch rounded-[12px] border border-[#E5E7EB] bg-[#F8F9F6] p-1 sm:self-auto"
                                role="group"
                                aria-label="Library view"
                            >
                                <RoundIconButton
                                    type="button"
                                    icon={<IconLayoutGrid className={viewMode === 'grid' ? 'text-[#5A6B44]' : ''} />}
                                    ariaLabel="Grid view"
                                    aria-pressed={viewMode === 'grid'}
                                    onClick={() => setViewMode('grid')}
                                    className={
                                        viewMode === 'grid'
                                            ? '!border-transparent bg-white text-[#262A22] shadow-sm'
                                            : '!border-transparent bg-transparent text-[#6B7280] shadow-none hover:bg-white/70'
                                    }
                                />
                                <RoundIconButton
                                    type="button"
                                    icon={<IconLayoutList className={viewMode === 'list' ? 'text-[#5A6B44]' : ''} />}
                                    ariaLabel="List view"
                                    aria-pressed={viewMode === 'list'}
                                    onClick={() => setViewMode('list')}
                                    className={
                                        viewMode === 'list'
                                            ? '!border-transparent bg-white text-[#262A22] shadow-sm'
                                            : '!border-transparent bg-transparent text-[#6B7280] shadow-none hover:bg-white/70'
                                    }
                                />
                            </div>
                        </div>

                        <div className="-mx-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 pb-0 pt-4">
                            <div className="min-w-0">
                                <p className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]">Meal library</p>
                                <p className="mt-0.5 font-body text-xs text-[#555555]">
                                    <span className="font-semibold text-[#374151]">{mealRows.length}</span> in library ·{' '}
                                    {displayedMeals.length} of {filteredMeals.length} in view
                                    {filteredMeals.length < mealRows.length ? ' (filtered)' : ''} · {selectedRows.length}{' '}
                                    selected
                                    {filteredMeals.length > PAGE_SIZE && displayedMeals.length < filteredMeals.length
                                        ? ' · use "Load more" below'
                                        : ''}
                                </p>
                            </div>
                            <Button
                                label="Delete selected"
                                variant="ghost"
                                type="button"
                                disabled={selectedRows.length === 0}
                                onClick={() => {
                                    if (!anySelected) {
                                        return;
                                    }
                                    setDeleteError(null);
                                    setConfirmOpen(true);
                                }}
                                className={deleteSelectedButtonClass(anySelected)}
                            />
                        </div>
                    </div>

                    <div className="p-5">
                        {filteredMeals.length === 0 ? (
                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] p-8 text-center font-body text-sm text-[#555555]">
                                No meals match your search. Try another name, type, category, or tag.
                            </p>
                        ) : viewMode === 'grid' ? (
                            <>
                                <ul className="m-0 grid list-none grid-cols-1 gap-6 p-0 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {displayedMeals.map((meal) => (
                                        <li key={meal.id} className="flex justify-center">
                                            <MealCard
                                                isAdmin
                                                adminControls
                                                showActions
                                                selected={selectedSet.has(meal.id)}
                                                onToggleSelected={() => toggleRow(meal.id)}
                                                meal={meal}
                                                onViewDetails={(m) => {
                                                    if (m?.detailView) {
                                                        setMealDetailModal({
                                                            title: String(m.title ?? '').trim() || 'Meal',
                                                            detailView: m.detailView,
                                                        });
                                                    }
                                                }}
                                                onPrimaryAction={() => {}}
                                                onEdit={() => {
                                                    resetCreateForm();
                                                    setMealToEdit(meal);
                                                    setCreateOpen(true);
                                                }}
                                                className="transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:translate-y-0 active:scale-[0.98] active:shadow-md"
                                            />
                                        </li>
                                    ))}
                                </ul>
                                {loadMoreFooter}
                            </>
                        ) : (
                            <>
                                <MealLibrarySortableTable
                                    displayedMeals={displayedMeals}
                                    mealRows={mealRows}
                                    selectedSet={selectedSet}
                                    allVisibleSelected={allVisibleSelected}
                                    onToggleAllVisible={toggleAllVisible}
                                    onToggleRow={toggleRow}
                                    onRowReorder={persistMealRowReorder}
                                />
                                {loadMoreFooter}
                            </>
                        )}
                    </div>
                </section>
            </div>

            {mealCsvImportResultModal && typeof document !== 'undefined'
                ? createPortal(
                      <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                          <button
                              type="button"
                              className="absolute inset-0 bg-black/40"
                              onClick={dismissMealCsvImportModal}
                              aria-label="Close import summary"
                          />
                          <div
                              role="dialog"
                              aria-modal="true"
                              aria-labelledby="meal-csv-import-summary-title"
                              className="relative w-full max-w-[520px] rounded-[12px] bg-white p-8 shadow-2xl"
                          >
                              <h2
                                  id="meal-csv-import-summary-title"
                                  className="text-center font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]"
                              >
                                  Meal CSV import
                              </h2>
                              {mealCsvImportResultModal.error ? (
                                  <div className="mt-4 space-y-3 text-left font-body text-sm text-[#7F1D1D]" role="alert">
                                      <p className="text-center">{mealCsvImportResultModal.error}</p>
                                      {mealCsvImportResultModal.validationErrors ? (
                                          <ul className="list-disc space-y-1 pl-5 text-[#262A22]">
                                              {Object.entries(mealCsvImportResultModal.validationErrors).map(([key, msgs]) =>
                                                  Array.isArray(msgs) ? (
                                                      msgs.map((m) => (
                                                          <li key={`${key}-${String(m)}`}>
                                                              <span className="font-semibold">{key}:</span> {String(m)}
                                                          </li>
                                                      ))
                                                  ) : (
                                                      <li key={key}>
                                                          <span className="font-semibold">{key}:</span> {String(msgs)}
                                                      </li>
                                                  ),
                                              )}
                                          </ul>
                                      ) : null}
                                  </div>
                              ) : (
                                  <div className="mt-6 space-y-4 font-body text-sm text-[#262A22]">
                                      {mealCsvImportMealLibrarySavedCount(mealCsvImportResultModal.summary) > 0 ? (
                                          <div className="flex gap-3 rounded-[12px] border border-[#E5E7EB] bg-[#F8F9F6] px-4 py-3">
                                              <span className="text-lg leading-none text-[#5A6B44]" aria-hidden>
                                                  ✓
                                              </span>
                                              <p>
                                                  <span className="font-semibold">
                                                      {mealCsvImportMealLibrarySavedCount(mealCsvImportResultModal.summary)}
                                                  </span>{' '}
                                                  meal(s) saved to the Meal Library (new or updated).
                                              </p>
                                          </div>
                                      ) : null}
                                      {mealCsvImportIngredientLibrarySavedCount(mealCsvImportResultModal.summary) > 0 ? (
                                          <div className="flex gap-3 rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
                                              <span className="text-lg leading-none" aria-hidden>
                                                  ⚠
                                              </span>
                                              <div>
                                                  <p>
                                                      <span className="font-semibold">
                                                          {mealCsvImportIngredientLibrarySavedCount(
                                                              mealCsvImportResultModal.summary,
                                                          )}
                                                      </span>{' '}
                                                      row(s) were saved to the{' '}
                                                      <span className="font-semibold">Ingredient Library</span> as base
                                                      recipes (Category = Base Ingredient), not the Meal Library. Check
                                                      Ingredient Library for those names.
                                                  </p>
                                                  {mealCsvImportIngredientLibraryRowNames(mealCsvImportResultModal.rows ?? [])
                                                      .length > 0 ? (
                                                      <ul className="mt-2 max-h-32 list-disc space-y-0.5 overflow-y-auto pl-5 text-sm">
                                                          {mealCsvImportIngredientLibraryRowNames(
                                                              mealCsvImportResultModal.rows ?? [],
                                                          ).map((name) => (
                                                              <li key={name}>{name}</li>
                                                          ))}
                                                      </ul>
                                                  ) : null}
                                              </div>
                                          </div>
                                      ) : null}
                                      {(Number(mealCsvImportResultModal.summary?.pending_ingredient_input) || 0) > 0 ? (
                                          <div className="flex gap-3 rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
                                              <span className="text-lg leading-none" aria-hidden>
                                                  ⚠
                                              </span>
                                              <p>
                                                  <span className="font-semibold">
                                                      {Number(mealCsvImportResultModal.summary?.pending_ingredient_input) ||
                                                          0}
                                                  </span>{' '}
                                                  meal row(s) are pending because one or more ingredients were not found in
                                                  your library. Add them via Ingredient Library import, and those meals will
                                                  be created automatically.
                                              </p>
                                          </div>
                                      ) : null}
                                      {(mealCsvImportResultModal.uniquePending ?? []).length > 0 ? (
                                          <div className="rounded-[12px] border border-amber-200/80 bg-white px-4 py-3 text-amber-950">
                                              <p className="font-semibold">Missing from ingredient library</p>
                                              <p className="mt-1 text-sm text-[#555555]">
                                                  This is not a crash — the meal was held until these exist in your library.
                                                  After you add them, upload the ingredient CSV on the Ingredient Library page;
                                                  the meal will be created automatically (or re-upload this meal CSV).
                                              </p>
                                              {mealCsvImportPendingMealNames(mealCsvImportResultModal.rows ?? []).length > 0 ? (
                                                  <p className="mt-2 text-sm">
                                                      <span className="font-semibold">Meal waiting:</span>{' '}
                                                      {mealCsvImportPendingMealNames(mealCsvImportResultModal.rows ?? []).join(
                                                          ', ',
                                                      )}
                                                  </p>
                                              ) : null}
                                              <ul className="mt-2 max-h-40 list-disc space-y-0.5 overflow-y-auto pl-5 text-sm">
                                                  {(mealCsvImportResultModal.uniquePending ?? []).map((name) => (
                                                      <li key={String(name)}>{String(name)}</li>
                                                  ))}
                                              </ul>
                                          </div>
                                      ) : null}
                                      {(mealCsvImportResultModal.csvUnrecognizedHeaders ?? []).length > 0 ? (
                                          <div className="rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
                                              <p className="font-semibold">Unrecognized column header(s)</p>
                                              <p className="mt-1 text-sm">
                                                  These labels were not mapped to known fields (check spelling, spaces vs.
                                                  underscores):{' '}
                                                  <span className="font-mono text-xs">
                                                      {(mealCsvImportResultModal.csvUnrecognizedHeaders ?? []).join(', ')}
                                                  </span>
                                              </p>
                                          </div>
                                      ) : null}
                                      {(Number(mealCsvImportResultModal.summary?.errors) || 0) > 0 ? (
                                          <p className="font-semibold text-[#7F1D1D]">
                                              {Number(mealCsvImportResultModal.summary?.errors) || 0} row(s) had errors (see
                                              CSV template rules).
                                          </p>
                                      ) : null}
                                      {!mealCsvImportModalHasVisibleOutcome(
                                          mealCsvImportResultModal.summary,
                                          mealCsvImportResultModal.uniquePending,
                                          mealCsvImportResultModal.rows,
                                          mealCsvImportResultModal.import_error_lines,
                                      ) ? (
                                          <div
                                              className="rounded-[12px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950"
                                              role="alert"
                                          >
                                              <p className="font-semibold">Import finished — no meals saved</p>
                                              <p className="mt-1 text-sm">
                                                  The file was read but nothing was imported or updated. Check that
                                                  ingredient names in{' '}
                                                  <span className="font-mono text-xs">ingredients_string</span> exist in
                                                  your verified Ingredient Library, then upload again. You should also
                                                  see pending-ingredient details above when rows were held.
                                              </p>
                                          </div>
                                      ) : null}
                                      {(() => {
                                          const lines = mealCsvImportModalErrorDisplayLines(mealCsvImportResultModal);
                                          const n = Number(mealCsvImportResultModal.summary?.errors) || 0;
                                          if (lines.length > 0) {
                                              return (
                                                  <div className="space-y-2">
                                                      {lines.map((text, idx) => (
                                                          <div
                                                              key={`meal-csv-import-err-line-${idx}`}
                                                              className="rounded-[12px] border border-red-300 bg-red-50 px-4 py-3 text-left text-sm font-medium text-red-900"
                                                              role="alert"
                                                          >
                                                              {text}
                                                          </div>
                                                      ))}
                                                  </div>
                                              );
                                          }
                                          if (n <= 0) {
                                              return null;
                                          }
                                          return (
                                              <div
                                                  className="rounded-[12px] border border-red-300 bg-red-50 px-4 py-3 text-left text-sm text-red-900"
                                                  role="alert"
                                              >
                                                  The server reported {n} error row(s), but no error text was returned.
                                                  Confirm you are on the latest deploy and check the Network tab for the
                                                  POST response body — it should include{' '}
                                                  <span className="font-mono text-xs">import_error_lines</span> or{' '}
                                                  <span className="font-mono text-xs">rows</span> with{' '}
                                                  <span className="font-mono text-xs">status: &quot;error&quot;</span>.
                                              </div>
                                          );
                                      })()}
                                      {(mealCsvImportResultModal.uniquePending ?? []).length > 0 ? (
                                          <Button
                                              label="Download missing ingredients"
                                              variant="secondary"
                                              type="button"
                                              className="w-full"
                                              onClick={() =>
                                                  downloadMissingIngredientsCSV(mealCsvImportResultModal.uniquePending ?? [])
                                              }
                                          />
                                      ) : null}
                                  </div>
                              )}
                              <div className="mt-6">
                                  <Button
                                      label="Done"
                                      variant="primary"
                                      type="button"
                                      className="w-full uppercase tracking-wide"
                                      onClick={dismissMealCsvImportModal}
                                  />
                              </div>
                          </div>
                      </div>,
                      document.body,
                  )
                : null}

            {confirmOpen ? (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setConfirmOpen(false)}
                        aria-label="Close delete confirmation"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-label="Delete selected meals"
                        className="relative w-full max-w-[480px] rounded-[12px] bg-white p-8 shadow-2xl"
                    >
                        <p className="text-center font-montserrat text-xs font-bold uppercase tracking-[0.22em] text-[#C44F5D]">
                            Warning
                        </p>
                        <p className="mt-3 text-center font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]">
                            Remove selected meals from the library?
                        </p>
                        <p className="mt-3 text-center font-body text-sm text-[#555555]">
                            Removed meals will no longer appear in the Meal Library or in CSV export.
                        </p>
                        {deleteError ? (
                            <p className="mt-3 text-center font-body text-sm font-semibold text-[#B91C1C]" role="alert">
                                {deleteError}
                            </p>
                        ) : null}
                        <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Button
                                label="Cancel"
                                variant="secondary"
                                type="button"
                                disabled={deleteBusy}
                                onClick={() => {
                                    setDeleteError(null);
                                    setConfirmOpen(false);
                                }}
                                className="w-full"
                            />
                            <Button
                                label={deleteBusy ? 'Deleting…' : 'Delete permanently'}
                                variant="ghost"
                                type="button"
                                disabled={deleteBusy}
                                className={
                                    'w-full rounded-[12px] transition-colors duration-200 ' +
                                    '!bg-[#C44F5D] !text-white hover:!bg-[#B14552] hover:!text-white'
                                }
                                onClick={() => void handleConfirmDelete()}
                            />
                        </div>
                    </div>
                </div>
            ) : null}

            {mealDetailModal ? (
                <div className="fixed inset-0 z-[102] flex items-end justify-center sm:items-center sm:p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setMealDetailModal(null)}
                        aria-label="Close meal details"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="meal-library-detail-title"
                        className="relative flex max-h-[min(92dvh,calc(100dvh-2rem))] w-full max-w-[960px] flex-col overflow-hidden rounded-t-[12px] bg-[#F8F9F6] shadow-2xl sm:rounded-[12px]"
                    >
                        <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-4 py-4 md:px-6">
                            <div className="min-w-0 flex-1">
                                <h2
                                    id="meal-library-detail-title"
                                    className="break-words font-montserrat text-xl font-bold tracking-tight text-[#262A22] md:text-2xl"
                                >
                                    {mealDetailModal.title}
                                </h2>
                                {mealDetailModal.detailView?.shortDescription ||
                                mealDetailModal.detailView?.description ? (
                                    <p className="mt-1 line-clamp-2 font-montserrat text-sm font-medium text-[#555555] md:text-base">
                                        {mealDetailModal.detailView.shortDescription ||
                                            mealDetailModal.detailView.description}
                                    </p>
                                ) : null}
                            </div>
                            <Button label="Close" variant="ghost" type="button" onClick={() => setMealDetailModal(null)} />
                        </div>
                        <MealDetailView meal={mealDetailModal.detailView} embedded />
                    </div>
                </div>
            ) : null}

            {createOpen ? (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/40"
                        onClick={() => closeCreateModal()}
                        aria-label="Close create meal modal"
                    />
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="meal-library-create-title"
                        className="relative w-full max-w-[1200px] overflow-hidden rounded-[12px] bg-white shadow-2xl"
                    >
                        <div className="absolute right-3 top-3 z-20">
                            <Button label="Close" variant="ghost" onClick={() => closeCreateModal()} />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-[13fr_7fr]">
                            {/* Left: Form */}
                            <div className="flex max-h-[85vh] flex-col overflow-hidden border-b border-gray-200 md:border-b-0 md:border-r">
                                <div className="flex-1 overflow-y-auto px-10 pb-6 pt-10">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h2
                                                id="meal-library-create-title"
                                                className="font-montserrat text-[22px] font-bold tracking-tight text-[#262A22]"
                                            >
                                                {mealToEdit?.editForm?.id ? 'Edit meal' : 'Create meal'}
                                            </h2>
                                            <p className="mt-1 font-body text-sm text-[#555555]">
                                                {isBulkRecipe
                                                    ? 'Bulk mode: enter batch totals for calories and macros, then set how many servings that batch makes. We save single-serving values to your library.'
                                                    : 'Build a meal and calculate nutrition live from your ingredient library.'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 space-y-6">
                                    <TextInput
                                        id="create-meal-name"
                                        label="Meal name"
                                        placeholder="e.g. Post-training recovery shake"
                                        value={formName}
                                        onChange={(e) => setFormName(e.target.value)}
                                        className="!max-w-none"
                                        required
                                    />

                                    <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-3"
                                            onClick={() => setIsBulkRecipe((v) => !v)}
                                            aria-pressed={isBulkRecipe}
                                        >
                                            <SquareCheckbox checked={isBulkRecipe} />
                                            <span className="font-montserrat text-sm font-bold text-[#262A22]">
                                                Bulk recipe
                                            </span>
                                        </button>
                                        <p className="mt-2 font-body text-sm text-[#555555]">
                                            Totals you enter are for the full batch; single-serving values are computed and
                                            stored for meal cards and plans.
                                        </p>
                                        <div
                                            className={[
                                                'overflow-hidden transition-all duration-300',
                                                isBulkRecipe ? 'mt-4 max-h-96 opacity-100' : 'max-h-0 opacity-0',
                                            ].join(' ')}
                                        >
                                            <div className={['space-y-4', isBulkRecipe ? '' : 'pointer-events-none'].join(' ')}>
                                                <TextInput
                                                    label="Number of servings"
                                                    placeholder="e.g. 8"
                                                    value={bulkServingsCount}
                                                    onChange={(e) => setBulkServingsCount(e.target.value)}
                                                    className="!max-w-none"
                                                    inputMode="decimal"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <TextInput
                                            id="create-meal-calories"
                                            label={isBulkRecipe ? 'Batch total calories' : 'Calories'}
                                            placeholder="e.g. 420"
                                            value={formCalories}
                                            onChange={(e) => setFormCalories(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                            autoComplete="off"
                                            name="meal_library_batch_calories"
                                            required
                                        />
                                        <TextInput
                                            label={isBulkRecipe ? 'Batch protein (g)' : 'Protein (g)'}
                                            placeholder="0"
                                            value={formProtein}
                                            onChange={(e) => setFormProtein(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                            autoComplete="off"
                                            name="meal_library_batch_protein"
                                        />
                                        <TextInput
                                            label={isBulkRecipe ? 'Batch carbs (g)' : 'Carbs (g)'}
                                            placeholder="0"
                                            value={formCarbs}
                                            onChange={(e) => setFormCarbs(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                            autoComplete="off"
                                            name="meal_library_batch_carbs"
                                        />
                                        <TextInput
                                            label={isBulkRecipe ? 'Batch fat (g)' : 'Fat (g)'}
                                            placeholder="0"
                                            value={formFat}
                                            onChange={(e) => setFormFat(e.target.value)}
                                            className="!max-w-none"
                                            inputMode="decimal"
                                            autoComplete="off"
                                            name="meal_library_batch_fat"
                                        />
                                    </div>

                                    <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                        <p className="font-montserrat text-sm font-bold text-[#262A22]">
                                            Ingredient string (library match)
                                        </p>
                                        <p className="mt-1 font-body text-xs text-[#6B7280]">
                                            Paste segments separated by <span className="font-semibold">|</span> or a new line.
                                            Each segment: <span className="font-mono">Name:825g</span>,{' '}
                                            <span className="font-mono">Name (710ml)</span> or{' '}
                                            <span className="font-mono">Name(710ml)</span>, <span className="font-mono">Name:0.5kg</span>, or{' '}
                                            <span className="font-mono">Name 200ml</span> (unit required for the space form). Library
                                            matching is case-insensitive. Green = matched name; red = not in library or unparsed. Apply
                                            replaces ingredient rows and refreshes the nutrition summary.
                                        </p>
                                        <div className="relative mt-3 rounded-[12px] border border-[#E5E7EB] bg-[#F8F9F6]">
                                            <textarea
                                                ref={ingredientPasteTextareaRef}
                                                value={ingredientPasteField}
                                                onChange={(e) => {
                                                    setIngredientPasteField(e.target.value.replace(/\r\n/g, '\n'));
                                                    setIngredientPasteApplyError('');
                                                }}
                                                onScroll={(e) => {
                                                    const pre = ingredientPasteMirrorRef.current;
                                                    if (pre) {
                                                        pre.scrollTop = e.currentTarget.scrollTop;
                                                    }
                                                }}
                                                rows={4}
                                                spellCheck={false}
                                                className="relative z-10 m-0 box-border block min-h-[5.5rem] w-full resize-y rounded-[12px] border-0 bg-transparent px-3 py-2 font-mono text-sm leading-relaxed text-transparent caret-[#1F2937] outline-none [-webkit-text-fill-color:transparent] selection:bg-[#C5D4B0]/45 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#5A6B44]"
                                                placeholder={
                                                    'e.g. Chicken Thighs:825g | Rice (200g) | Olive Oil(15ml) | Lemon Juice (15ml)'
                                                }
                                                aria-label="Ingredient quantities string"
                                            />
                                            <pre
                                                ref={ingredientPasteMirrorRef}
                                                className="pointer-events-none absolute inset-x-0 top-0 z-0 m-0 box-border min-h-[5.5rem] w-full overflow-hidden rounded-[12px] border-0 bg-transparent px-3 py-2 font-mono text-sm leading-relaxed whitespace-pre-wrap break-words"
                                                aria-hidden="true"
                                            >
                                                {ingredientPasteHighlightParts.map((part, idx) => (
                                                    <span
                                                        key={idx}
                                                        className={
                                                            part.tone === 'ok'
                                                                ? 'font-semibold text-green-700'
                                                                : part.tone === 'bad'
                                                                  ? 'font-semibold text-red-700'
                                                                  : 'text-[#1F2937]'
                                                        }
                                                    >
                                                        {part.text}
                                                    </span>
                                                ))}
                                            </pre>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center gap-3">
                                            <Button
                                                label="Apply to ingredients & macros"
                                                variant="secondary"
                                                type="button"
                                                onClick={handleApplyIngredientQuantityString}
                                            />
                                        </div>
                                        {ingredientPasteApplyError ? (
                                            <p className="mt-3 font-body text-sm text-[#7F1D1D]" role="alert">
                                                {ingredientPasteApplyError}
                                            </p>
                                        ) : null}
                                        {ingredientPasteMissingLabels.length > 0 ? (
                                            <div className="mt-4 rounded-[12px] border border-amber-200 bg-amber-50 p-3">
                                                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-amber-900">
                                                    Not in library (add these ingredients first)
                                                </p>
                                                <ul className="mt-2 list-inside list-disc space-y-1 font-body text-sm text-amber-950">
                                                    {ingredientPasteMissingLabels.map((name) => (
                                                        <li key={name}>{name}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                    </div>

                                    <div className="rounded-[12px] border border-[#C4B5A0]/35 bg-[#FAF8F5] p-4">
                                        <p className="font-montserrat text-sm font-bold text-[#262A22]">Planning targets</p>
                                        <p className="mt-1 font-body text-xs text-[#6B7280]">
                                            Per-serving editorial goals (never batch totals). These fields never sync with batch
                                            totals, ingredient lines, or system nutrition — only your typing (or loading a meal for
                                            edit) changes them.
                                        </p>
                                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                            <TextInput
                                                label="Target calories (kcal)"
                                                placeholder="e.g. 500"
                                                value={targetCaloriesManual}
                                                onChange={(e) => setTargetCaloriesManual(e.target.value)}
                                                className="!max-w-none"
                                                inputMode="decimal"
                                                autoComplete="off"
                                                name="meal_library_target_calories"
                                            />
                                            <TextInput
                                                label="Target protein (g)"
                                                placeholder="0"
                                                value={targetProteinManual}
                                                onChange={(e) => setTargetProteinManual(e.target.value)}
                                                className="!max-w-none"
                                                inputMode="decimal"
                                                autoComplete="off"
                                                name="meal_library_target_protein"
                                            />
                                            <TextInput
                                                label="Target carbs — compared as net carbs (g)"
                                                placeholder="0"
                                                value={targetCarbsManual}
                                                onChange={(e) => setTargetCarbsManual(e.target.value)}
                                                className="!max-w-none"
                                                inputMode="decimal"
                                                autoComplete="off"
                                                name="meal_library_target_carbs"
                                            />
                                            <TextInput
                                                label="Target fat (g)"
                                                placeholder="0"
                                                value={targetFatManual}
                                                onChange={(e) => setTargetFatManual(e.target.value)}
                                                className="!max-w-none"
                                                inputMode="decimal"
                                                autoComplete="off"
                                                name="meal_library_target_fat"
                                            />
                                        </div>
                                    </div>

                                    <DropdownTextInput
                                        label="Meal type"
                                        value={formType}
                                        options={MEAL_FORM_TYPE_OPTIONS}
                                        onChange={setFormType}
                                        className="!max-w-none"
                                    />
                                    <MultiPillDropdown
                                        label="Meal Plan Tag"
                                        options={mealPlanMultiOptions}
                                        selectedValues={selectedMealPlanTags}
                                        onChange={setSelectedMealPlanTags}
                                        className="!max-w-none"
                                        listboxAriaLabel="Meal plan tags"
                                        placeholder="Select one or more plan tags…"
                                    />
                                    <div className="space-y-2">
                                        <p className="font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Dietary tags
                                        </p>
                                        <div className="flex flex-wrap gap-3" role="group" aria-label="Dietary tags">
                                            {DIETARY_TAG_OPTIONS.map((tag) => {
                                                const checked = selectedDietTags.includes(tag);
                                                return (
                                                    <button
                                                        key={tag}
                                                        type="button"
                                                        className="inline-flex items-center gap-2 rounded-[10px] border border-[#E5E7EB] bg-[#F8F9F6] px-3 py-2 font-body text-sm text-[#262A22] transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                                        aria-pressed={checked}
                                                        onClick={() => toggleDietTag(tag)}
                                                    >
                                                        <SquareCheckbox checked={checked} presentational />
                                                        {tag}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                    <MultiPillDropdown
                                        label="Cycle phase"
                                        options={cyclePhases}
                                        selectedValues={selectedCyclePhaseValues}
                                        onChange={setSelectedCyclePhaseValues}
                                        listboxAriaLabel="Cycle phases"
                                        className="!max-w-none"
                                        placeholder="Select one or more phases…"
                                    />

                                    <div className="block w-full max-w-[492px] text-left !max-w-none">
                                        <label className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Instructions
                                        </label>
                                        <textarea
                                            value={formInstructions}
                                            onChange={(e) => setFormInstructions(e.target.value)}
                                            rows={4}
                                            className="block w-full resize-y whitespace-pre-wrap rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-[15px] leading-relaxed text-[#1F2937] shadow-sm outline-none focus-visible:border-[#5A6B44] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                            placeholder="Steps, prep notes, cooking instructions…"
                                        />
                                    </div>

                                    <TextInput
                                        label="Short description"
                                        placeholder="Short Smart Kitchen note (optional)…"
                                        value={formHighlight}
                                        onChange={(e) => setFormHighlight(e.target.value)}
                                        className="!max-w-none"
                                    />

                                    <div className="block w-full text-left">
                                        <p className="mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                                            Meal photo
                                        </p>
                                        {mealToEdit?.editForm?.imageUrl && !formPhoto ? (
                                            <div className="mb-3 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-3">
                                                <p className="mb-2 font-body text-xs font-medium text-[#555555]">
                                                    Current photo (upload a new file below to replace)
                                                </p>
                                                <img
                                                    src={resolveMealImageUrl(mealToEdit.editForm.imageUrl)}
                                                    alt=""
                                                    className="max-h-40 w-auto max-w-full rounded-lg object-contain"
                                                />
                                            </div>
                                        ) : null}
                                        {mealPhotoPreviewUrl ? (
                                            <div className="mb-3 flex items-start gap-3 rounded-[12px] border border-gray-200 bg-white p-3">
                                                <img
                                                    src={mealPhotoPreviewUrl}
                                                    alt=""
                                                    className="h-24 w-24 shrink-0 rounded-lg object-cover"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-body text-sm text-[#374151]">
                                                        {formPhoto?.name ?? 'Selected photo'}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        className="mt-2 font-body text-sm font-semibold text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2 hover:decoration-[#5A6B44]"
                                                        onClick={clearMealPhotoSelection}
                                                    >
                                                        Remove selected photo
                                                    </button>
                                                </div>
                                            </div>
                                        ) : null}
                                        <label className="relative block cursor-pointer overflow-hidden rounded-[12px] border-2 border-dashed border-gray-200 bg-[#F8F9F6] p-10">
                                            <div className="aspect-[4/3] w-full">
                                                <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                                                    <p className="max-w-2xl font-montserrat text-[14px] font-bold uppercase leading-relaxed tracking-[0.10em] text-[#5A6B44]">
                                                        Upload photo (JPG, PNG, or WebP — large files are resized automatically)
                                                    </p>
                                                    <p className="mt-6 font-body text-sm text-[#6B7280]">
                                                        {formPhoto ? formPhoto.name : 'Click to choose a file'}
                                                    </p>
                                                </div>
                                            </div>
                                            <input
                                                name="photo"
                                                type="file"
                                                accept="image/*"
                                                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                onChange={handleMealPhotoChange}
                                            />
                                        </label>
                                    </div>

                                    <div className="rounded-[12px] border border-gray-200 bg-white p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-montserrat text-sm font-bold text-[#262A22]">Ingredients</p>
                                            <Button
                                                label="Add ingredient"
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    setIngredientRows((prev) => [
                                                        ...prev,
                                                        { nameQuery: '', selectedName: '', ingredientId: null, amount: '100', unit: 'g' },
                                                    ])
                                                }
                                            />
                                        </div>

                                        <div ref={ingredientSuggestRootRef} className="mt-4 space-y-4">
                                            {ingredientRows.map((row, idx) => {
                                                const matches =
                                                    row.nameQuery.trim().length < 1
                                                        ? []
                                                        : filterIngredientsForCombobox(ingredientDatabase, row.nameQuery, 15);
                                                return (
                                                    <div key={idx} className="rounded-[12px] border border-gray-100 bg-[#F8F9F6] p-3">
                                                        <div className="grid gap-4 md:grid-cols-[1fr_100px_90px_auto] md:items-end">
                                                            <div className="relative min-w-0">
                                                                <TextInput
                                                                    id={`ingredient-combobox-${idx}`}
                                                                    label="Ingredient"
                                                                    placeholder="Type to search…"
                                                                    value={row.selectedName || row.nameQuery}
                                                                    onChange={(e) => {
                                                                        const v = e.target.value;
                                                                        setIngredientRows((prev) =>
                                                                            prev.map((r, i) =>
                                                                                i === idx
                                                                                    ? { ...r, nameQuery: v, selectedName: '', ingredientId: null }
                                                                                    : r,
                                                                            ),
                                                                        );
                                                                    }}
                                                                    autoComplete="off"
                                                                    role="combobox"
                                                                    aria-expanded={activeSuggestRow === idx && matches.length > 0}
                                                                    aria-controls={
                                                                        matches.length > 0 ? `ingredient-listbox-${idx}` : undefined
                                                                    }
                                                                    aria-autocomplete="list"
                                                                    onFocus={() => {
                                                                        setActiveSuggestRow(idx);
                                                                    }}
                                                                    className="!max-w-none"
                                                                />
                                                                {activeSuggestRow === idx && matches.length > 0 && ingredientSuggestRect
                                                                    ? createPortal(
                                                                          <div
                                                                              data-meal-library-ingredient-suggest
                                                                              className="fixed z-[9999]"
                                                                              style={{
                                                                                  left: `${ingredientSuggestRect.left}px`,
                                                                                  top: `${ingredientSuggestRect.top + 8}px`,
                                                                                  width: `${ingredientSuggestRect.width}px`,
                                                                              }}
                                                                          >
                                                                              <div
                                                                                  id={`ingredient-listbox-${idx}`}
                                                                                  role="listbox"
                                                                                  className="w-full rounded-[12px] border border-[#E5E7EB] bg-white p-2 shadow-2xl"
                                                                              >
                                                                                  <div className="max-h-56 overflow-auto">
                                                                                      {matches.map((m) => (
                                                                                          <button
                                                                                              key={m.id != null ? `ing-${m.id}` : m.name}
                                                                                              type="button"
                                                                                              role="option"
                                                                                              className="flex w-full items-center justify-between gap-3 rounded-[12px] px-4 py-2 text-left font-montserrat text-sm font-bold text-[#262A22] transition-colors hover:bg-[#F8F9F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-inset"
                                                                                              onClick={() => {
                                                                                                  setIngredientRows((prev) =>
                                                                                                      prev.map((r, i) =>
                                                                                                          i === idx
                                                                                                              ? {
                                                                                                                    ...r,
                                                                                                                    selectedName: m.name,
                                                                                                                    nameQuery: m.name,
                                                                                                                    ingredientId:
                                                                                                                        typeof m.id === 'number' &&
                                                                                                                        Number.isFinite(m.id)
                                                                                                                            ? m.id
                                                                                                                            : null,
                                                                                                                }
                                                                                                              : r,
                                                                                                      ),
                                                                                                  );
                                                                                                  setActiveSuggestRow(null);
                                                                                              }}
                                                                                          >
                                                                                              <span className="min-w-0 truncate">{m.name}</span>
                                                                                              <span className="shrink-0 text-xs font-medium text-[#555555]">
                                                                                                  per 100g
                                                                                              </span>
                                                                                          </button>
                                                                                      ))}
                                                                                  </div>
                                                                              </div>
                                                                          </div>,
                                                                          document.body,
                                                                      )
                                                                    : null}
                                                            </div>

                                                            <TextInput
                                                                label="Amount"
                                                                type="number"
                                                                placeholder="100"
                                                                value={row.amount}
                                                                onChange={(e) =>
                                                                    setIngredientRows((prev) =>
                                                                        prev.map((r, i) => (i === idx ? { ...r, amount: e.target.value } : r)),
                                                                    )
                                                                }
                                                                className="!max-w-none text-center"
                                                            />

                                                            <DropdownTextInput
                                                                label="Unit"
                                                                value={row.unit}
                                                                options={UNIT_OPTIONS}
                                                                onChange={(v) =>
                                                                    setIngredientRows((prev) =>
                                                                        prev.map((r, i) => (i === idx ? { ...r, unit: v } : r)),
                                                                    )
                                                                }
                                                                className="!max-w-none text-center"
                                                            />

                                                            <div className="flex justify-end">
                                                                <PillButton
                                                                    label="Remove"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setIngredientRows((prev) =>
                                                                            prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx),
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <div className="border-t border-gray-200 bg-white px-10 pb-10 pt-6">
                                    {mealSaveError ? (
                                        <p
                                            className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                                            role="alert"
                                        >
                                            {mealSaveError}
                                        </p>
                                    ) : null}
                                    {mealToEdit?.editForm?.id ? (
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <Button
                                                label="Update meal"
                                                variant="primary"
                                                type="button"
                                                disabled={!canSave}
                                                onClick={handleUpdateExistingMeal}
                                                className="w-full justify-center"
                                            />
                                            <Button
                                                label="Save as new copy"
                                                variant="primary"
                                                type="button"
                                                disabled={!canSave}
                                                onClick={handleSaveMealAsNewCopy}
                                                className="w-full justify-center"
                                            />
                                        </div>
                                    ) : (
                                        <Button
                                            label="Save meal"
                                            variant="primary"
                                            type="button"
                                            disabled={!canSave}
                                            onClick={handleSaveCreateMeal}
                                            className="w-full justify-center"
                                        />
                                    )}
                                </div>
                            </div>

                            {/* Right: Nutrition Summary */}
                            <div className="max-h-[85vh] overflow-y-auto bg-[#F8F9F6] px-10 pb-10 pt-14">
                                <div className="sticky top-0">
                                    <div className="min-w-0 pr-12">
                                    <h3 className="font-montserrat text-[18px] font-bold tracking-tight text-[#262A22]">
                                        {isBulkRecipe ? 'Nutrition Summary (Per Serving)' : 'Nutrition Summary'}
                                    </h3>
                                    <p className="mt-1 font-body text-sm text-[#555555]">
                                        {isBulkRecipe
                                            ? 'What one serving looks like from your ingredient rollup ÷ number of servings. Batch macro fields on the left are not shown here.'
                                            : 'Live totals from selected ingredients (per 100 g library values).'}
                                    </p>
                                    </div>

                                    <div className="mt-4 rounded-[12px] border border-gray-200 bg-white p-4 shadow-sm">
                                        <div className="mb-4">
                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                Safety alerts
                                            </p>
                                            <div className="mt-2">
                                                {safetyFormAlerts.length > 0 ? (
                                                    <SafetyAlerts
                                                        alerts={safetyFormAlerts.map((label) => ({
                                                            label,
                                                            variant:
                                                                label === G6PD_TRIGGER_SAFETY_LABEL ? 'g6pd' : 'allergy',
                                                        }))}
                                                    />
                                                ) : (
                                                    <p className="font-body text-sm text-[#555555]">
                                                        No common-allergen ingredients detected in the current lines.
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {nutritionSummarySections.length > 0 ? (
                                            <div className="overflow-hidden rounded-[12px] border border-gray-200 bg-white">
                                                <table className="w-full border-collapse text-left text-sm">
                                                    <thead>
                                                        <tr className="border-b border-gray-200 bg-[#F8F9F6]">
                                                            <th className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                                Nutrient
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                                {isBulkRecipe ? 'Per serving (system)' : nutritionSummaryTableValueLabel}
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {nutritionSummarySections.map((sec) => (
                                                            <Fragment key={sec.title}>
                                                                <tr className="bg-[#F8F9F6]">
                                                                    <td
                                                                        colSpan={2}
                                                                        className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]"
                                                                    >
                                                                        {sec.title}
                                                                    </td>
                                                                </tr>
                                                                {sec.rows.map((r) => (
                                                                    <tr key={`${sec.title}-${r.label}`} className="border-b border-gray-100">
                                                                        <td className="px-3 py-2 font-body text-[#374151]">{r.label}</td>
                                                                        <td
                                                                            className={[
                                                                                'px-3 py-2 text-right font-montserrat text-sm font-bold tabular-nums text-[#1F2937]',
                                                                                r.valueClass ?? '',
                                                                            ].join(' ')}
                                                                        >
                                                                            {r.value}
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </Fragment>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="rounded-[12px] border border-dashed border-gray-200 bg-[#F8F9F6] px-3 py-4 font-body text-sm text-[#555555]">
                                                {isBulkRecipe
                                                    ? parseBulkServingsCount(bulkServingsCount) == null
                                                        ? 'Enter a valid number of servings so ingredient totals can be divided into per-serving system nutrition.'
                                                        : 'Add verified ingredient lines (or nutrition that resolves from your recipe) to show system per-serving totals here. Batch macro fields on the left remain full-batch values and are not used for this summary.'
                                                    : 'Select verified ingredients to see weighted nutrition totals, automated safety alerts, and program highlights.'}
                                            </p>
                                        )}

                                        <div className="mt-4 border-t border-gray-100 pt-4">
                                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                                Sickle Cell Highlights
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {scBadges.length > 0 ? (
                                                    scBadges.map((b) => <NutrientBadge key={b} type={b} />)
                                                ) : (
                                                    <p className="font-body text-sm text-[#555555]">—</p>
                                                )}
                                            </div>
                                        </div>

                                        {categoryWarningsForModal.length > 0 ? (
                                            <div className="mt-4 rounded-[12px] border border-amber-200 bg-amber-50 p-3">
                                                <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-amber-900">
                                                    Category warnings
                                                </p>
                                                <ul className="mt-2 list-inside list-disc space-y-1 font-body text-sm text-amber-950">
                                                    {categoryWarningsForModal.map((w) => (
                                                        <li key={w}>{w}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}

            {g6pdToastVisible ? (
                <div
                    role="alert"
                    className="fixed bottom-6 left-1/2 z-[200] w-[min(92vw,520px)] -translate-x-1/2 rounded-[12px] border-2 border-[#B91C1C] bg-[#FEF2F2] px-4 py-3 shadow-lg"
                >
                    <p className="font-montserrat text-sm font-bold text-[#991B1B]">G6PD warning</p>
                    <p className="mt-1 font-body text-sm text-[#7F1D1D]">
                        Warning: This meal contains ingredients unsafe for G6PD deficiency.
                    </p>
                </div>
            ) : null}
        </div>
    );
}

/** Inertia page: supplies flash + shared Meal Craft URLs from {@link HandleInertiaRequests}, then renders {@link MealLibraryPageContent}. */
function MealLibraryPage(props) {
    const { props: pageProps } = usePage();
    const flashSuccess = typeof pageProps.flash?.success === 'string' ? pageProps.flash.success : null;
    const flashError = typeof pageProps.flash?.error === 'string' ? pageProps.flash.error : null;
    const mealCsvImportFlash =
        pageProps.flash?.mealCsvImportResult && typeof pageProps.flash.mealCsvImportResult === 'object'
            ? pageProps.flash.mealCsvImportResult
            : null;
    const sharedMealUrls = mealLibraryUrls(pageProps);
    const mealLibrarySchemaNotice =
        typeof props.mealLibrarySchemaNotice === 'string'
            ? props.mealLibrarySchemaNotice
            : typeof pageProps.mealLibrarySchemaNotice === 'string'
              ? pageProps.mealLibrarySchemaNotice
              : (pageProps.mealCraft?.notices?.mealLibrarySchema ?? null);

    return (
        <MealLibraryPageContent
            {...props}
            cyclePhases={
                Array.isArray(props.cyclePhases) && props.cyclePhases.length > 0
                    ? props.cyclePhases
                    : cyclePhasesFromPage(pageProps)
            }
            mealBulkDestroyUrl={resolveUrl(props.mealBulkDestroyUrl, pageProps.mealBulkDestroyUrl ?? sharedMealUrls.bulkDestroy)}
            mealReorderUrl={resolveUrl(props.mealReorderUrl, pageProps.mealReorderUrl ?? sharedMealUrls.reorder)}
            mealStoreUrl={resolveUrl(props.mealStoreUrl, sharedMealUrls.store)}
            csvMealCraftTemplateUrl={resolveUrl(props.csvMealCraftTemplateUrl, sharedMealUrls.mealCraftTemplate)}
            csvExportUrl={resolveUrl(props.csvExportUrl, sharedMealUrls.exportCsv)}
            csvImportUrl={resolveUrl(props.csvImportUrl, sharedMealUrls.importCsv)}
            flashSuccess={flashSuccess}
            flashError={flashError}
            mealCsvImportFlash={mealCsvImportFlash}
            pendingMealImports={
                Array.isArray(props.pendingMealImports)
                    ? props.pendingMealImports
                    : Array.isArray(pageProps.pendingMealImports)
                      ? pageProps.pendingMealImports
                      : []
            }
            mealLibrarySchemaNotice={mealLibrarySchemaNotice}
            csrfToken={typeof pageProps.csrfToken === 'string' ? pageProps.csrfToken : ''}
        />
    );
}

MealLibraryPage.layout = adminInertiaLayout;

export default MealLibraryPage;
