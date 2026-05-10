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
    const values = { calories, protein, carbs, fat };

    const resolvedItems = abbreviated ? ABBREV_ITEMS : items;

    const wrap = compact
        ? abbreviated
            ? 'h-[48px] w-full max-w-[min(280px,100%)]'
            : fluid
              ? 'h-auto min-h-[46px] w-full max-w-full'
              : narrow
                ? 'h-[38px] w-[188px]'
                : 'h-[36px] w-[204px] sm:h-[40px] sm:w-[222px]'
        : 'h-[50px] w-[246px]';
    const valueClass = compact
        ? abbreviated
            ? 'text-[14px]'
            : fluid
              ? 'text-[13px] sm:text-[14px]'
              : 'text-[12px]'
        : 'text-[14px]';
    const labelClass = compact
        ? abbreviated
            ? 'text-[10px] font-semibold uppercase tracking-tight'
            : fluid
              ? 'text-[9px] sm:text-[10px]'
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
                <div key={item.key} className="flex flex-col items-center justify-center">
                    <div className={`text-center ${valueClass} font-bold leading-none`} style={{ color: item.color }}>
                        {values[item.key]}
                    </div>
                    <div
                        className={`${labelMargin} text-center ${labelClass} leading-none text-[#666666] ${abbreviated ? '' : 'font-medium'}`}
                    >
                        {item.label}
                    </div>
                </div>
            ))}
        </div>
    );
}

