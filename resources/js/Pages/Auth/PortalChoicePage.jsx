import MealCraftLogo from '../../Components/Atoms/Logo/MealCraftLogo.jsx';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerOnboardingPhoneFrame from '../../Components/Molecules/Customer/CustomerOnboardingPhoneFrame.jsx';

/** Content width inside the onboarding phone frame. */
const contentWidthClass = 'mx-auto w-full max-w-full px-1 text-center';

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
    onboardingHref = '/app',
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
        <CustomerOnboardingPhoneFrame>
            <div className="relative flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-white">
                <main className="relative z-0 flex min-h-0 flex-1 flex-col items-center justify-center overflow-y-auto overscroll-contain px-3 py-6">
                    <div className={`flex w-full flex-col items-center ${contentWidthClass}`}>
                        <MealCraftLogo
                            variant="vertical-smart"
                            width={280}
                            className="mx-auto h-auto max-w-full"
                            alt="Meal Craft"
                        />

                        <header className="mt-8 w-full text-center">
                            <p className="font-montserrat text-sm font-semibold text-[#6E8C47]">{greeting}</p>

                            <h1 className="mt-3 font-montserrat text-2xl font-bold tracking-tight text-heading">
                                Choose your workspace
                            </h1>

                            <p className="mx-auto mt-3 max-w-prose text-base font-medium leading-relaxed text-[#4B5563]">
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
        </CustomerOnboardingPhoneFrame>
    );
}
