import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';
import Button from './Atoms/Button.jsx';
import { useState } from 'react';
import { MEAL_CARD_DECK_SHELL_CLASS } from './Consultation/consultationNanoCardWidth.js';

/**
 * Same fixed shell as {@link MealCard} / consultation deck cards (`240px`).
 */
const DECK_SHELL = `${MEAL_CARD_DECK_SHELL_CLASS} shadow-md font-montserrat`;

/**
 * MealPlanCard — protocol plan card aligned to meal card size and layout.
 *
 * @param {string} [props.title]
 * @param {string | null} [props.dateRangeLabel]
 * @param {string | null} [props.imageUrl]
 * @param {string} [props.imageAlt]
 * @param {() => void} [props.onPrimaryAction]
 * @param {string} [props.primaryActionLabel]
 * @param {string} [props.className]
 */
export default function MealPlanCard({
    title,
    dateRangeLabel = null,
    imageUrl,
    imageAlt = '',
    onPrimaryAction,
    primaryActionLabel = 'View details',
    className = '',
}) {
    const [imageBroken, setImageBroken] = useState(false);

    const showImage = Boolean(imageUrl) && !imageBroken;
    const resolvedTitle = String(title ?? '').trim();
    if (!resolvedTitle) {
        return null;
    }

    const btnRow = '!h-[36px] !min-h-[36px] !px-3 !text-[12px]';

    return (
        <article className={`relative ${DECK_SHELL} ${className}`.trim()}>
            <div className="relative flex w-full min-h-0 flex-1 flex-col overflow-hidden rounded-[12px] bg-white shadow-none">
                <div className="relative aspect-[4/3] w-full shrink-0 overflow-hidden bg-[#F8F9F6]">
                    {showImage ? (
                        <img
                            src={imageUrl}
                            alt={imageAlt || resolvedTitle}
                            className="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                            onError={() => setImageBroken(true)}
                        />
                    ) : (
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
                                {resolvedTitle}
                            </h3>
                        </div>
                    )}
                </div>

                <div className="relative flex flex-col gap-0 px-3 pb-2 pt-0">
                    {showImage ? (
                        <div className="min-h-[2.5rem] shrink-0">
                            <h3
                                className="mt-2 w-full text-center text-[17px] font-bold leading-snug tracking-tight text-[#262A22]"
                                style={{
                                    display: '-webkit-box',
                                    WebkitBoxOrient: 'vertical',
                                    overflow: 'hidden',
                                    WebkitLineClamp: 2,
                                }}
                            >
                                {resolvedTitle}
                            </h3>
                        </div>
                    ) : null}

                    {dateRangeLabel ? (
                        <p
                            className={`text-center font-body text-sm font-semibold text-[#5A6B44] ${
                                showImage ? 'mt-1' : 'mt-2'
                            }`}
                        >
                            {dateRangeLabel}
                        </p>
                    ) : null}

                    <div className="mt-2 w-full shrink-0 pb-0.5">
                        <Button
                            type="button"
                            label={primaryActionLabel}
                            variant="ghost"
                            className={`w-full justify-center rounded-[12px] border border-[#5A6B44] ${btnRow}`}
                            onClick={onPrimaryAction}
                        />
                    </div>
                </div>
            </div>
        </article>
    );
}
