import SwiftUI

// MARK: - Placeholder steps (replace with full implementations)

struct HeightView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Height", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct WeightView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Weight", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct TargetWeightView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Target weight", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct ActivityView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Activity", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct DietProtocolView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Diet protocol", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct FoodFilterView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingPlaceholderStep(title: "Food filters", userData: userData, onNext: onNext, onBack: onBack)
    }
}

struct DailyTargetSummary: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingStepShell(
            title: "Your daily targets",
            subtitle: "Review calories and macros before we craft your plan.",
            canGoBack: true,
            isContinueEnabled: true,
            onBack: onBack,
            onContinue: onNext
        ) {
            VStack(spacing: 12) {
                if let targets = userData.computedTargets {
                    Text("\(targets.calories) kcal")
                        .font(.largeTitle.bold())
                    Text("P \(targets.proteinGrams)g · C \(targets.carbsGrams)g · F \(targets.fatGrams)g")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                } else {
                    Text("Targets will appear after profile data is complete.")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)
                        .padding()
                }
            }
        }
    }
}

private struct OnboardingPlaceholderStep: View {
    let title: String
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingStepShell(
            title: title,
            subtitle: "Placeholder — wire your picker UI here.",
            canGoBack: true,
            isContinueEnabled: true,
            onBack: onBack,
            onContinue: onNext
        ) {
            Text("Step in progress")
                .foregroundStyle(.tertiary)
        }
    }
}
