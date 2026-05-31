/**
 * Compact horizontal kg vs lb toggle for the onboarding weight step.
 *
 * @param {{
 *   value: 'kg' | 'lb';
 *   onChange: (value: 'kg' | 'lb') => void;
 *   className?: string;
 * }} props
 */
export function WeightUnitToggle({ value, onChange, className = '' }) {
    const options = [
        { id: 'kg', label: 'kg' },
        { id: 'lb', label: 'lb' },
    ];

    return (
        <div
            className={[
                'flex shrink-0 flex-row items-center overflow-hidden rounded-[10px] border border-[#5A6B44] bg-white p-0.5',
                className,
            ].join(' ')}
            role="group"
            aria-label="Weight unit"
        >
            {options.map((option) => {
                const active = value === option.id;

                return (
                    <button
                        key={option.id}
                        type="button"
                        onClick={() => onChange(option.id)}
                        aria-pressed={active}
                        className={[
                            'rounded-[8px] px-2.5 py-1.5 font-montserrat text-xs font-bold leading-none transition-colors duration-200',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-1',
                            active
                                ? 'bg-[#5A6B44] text-white'
                                : 'bg-transparent text-[#364153] hover:bg-[#6E8C47]/10',
                        ].join(' ')}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

export default WeightUnitToggle;
