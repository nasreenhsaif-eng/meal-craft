import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';

/** Same responsive width band as `LoginPage` — 90% on mobile, capped on desktop. */
const contentWidthClass = 'mx-auto w-full max-w-[90%] md:max-w-[440px]';

/** Secondary portal choices — full width, multi-line safe. */
const portalChoiceButtonClass =
    'flex !h-auto min-h-[56px] w-full !max-w-none items-center justify-center px-5 py-4 text-center text-[15px] !leading-normal whitespace-normal sm:text-base';

/**
 * Post-login workspace picker for staff — customer preview vs admin portal.
 *
 * @param {{
 *   userName?: string;
 *   onboardingHref?: string;
 *   adminHref?: string;
 *   onSelectCustomerExperience?: () => void;
 *   onSelectAdminPortal?: () => void;
 * }} props
 */
export default function PortalChoicePage({
    userName = '',
    onboardingHref = '/onboarding/gender',
    adminHref = '/admin/dashboard',
    onSelectCustomerExperience,
    onSelectAdminPortal,
}) {
    const greeting = userName.trim().length > 0 ? `Welcome back, ${userName.trim()}` : 'Welcome back';

    const goToCustomerExperience = () => {
        if (onSelectCustomerExperience) {
            onSelectCustomerExperience();
            return;
        }

        window.location.href = onboardingHref;
    };

    const goToAdminPortal = () => {
        if (onSelectAdminPortal) {
            onSelectAdminPortal();
            return;
        }

        window.location.href = adminHref;
    };

    return (
        <div className="relative min-h-screen w-screen bg-white">
            <main className="flex min-h-screen w-screen flex-col items-center justify-center px-2 py-8 sm:px-4 md:px-6">
                <div className={contentWidthClass}>
                    <div className="flex justify-center">
                        <MealCraftLogo
                            variant="vertical-smart"
                            width={280}
                            className="h-auto max-w-full"
                            alt="Meal Craft"
                        />
                    </div>

                    <header className="mt-8 text-center">
                        <p className="font-montserrat text-sm font-semibold text-[#6E8C47]">{greeting}</p>

                        <h1 className="mt-3 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                            Choose your workspace
                        </h1>

                        <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-[#4B5563] sm:text-lg">
                            Where would you like to go today?
                        </p>
                    </header>

                    <div className="mt-10 flex w-full flex-col gap-4">
                        <Button
                            label="Customer Onboarding / Experience"
                            variant="secondary"
                            className={portalChoiceButtonClass}
                            onClick={goToCustomerExperience}
                        />

                        <Button
                            label="Admin Portal Dashboard"
                            variant="secondary"
                            className={portalChoiceButtonClass}
                            onClick={goToAdminPortal}
                        />
                    </div>
                </div>
            </main>
        </div>
    );
}
