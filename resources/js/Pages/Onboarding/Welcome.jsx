import OnboardingNavHeader from '../../Components/Molecules/Onboarding/OnboardingNavHeader.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { getOnboardingStepIndex } from '../../meal-craft/onboarding/onboardingTabFlow.js';

/**
 * @param {object} props
 * @param {string} props.title
 * @param {string} props.description
 * @param {Array<{ value: string, label: string }>} props.steps
 * @param {string} props.currentStep
 * @param {string} props.customerName
 * @param {Array<{ value: string, label: string }>} [props.visibleSteps]
 * @param {() => void} [props.onBack]
 * @param {boolean} [props.centerHeader]
 * @param {boolean} [props.hideDefaultHeader]
 * @param {string} [props.titleClassName]
 * @param {import('react').ReactNode} [props.navHeader]
 * @param {import('react').ReactNode} props.children
 */
export function OnboardingShell({
    title,
    description,
    steps,
    currentStep,
    customerName,
    visibleSteps,
    onBack,
    centerHeader = false,
    hideDefaultHeader = false,
    titleClassName = '',
    navHeader,
    children,
}) {
    const headerAlign = centerHeader ? 'text-center' : '';
    const stepsForNav = visibleSteps ?? steps;
    const stepIndex = getOnboardingStepIndex(currentStep, stepsForNav);
    const canGoBack = stepIndex > 0;

    const navigation =
        navHeader ??
        (stepsForNav.length > 0 ? (
            <OnboardingNavHeader
                steps={stepsForNav}
                activeStep={currentStep}
                onBack={onBack}
                canGoBack={canGoBack && typeof onBack === 'function'}
            />
        ) : null);

    return (
        <CustomerInertiaShell customerName={customerName} layoutVariant="onboarding">
            <div className="flex w-full min-w-0 flex-col space-y-6 md:space-y-8">
                {navigation}
                <div className="h-auto w-full min-w-0 md:rounded-2xl md:border md:border-gray-100 md:bg-white md:p-8 md:shadow-sm">
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
                    <div className={`w-full min-w-0 overflow-x-hidden ${hideDefaultHeader ? '' : 'mt-4 md:mt-5'}`.trim()}>
                        {children}
                    </div>
                </div>
            </div>
        </CustomerInertiaShell>
    );
}
