import MealCraftLogo from '../../Atoms/Logo/MealCraftLogo.jsx';

/**
 * Horizontal brand lockup — leaf mark, divider, stacked wordmark + anti-inflammatory tagline.
 *
 * @param {{ className?: string }} props
 */
export default function AntiInflammatoryHorizontalLockup({ className = '' }) {
    return (
        <div className={`inline-flex min-w-0 max-w-full items-center gap-2.5 ${className}`.trim()}>
            <MealCraftLogo variant="leaf" color="green" width={28} alt="" className="shrink-0" />
            <div className="h-8 w-px shrink-0 bg-gray-200" aria-hidden="true" />
            <div className="flex min-w-0 flex-col justify-center leading-none">
                <span className="font-montserrat text-[11px] font-bold uppercase tracking-[0.12em] text-[#262A22]">
                    Meal Craft
                </span>
                <span className="mt-0.5 font-montserrat text-[9px] font-semibold uppercase tracking-[0.14em] text-[#555555]/80">
                    Anti-inflammatory
                </span>
            </div>
        </div>
    );
}
