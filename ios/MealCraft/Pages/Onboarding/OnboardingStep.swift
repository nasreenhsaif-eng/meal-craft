import Foundation

/// A single screen in the profile onboarding wizard.
///
/// Phase 1 implements the **male flow** only (no period tracking).
/// Order matches product spec; `foodFilter` precedes `dailyTargetSummary`.
enum OnboardingStep: Int, CaseIterable, Hashable, Identifiable {
    case gender
    case birthday
    case height
    case weight
    case targetWeight
    case activity
    case dietProtocol
    case foodFilter
    case dailyTargetSummary

    var id: Int { rawValue }

    /// Linear sequence for customers who skip period tracking.
    static let maleFlow: [OnboardingStep] = [
        .gender,
        .birthday,
        .height,
        .weight,
        .targetWeight,
        .activity,
        .dietProtocol,
        .foodFilter,
        .dailyTargetSummary,
    ]

    /// Human-readable title for progress UI or debugging.
    var title: String {
        switch self {
        case .gender: "Gender"
        case .birthday: "Birthday"
        case .height: "Height"
        case .weight: "Weight"
        case .targetWeight: "Target weight"
        case .activity: "Activity"
        case .dietProtocol: "Diet protocol"
        case .foodFilter: "Food filters"
        case .dailyTargetSummary: "Daily targets"
        }
    }

    func next(in flow: [OnboardingStep] = maleFlow) -> OnboardingStep? {
        guard let index = flow.firstIndex(of: self), index + 1 < flow.count else {
            return nil
        }

        return flow[index + 1]
    }

    func previous(in flow: [OnboardingStep] = maleFlow) -> OnboardingStep? {
        guard let index = flow.firstIndex(of: self), index > 0 else {
            return nil
        }

        return flow[index - 1]
    }
}
