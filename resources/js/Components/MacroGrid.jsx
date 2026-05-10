/**
 * Calories: rounded number or trimmed string.
 *
 * @param {string | number | null | undefined} value
 * @returns {string}
 */
function formatCalorieDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(Math.round(value));
    }

    return String(value).trim();
}

/**
 * Protein/carbs/fat: show the numeric amount only (column labels carry units). Strips a trailing `g`
 * from API strings like `44g` so the cell does not repeat the unit.
 *
 * @param {string | number | null | undefined} value
 * @returns {string}
 */
function formatGramMacroDisplay(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Number.isInteger(value) ? String(value) : String(Number.parseFloat(value.toFixed(1)));
    }

    const s = String(value).trim();
    if (s === '') {
        return '';
    }
    if (/oz|ml|mg|kcal/i.test(s)) {
        return s;
    }
    const strippedGrams = /^(\d+(?:\.\d+)?)\s*g$/i.exec(s);
    if (strippedGrams) {
        return strippedGrams[1];
    }
    if (/^\d+(\.\d+)?$/.test(s)) {
        return s;
    }

    return s;
}

/** Value colors: `#5A6B44` for calories meets WCAG AA on white (replaces `#6E8C47`). */
const DEFAULT_ITEMS = [
    { key: 'calories', label: 'CALORIES', color: '#5A6B44' },
    { key: 'protein', label: 'PROTEIN', color: '#916A00' },
    { key: 'carbs', label: 'CARBS', color: '#8F55A8' },
    { key: 'fat', label: 'FAT', color: '#2F4C9B' },
];

/** Short labels for compact deck cards; values stay bold Montserrat. */
const ABBREV_ITEMS = [
    { key: 'calories', label: 'CAL', color: '#5A6B44' },
    { key: 'protein', label: 'P', color: '#916A00' },
    { key: 'carbs', label: 'C', color: '#8F55A8' },
    { key: 'fat', label: 'F', color: '#2F4C9B' },
];

/**
 * Figma-aligned macro grid (246×50).
 * Borderless by default — parent owns separators.
 *
 * @param {{
 *   calories: string | number;
 *   protein: string | number;
 *   carbs: string | number;
 *   fat: string | number;
 *   items?: { key: 'calories'|'protein'|'carbs'|'fat'; label: string; color: string }[];
 *   ariaLabel?: string;
 *   className?: string;
 *   compact?: boolean;
 *   narrow?: boolean; // with compact: smaller width for tight cards; same labels/typography as compact
 *   abbreviated?: boolean; // with compact: CAL / P / C / F + larger readable type (deck)
 *   fluid?: boolean; // with compact + full labels: stretches to card width — reference deck layout
 * }} props
 */
export default function MacroGrid({
    calories,
    protein,
    carbs,
    fat,
    items = DEFAULT_ITEMS,
    ariaLabel = 'Macros',
    className = '',
    compact = false,
    narrow = false,
    abbreviated = false,
    fluid = false,
}) {
    const values = {
        calories: formatCalorieDisplay(calories),
        protein: formatGramMacroDisplay(protein),
        carbs: formatGramMacroDisplay(carbs),
        fat: formatGramMacroDisplay(fat),
    };

    const resolvedItems = abbreviated ? ABBREV_ITEMS : items;

    const wrap = compact
        ? abbreviated
            ? fluid
                ? 'h-auto min-h-[48px] w-full max-w-full gap-x-1'
                : 'h-[48px] w-full max-w-[min(280px,100%)]'
            : fluid
              ? 'h-auto min-h-[40px] w-full max-w-full gap-x-0.5'
              : narrow
                ? 'h-[38px] w-[188px]'
                : 'h-[36px] w-[204px] sm:h-[40px] sm:w-[222px]'
        : 'h-[50px] w-[246px]';
    const valueClass = compact
        ? abbreviated
            ? fluid
                ? 'text-[15px] tabular-nums sm:text-[16px]'
                : 'text-[14px]'
            : fluid
              ? 'text-[15px] tabular-nums leading-none'
              : 'text-[12px]'
        : 'text-[14px]';
    const labelClass = compact
        ? abbreviated
            ? fluid
                ? 'text-[10px] font-semibold uppercase tracking-wide sm:text-[11px]'
                : 'text-[10px] font-semibold uppercase tracking-tight'
            : fluid
              ? 'text-[7.5px] font-medium uppercase tracking-[0.05em]'
              : 'text-[9px]'
        : 'text-[10px]';
    const labelMargin = compact ? (narrow && !abbreviated && !fluid ? 'mt-0' : 'mt-0.5') : 'mt-1';

    return (
        <div
            className={`grid ${wrap} grid-cols-4 font-montserrat ${className}`.trim()}
            role="group"
            aria-label={ariaLabel}
        >
            {resolvedItems.map((item) => (
                <div
                    key={item.key}
                    className={`flex min-w-0 flex-col items-center justify-center ${compact && fluid && !abbreviated ? 'min-w-0 px-0' : 'px-0.5'}`}
                >
                    <div
                        className={`w-full text-center ${valueClass} font-bold leading-none`}
                        style={{ color: item.color }}
                    >
                        {values[item.key]}
                    </div>
                    <div
                        className={`${labelMargin} w-full min-w-0 max-w-full text-center ${labelClass} leading-none text-[#666666] hyphens-none ${abbreviated ? 'whitespace-nowrap' : 'whitespace-nowrap font-medium'}`}
                    >
                        {item.label}
                    </div>
                </div>
            ))}
        </div>
    );
}

