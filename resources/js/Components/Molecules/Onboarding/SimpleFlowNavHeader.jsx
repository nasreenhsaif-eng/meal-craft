import MealCraftLogo from '../../Atoms/Logo/MealCraftLogo.jsx';

/**
 * Minimal flow chrome — back control + centered Meal Craft lockup (food-filters style, no step rail).
 *
 * @param {{
 *   onBack: () => void;
 *   ariaLabel?: string;
 * }} props
 */
export default function SimpleFlowNavHeader({ onBack, ariaLabel = 'Go back' }) {
    return (
        <header className="w-full" aria-label="Flow navigation">
            <div className="grid w-full grid-cols-[2.5rem_1fr_2.5rem] items-center gap-x-2">
                <button
                    type="button"
                    onClick={onBack}
                    aria-label={ariaLabel}
                    className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-base leading-none text-[#262A22] outline-none transition [-webkit-tap-highlight-color:transparent] hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2"
                >
                    ←
                </button>
                <div className="flex min-w-0 justify-center">
                    <MealCraftLogo
                        variant="minimal"
                        width={108}
                        alt="Meal Craft"
                        className="h-auto max-w-full"
                    />
                </div>
                <span className="block h-10 w-10" aria-hidden="true" />
            </div>
        </header>
    );
}
