import { DietaryTag, DIETARY_TAG_OPTIONS } from './DietaryTags.jsx';

/**
 * Multi-select tags (plan workstation).
 * Keeps the "Pure" tag styling when selected, and uses an outlined state when unselected.
 *
 * @param {{
 *  options?: string[];
 *  value: string[];
 *  onChange: (next: string[]) => void;
 *  className?: string;
 * }} props
 */
export default function DietaryTagsMultiSelect({ options = DIETARY_TAG_OPTIONS, value, onChange, className = '' }) {
    const selected = new Set(value ?? []);

    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="group" aria-label="Meal plan tags">
            {options.map((t) => {
                const isSelected = selected.has(t);
                return (
                    <button
                        key={t}
                        type="button"
                        aria-pressed={isSelected}
                        onClick={() => {
                            const next = new Set(selected);
                            if (next.has(t)) {
                                next.delete(t);
                            } else {
                                next.add(t);
                            }
                            onChange(Array.from(next));
                        }}
                        className={[
                            'inline-flex rounded-[12px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2',
                            isSelected ? '' : 'ring-1 ring-[#5A6B44]/25 hover:ring-[#5A6B44]/40',
                        ].join(' ')}
                    >
                        {isSelected ? (
                            <DietaryTag label={t} />
                        ) : (
                            <span className="box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center gap-2 rounded-full px-3 py-1 font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide whitespace-nowrap text-[#5A6B44]">
                                {t.toUpperCase()}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

