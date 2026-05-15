import { SICKLE_CELL_BADGE_TOOLTIPS } from '../../../meal-library/sickleCellNutrientRdi.ts';

const NUTRIENT_STYLES = {
    Folate: { label: 'FOLATE', color: '#8F55A8' },
    B12: { label: 'VITAMIN B12', color: '#5A723A' },
    B6: { label: 'VITAMIN B6', color: '#4A6FA5' },
    'Vitamin D & Calcium': { label: 'VITAMIN D & CALCIUM', color: '#2F6B8F' },
    Zinc: { label: 'ZINC', color: '#916F19' },
    Antioxidants: { label: 'ANTIOXIDANTS', color: '#7B4B94' },
    Iron: { label: 'IRON', color: '#C44F5D' },
    Magnesium: { label: 'MAGNESIUM', color: '#2F4C9B' },
    'Sickle Cell': { label: 'Sickle cell', color: '#6B4C9A' },
    'G6PD Alert': {
        label: 'G6PD ALERT',
        color: '#B91C1C',
        highVisibility: true,
    },
    'G6PD Safety Alert': {
        label: 'G6PD SAFETY ALERT',
        color: '#B91C1C',
        highVisibility: true,
    },
};

export const NUTRIENT_BADGE_TYPES = Object.keys(NUTRIENT_STYLES);

function resolveTooltip(type, tooltipProp) {
    if (typeof tooltipProp === 'string' && tooltipProp.trim() !== '') {
        return tooltipProp.trim();
    }

    return SICKLE_CELL_BADGE_TOOLTIPS[type] ?? null;
}

export default function NutrientBadge({ type, tooltip, className = '' }) {
    const style = NUTRIENT_STYLES[type];

    if (!style) {
        return null;
    }

    const highVisibility = style.highVisibility === true;
    const resolvedTooltip = resolveTooltip(type, tooltip);

    return (
        <span
            title={resolvedTooltip ?? undefined}
            className={[
                'inline-flex cursor-help items-center rounded-[4px] px-3 py-1 font-montserrat text-[11px] font-bold leading-none tracking-wide uppercase whitespace-nowrap',
                highVisibility
                    ? 'bg-[#FEE2E2] text-[#991B1B] ring-2 ring-[#B91C1C]/60 shadow-sm'
                    : 'bg-white',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            style={
                highVisibility
                    ? undefined
                    : {
                          color: style.color,
                          border: `1px solid ${style.color}`,
                      }
            }
        >
            {style.label}
        </span>
    );
}
