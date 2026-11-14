import MealCraftLogo from '../../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../../Components/Atoms/Button.jsx';
import { MEAL_CARD_DECK_SHELL_CLASS } from '../../../Components/Consultation/consultationNanoCardWidth.js';

/**
 * Library card mock — same 240px shell as meal / meal-plan cards.
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
        <article className={`${MEAL_CARD_DECK_SHELL_CLASS} shadow-md font-montserrat`}>
            <div className="relative flex w-full min-h-0 flex-1 flex-col overflow-hidden rounded-[12px] bg-white shadow-none">
                <div className="relative aspect-[4/3] w-full shrink-0 overflow-hidden bg-[#F8F9F6]">
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 px-3">
                        <MealCraftLogo
                            variant="seal-sm"
                            width={56}
                            className="opacity-70"
                            alt="MealCraft"
                            title="MealCraft"
                        />
                        <h3
                            className="w-full text-center text-[17px] font-bold leading-snug tracking-tight text-[#262A22]"
                            style={{
                                display: '-webkit-box',
                                WebkitBoxOrient: 'vertical',
                                overflow: 'hidden',
                                WebkitLineClamp: 2,
                            }}
                        >
                            {title}
                        </h3>
                    </div>
                </div>
                <div className="relative flex flex-col gap-0 px-3 pb-2 pt-0">
                    <p className="mt-2 text-center font-body text-sm font-semibold text-[#5A6B44]">{dateRangeLabel}</p>
                    <div className="mt-2 w-full shrink-0 pb-0.5">
                        <Button
                            label="View details"
                            variant="ghost"
                            className="w-full justify-center rounded-[12px] border border-[#5A6B44] !h-[36px] !min-h-[36px] !px-3 !text-[12px]"
                        />
                    </div>
                </div>
            </div>
        </article>
    );
}
