import MealCraftLogo from '../../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../../Components/Atoms/Button.jsx';

/**
 * Library card mock with the published week under the title.
 *
 * @param {{
 *   title?: string;
 *   dateRangeLabel?: string;
 * }} props
 */
export default function LibraryCardMock({
    title = 'Balanced Anti-inflammatory',
    dateRangeLabel = '27th September to 3rd October',
}) {
    return (
        <article className="box-border w-full min-w-[280px] max-w-[310px] overflow-hidden rounded-[12px] border border-gray-200 bg-white shadow-sm">
            <div className="relative w-full overflow-hidden rounded-t-[12px]">
                <div className="aspect-[4/3] w-full bg-[#F8F9F6]">
                    <div className="flex h-full w-full items-center justify-center">
                        <div className="scale-[0.95] opacity-90">
                            <MealCraftLogo variant="seal-sm" />
                        </div>
                    </div>
                </div>
            </div>
            <div className="min-w-0 px-4 pb-6 pt-4 sm:px-5">
                <h3 className="font-montserrat text-[16px] font-bold tracking-tight text-[#262A22]">{title}</h3>
                <p className="mt-1 font-body text-sm font-semibold text-[#5A6B44]">{dateRangeLabel}</p>
                <div className="pt-4">
                    <Button label="View details" variant="primary" className="w-full justify-center" />
                </div>
            </div>
        </article>
    );
}
