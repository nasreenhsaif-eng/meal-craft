/**
 * Selectable gender option for onboarding profile setup.
 *
 * @param {{
 *   label: string;
 *   selected?: boolean;
 *   onSelect?: () => void;
 *   icon?: import('react').ReactNode;
 *   className?: string;
 * }} props
 */
export function GenderOptionCard({ label, selected = false, onSelect, icon, className = '' }) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={[
                'group box-border flex shrink-0 flex-col items-center justify-center gap-2 rounded-[12px] border-0 bg-transparent p-4 text-center shadow-none',
                'transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2',
                className,
            ].join(' ')}
        >
            <span
                className={[
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] border-0 p-2 transition-colors duration-200',
                    selected
                        ? 'bg-[#6E8C47]/10 text-[#364153] group-hover:bg-[#6E8C47]/20'
                        : 'bg-transparent text-[#556C37] group-hover:bg-[#6E8C47]/20 group-hover:text-[#364153]',
                ].join(' ')}
                aria-hidden
            >
                {icon}
            </span>
            <span
                className={[
                    'font-montserrat text-sm font-bold leading-tight transition-colors duration-200',
                    selected ? 'text-[#364153]' : 'text-[#364153] group-hover:text-[#262A22]',
                ].join(' ')}
            >
                {label}
            </span>
        </button>
    );
}

function IconMale() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden className="block">
            <circle cx="10" cy="14" r="4" stroke="currentColor" strokeWidth="2" />
            <path d="M14 10l6-6M16 4h4v4" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconFemale() {
    return (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden className="block">
            <circle cx="12" cy="9" r="4" stroke="currentColor" strokeWidth="2" />
            <path d="M12 13v7M9 17h6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** @param {'male' | 'female'} value */
export function genderOptionIcon(value) {
    return value === 'male' ? <IconMale /> : <IconFemale />;
}
