/**
 * Protein family and recipe-category browse tabs for Meal Library and Meal Tiers Library.
 *
 * @param {object} props
 * @param {Array<{ id: string, label: string, count?: number }>} props.tabs
 * @param {string} props.activeId
 * @param {(id: string) => void} props.onChange
 * @param {string} [props.ariaLabel]
 */
export default function MealBrowseTabs({ tabs = [], activeId, onChange, ariaLabel = 'Meal type' }) {
    if (!Array.isArray(tabs) || tabs.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1" role="tablist" aria-label={ariaLabel}>
            {tabs.map((tab) => {
                const isActive = tab.id === activeId;
                const count = typeof tab.count === 'number' ? tab.count : null;
                const label = count === null ? tab.label : `${tab.label} (${count})`;

                return (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        className={`rounded-full px-3 py-1.5 font-montserrat text-xs font-bold tracking-wide ${
                            isActive ? 'bg-[#5A6B44] text-white' : 'bg-[#E8EDE3] text-[#262A22]'
                        }`}
                        onClick={() => onChange?.(tab.id)}
                    >
                        {label}
                    </button>
                );
            })}
        </div>
    );
}
