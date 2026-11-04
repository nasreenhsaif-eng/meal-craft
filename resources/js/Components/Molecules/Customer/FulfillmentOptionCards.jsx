import { useState } from 'react';
import { router } from '@inertiajs/react';
import OnboardingOptionButton from '../../Atoms/Button/OnboardingOptionButton.jsx';
import OnboardingInlineDescription from '../Onboarding/OnboardingInlineDescription.jsx';

/**
 * @typedef {'partner_kitchen' | 'diy'} FulfillmentChoiceId
 */

/**
 * @param {{ className?: string }} props
 */
function IconKitchen({ className = '' }) {
    return (
        <svg className={className} width={28} height={28} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 10h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10ZM8 10V7a4 4 0 0 1 8 0v3"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
function IconDiy({ className = '' }) {
    return (
        <svg className={className} width={28} height={28} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 5h11a2 2 0 0 1 2 2v12H6a2 2 0 0 1-2-2V5ZM17 7h3v12a2 2 0 0 1-2 2h-1M8 9h6M8 13h6"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

/** @type {Array<{
 *   id: FulfillmentChoiceId;
 *   label: string;
 *   description: string;
 *   highlights: string[];
 *   Icon: (props: { className?: string }) => import('react').ReactNode;
 * }>} */
const FULFILLMENT_OPTIONS = [
    {
        id: 'partner_kitchen',
        label: 'Partner Kitchen',
        description:
            'Portion-controlled meals tailored to your calorie and macro targets, prepared fresh and delivered daily.',
        highlights: [
            'Ready-to-eat fresh meals',
            'Prepared according to your dietary profile',
            'Daily doorstep delivery',
        ],
        Icon: IconKitchen,
    },
    {
        id: 'diy',
        label: 'Cook It Yourself (DIY)',
        description:
            'Full access to detailed daily recipes, precise ingredient gram weights, and aggregated shopping lists.',
        highlights: [
            'Step-by-step recipe instructions',
            'Printable daily menus & shopping lists',
            'Ingredient breakdowns matched to your targets',
        ],
        Icon: IconDiy,
    },
];

/**
 * @param {{ items: string[] }} props
 */
function HighlightList({ items }) {
    return (
        <ul className="mt-2 flex flex-col gap-1.5 px-1 pb-1">
            {items.map((item) => (
                <li key={item} className="flex items-start gap-2 font-body text-sm text-[#555555]">
                    <span
                        className="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#6E8C47]/15 text-[#5A6B44]"
                        aria-hidden="true"
                    >
                        <svg className="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none">
                            <path
                                d="M2.5 6.2 4.7 8.5 9.5 3.5"
                                stroke="currentColor"
                                strokeWidth="1.75"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                    </span>
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}

/**
 * Partner Kitchen + DIY choice — vertical option buttons with expandable details + Next
 * (same interaction pattern as diet protocol).
 *
 * @param {{
 *   deliveryUrl?: string;
 *   recipesUrl?: string;
 *   className?: string;
 *   onContinue?: (choice: FulfillmentChoiceId) => void;
 * }} props
 */
export default function FulfillmentOptionCards({
    deliveryUrl = '/checkout/delivery',
    recipesUrl = '/meal-plan/recipes',
    className = '',
    onContinue,
}) {
    /** @type {[FulfillmentChoiceId | '', import('react').Dispatch<import('react').SetStateAction<FulfillmentChoiceId | ''>>]} */
    const [selected, setSelected] = useState(/** @type {FulfillmentChoiceId | ''} */ (''));

    const handleNext = () => {
        if (!selected) {
            return;
        }

        // Consultation receive step: parent owns routing for both choices.
        if (typeof onContinue === 'function') {
            onContinue(selected);
            return;
        }

        if (selected === 'partner_kitchen') {
            router.visit(deliveryUrl);
            return;
        }

        if (selected === 'diy') {
            router.visit(recipesUrl);
        }
    };

    return (
        <>
            <div className={['flex w-full flex-col gap-4', className].join(' ').trim()}>
                <div className="w-full" role="group" aria-label="Receive meal plan options">
                    <div className="flex w-full flex-col gap-2.5">
                        {FULFILLMENT_OPTIONS.map((option) => {
                            const isSelected = selected === option.id;
                            const descriptionId = `fulfillment-desc-${option.id}`;

                            return (
                                <div key={option.id} className="flex w-full flex-col">
                                    <OnboardingOptionButton
                                        label={option.label}
                                        selected={isSelected}
                                        onSelect={() => setSelected(option.id)}
                                        icon={<option.Icon />}
                                        describedBy={descriptionId}
                                    />
                                    <div
                                        className={[
                                            'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                                            isSelected ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                                        ].join(' ')}
                                        aria-hidden={!isSelected}
                                    >
                                        <div className="min-h-0 overflow-hidden">
                                            <OnboardingInlineDescription id={descriptionId}>
                                                {option.description}
                                            </OnboardingInlineDescription>
                                            <HighlightList items={option.highlights} />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {selected ? (
                    <div className="pt-1">
                        <button
                            type="button"
                            onClick={handleNext}
                            className={[
                                'inline-flex h-[50px] w-full min-h-[50px] items-center justify-center rounded-[12px]',
                                'font-montserrat text-[16px] font-bold uppercase leading-none tracking-[0.08em] text-white',
                                'transition-all duration-200 ease-in-out',
                                'border-2 border-protocol-selected bg-protocol-selected',
                                'hover:border-protocol-selected-hover hover:bg-protocol-selected-hover',
                                'active:border-protocol-selected-pressed active:bg-protocol-selected-pressed',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-protocol-selected focus-visible:ring-offset-2',
                            ].join(' ')}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </div>
        </>
    );
}
