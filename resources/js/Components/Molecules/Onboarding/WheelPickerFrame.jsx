/**
 * Shared fade mask wrapper for onboarding scroll wheels.
 *
 * @param {{ children: import('react').ReactNode; className?: string }} props
 */
export function WheelPickerFrame({ children, className = '' }) {
    return (
        <div
            className={[
                'relative min-h-[220px] w-full overflow-hidden rounded-[16px] border-0 bg-transparent shadow-none',
                className,
            ].join(' ')}
        >
            <div
                className="pointer-events-none absolute inset-x-0 top-0 z-20 h-[72px] bg-gradient-to-b from-white via-white/85 to-transparent"
                aria-hidden
            />
            <div
                className="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-[72px] bg-gradient-to-t from-white via-white/85 to-transparent"
                aria-hidden
            />
            <div className="relative z-0 flex w-full">{children}</div>
        </div>
    );
}
