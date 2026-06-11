import Foundation

// MARK: - Domain enums (API string values align with Meal Craft backend)

enum OnboardingGender: String, CaseIterable, Identifiable {
    case male
    case female

    var id: String { rawValue }
}

enum OnboardingActivityLevel: String, CaseIterable, Identifiable {
    case sedentary = "sedentary"
    case lightlyActive = "lightly_active"
    case moderatelyActive = "moderately_active"
    case veryActive = "very_active"

    var id: String { rawValue }
}

enum OnboardingDietProtocol: String, CaseIterable, Identifiable {
    case balanced
    case ketobiotic
    case cycleSync = "cycle_sync"
    case sickleCellWarrior = "sickle_cell_warrior"

    var id: String { rawValue }
}

// MARK: - Computed targets (filled before summary step)

struct OnboardingDailyTargets: Equatable {
    var calories: Int
    var proteinGrams: Int
    var carbsGrams: Int
    var fatGrams: Int
    var proteinPercent: Int
    var carbsPercent: Int
    var fatPercent: Int
}

// MARK: - Shared wizard state

/// Observable store passed into every onboarding step.
///
/// Holds profile inputs across the linear male flow. Persist or sync to your API
/// when each step validates successfully.
@MainActor
final class OnboardingUserData: ObservableObject {
    @Published var gender: OnboardingGender?
    @Published var birthdate: Date?
    @Published var heightCm: Double?
    @Published var weightKg: Double?
    @Published var targetWeightKg: Double?
    @Published var activityLevel: OnboardingActivityLevel = .lightlyActive
    @Published var dietProtocol: OnboardingDietProtocol = .balanced
    @Published var foodExclusions: Set<String> = []
    @Published var allergyOther: String = ""
    @Published var computedTargets: OnboardingDailyTargets?

    /// Convenience for steps that need age without exposing `Calendar` in every view.
    var ageInYears: Int? {
        guard let birthdate else { return nil }

        return Calendar.current.dateComponents([.year], from: birthdate, to: Date()).year
    }

    /// Whether the customer can leave the gender step (example validation hook).
    var isGenderStepValid: Bool { gender != nil }

    var isBirthdayStepValid: Bool { birthdate != nil }
}
