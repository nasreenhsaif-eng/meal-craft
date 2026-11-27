import Button from './Atoms/Button.jsx';

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
        <div className="flex flex-wrap gap-2" role="tablist" aria-label={ariaLabel}>
            {tabs.map((tab) => {
                const isActive = tab.id === activeId;
                const count = typeof tab.count === 'number' ? tab.count : null;
                const label = count === null ? tab.label : `${tab.label} (${count})`;

                return (
                    <Button
                        key={tab.id}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        label={label}
                        variant={isActive ? 'primary' : 'tab'}
                        size="sm"
                        className="shrink-0"
                        onClick={() => onChange?.(tab.id)}
                    />
                );
            })}
        </div>
    );
}
