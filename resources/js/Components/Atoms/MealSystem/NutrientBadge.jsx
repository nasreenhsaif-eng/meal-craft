const NUTRIENT_STYLES = {
    B12: { label: 'B12', color: '#5A723A' },
    Iron: { label: 'IRON', color: '#C44F5D' },
    Magnesium: { label: 'MAGNESIUM', color: '#2F4C9B' },
    Zinc: { label: 'ZINC', color: '#916F19' },
    Folate: { label: 'FOLATE', color: '#8F55A8' },
    'Sickle Cell': { label: 'Sickle cell', color: '#6B4C9A' },
};

export const NUTRIENT_BADGE_TYPES = Object.keys(NUTRIENT_STYLES);

export default function NutrientBadge({ type, className = '' }) {
    const style = NUTRIENT_STYLES[type];

    if (!style) {
        return null;
    }

    return (
        <span
            className={`inline-flex items-center rounded-[4px] bg-white px-3 py-1 font-montserrat text-[11px] font-bold leading-none tracking-wide uppercase whitespace-nowrap ${className}`.trim()}
            style={{
                color: style.color,
                border: `1px solid ${style.color}`,
            }}
        >
            {style.label}
        </span>
    );
}

