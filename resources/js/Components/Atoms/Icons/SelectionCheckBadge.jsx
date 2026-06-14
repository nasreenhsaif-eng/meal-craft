/**
 * Solid selected-state check badge — single dark green circle, centered white tick.
 *
 * @param {object} props
 * @param {'sm' | 'md' | 'lg'} [props.size]
 */
export default function SelectionCheckBadge({ size = 'sm' }) {
    const boxClass =
        size === 'lg' ? 'h-9 w-9' : size === 'md' ? 'h-7 w-7' : 'h-6 w-6';
    const iconClass =
        size === 'lg' ? 'h-4 w-4' : size === 'md' ? 'h-3.5 w-3.5' : 'h-3 w-3';

    return (
        <div
            className={`flex shrink-0 items-center justify-center rounded-full bg-[#5A6B44] shadow-none ring-2 ring-white ${boxClass}`}
            aria-hidden="true"
        >
            <svg className={iconClass} viewBox="0 0 12 12" fill="none">
                <path
                    d="M2.5 6.5 5 9l4.5-6"
                    stroke="white"
                    strokeWidth="1.75"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </div>
    );
}
