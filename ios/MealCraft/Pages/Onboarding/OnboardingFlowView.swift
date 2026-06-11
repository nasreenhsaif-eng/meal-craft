import SwiftUI

/// Parent container that drives the linear male onboarding flow.
///
/// Child steps receive `OnboardingUserData` plus `onNext` / `onBack` closures so they
/// never reference this type directly.
struct OnboardingFlowView: View {
    @StateObject private var userData = OnboardingUserData()
    @State private var currentStep: OnboardingStep = OnboardingStep.maleFlow.first ?? .gender
    @State private var navigationDirection: NavigationDirection = .forward

    private let flow = OnboardingStep.maleFlow

    var body: some View {
        ZStack {
            stepView(for: currentStep)
                .id(currentStep)
                .transition(stepTransition)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(Color(.systemGroupedBackground))
        .animation(.easeInOut, value: currentStep)
    }

    // MARK: - Step routing

    @ViewBuilder
    private func stepView(for step: OnboardingStep) -> some View {
        switch step {
        case .gender:
            GenderView(userData: userData, onNext: advance, onBack: retreat)

        case .birthday:
            BirthdayView(userData: userData, onNext: advance, onBack: retreat)

        case .height:
            HeightView(userData: userData, onNext: advance, onBack: retreat)

        case .weight:
            WeightView(userData: userData, onNext: advance, onBack: retreat)

        case .targetWeight:
            TargetWeightView(userData: userData, onNext: advance, onBack: retreat)

        case .activity:
            ActivityView(userData: userData, onNext: advance, onBack: retreat)

        case .dietProtocol:
            DietProtocolView(userData: userData, onNext: advance, onBack: retreat)

        case .foodFilter:
            FoodFilterView(userData: userData, onNext: advance, onBack: retreat)

        case .dailyTargetSummary:
            DailyTargetSummary(userData: userData, onNext: finishOnboarding, onBack: retreat)
        }
    }

    // MARK: - Navigation

    private func advance() {
        guard let next = currentStep.next(in: flow) else { return }

        navigationDirection = .forward
        currentStep = next
    }

    private func retreat() {
        guard let previous = currentStep.previous(in: flow) else { return }

        navigationDirection = .backward
        currentStep = previous
    }

    private func finishOnboarding() {
        // Phase 2: persist profile + route to home / plan craft.
    }

    // MARK: - Transitions

    private enum NavigationDirection {
        case forward
        case backward
    }

    private var stepTransition: AnyTransition {
        switch navigationDirection {
        case .forward:
            return .asymmetric(
                insertion: .move(edge: .trailing).combined(with: .opacity),
                removal: .move(edge: .leading).combined(with: .opacity)
            )
        case .backward:
            return .asymmetric(
                insertion: .move(edge: .leading).combined(with: .opacity),
                removal: .move(edge: .trailing).combined(with: .opacity)
            )
        }
    }
}

#Preview("Male onboarding flow") {
    OnboardingFlowView()
}
