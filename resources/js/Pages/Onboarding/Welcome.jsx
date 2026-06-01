import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { onboardingFromPage } from '../../meal-craft/mealCraftPageProps.js';
import customerOnboardingLayout from '../../Layouts/customerOnboardingLayout.js';

/**
 * @param {object} props
 * @param {Array<{ value: string, label: string }>} props.steps
 * @param {string} props.currentStep
 */
function OnboardingStepNav({ steps, currentStep }) {
    const currentIndex = steps.findIndex((step) => step.value === currentStep);

    return (
        <ol className="mb-8 flex flex-wrap gap-2">
            {steps.map((step, index) => {
                const active = step.value === currentStep;
                const complete = index < currentIndex;

                return (
                    <li
                        key={step.value}
                        className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${
                            active
                                ? 'bg-[#556C37] text-white'
                                : complete
                                  ? 'bg-[#E8EFE0] text-[#556C37]'
                                  : 'bg-white text-[#777777]'
                        }`}
                    >
                        {step.label}
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * @param {object} props
 * @param {string} props.title
 * @param {string} props.description
 * @param {Array<{ value: string, label: string }>} props.steps
 * @param {string} props.currentStep
 * @param {string} props.customerName
 * @param {boolean} [props.centerHeader]
 * @param {boolean} [props.hideDefaultHeader]
 * @param {string} [props.titleClassName]
 * @param {import('react').ReactNode} props.children
 */
export function OnboardingShell({
    title,
    description,
    steps,
    currentStep,
    customerName,
    centerHeader = false,
    hideDefaultHeader = false,
    titleClassName = '',
    children,
}) {
    const headerAlign = centerHeader ? 'text-center' : '';

    return (
        <CustomerInertiaShell customerName={customerName}>
            <OnboardingStepNav steps={steps} currentStep={currentStep} />
            <div className="rounded-[16px] border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                {hideDefaultHeader ? null : (
                    <>
                        <h1 className={`font-montserrat text-2xl font-semibold text-[#262A22] ${headerAlign} ${titleClassName}`.trim()}>
                            {title}
                        </h1>
                        {description ? (
                            <p className={`mt-2 text-sm text-[#555555] ${headerAlign}`.trim()}>{description}</p>
                        ) : null}
                    </>
                )}
                <div className={`w-full min-w-0 ${hideDefaultHeader ? '' : 'mt-6 sm:mt-8'}`.trim()}>{children}</div>
            </div>
        </CustomerInertiaShell>
    );
}

export default function Welcome() {
    const onboarding = onboardingFromPage(usePage().props);

    return (
        <OnboardingShell
            title="Welcome to Meal Craft"
            description="We will guide you through a short setup so we can personalize your meals and nutrition targets."
            steps={onboarding.steps ?? []}
            currentStep={onboarding.currentStep ?? 'welcome'}
            customerName={onboarding.customerName ?? ''}
        >
            <Button
                type="button"
                label="Get started"
                onClick={() => router.post(onboarding.urls?.welcome ?? '/onboarding/welcome')}
                className="min-w-[160px]"
            />
        </OnboardingShell>
    );
}

Welcome.layout = customerOnboardingLayout;
