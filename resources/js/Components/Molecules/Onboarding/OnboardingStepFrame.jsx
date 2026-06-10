import { OnboardingShell } from '../../../Pages/Onboarding/Welcome.jsx';

/**
 * Renders onboarding step body inside the shared shell, or bare content when embedded in the tab container.
 *
 * @param {{
 *   embedded?: boolean;
 *   title: string;
 *   description?: string;
 *   steps?: Array<{ value: string; label: string }>;
 *   currentStep?: string;
 *   customerName?: string;
 *   centerHeader?: boolean;
 *   hideDefaultHeader?: boolean;
 *   titleClassName?: string;
 *   children: import('react').ReactNode;
 * }} props
 */
export default function OnboardingStepFrame({
    embedded = false,
    title,
    description = '',
    steps = [],
    currentStep = '',
    customerName = '',
    centerHeader = false,
    hideDefaultHeader = false,
    titleClassName = '',
    children,
}) {
    if (embedded) {
        return children;
    }

    return (
        <OnboardingShell
            title={title}
            description={description}
            steps={steps}
            currentStep={currentStep}
            customerName={customerName}
            centerHeader={centerHeader}
            hideDefaultHeader={hideDefaultHeader}
            titleClassName={titleClassName}
        >
            {children}
        </OnboardingShell>
    );
}
