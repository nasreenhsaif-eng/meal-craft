import SwiftUI

/// Birthday step — replace placeholder picker with your wheel date UI.
struct BirthdayView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingStepShell(
            title: "When were you born?",
            subtitle: "We use your age to calculate daily calorie and macro targets.",
            canGoBack: true,
            isContinueEnabled: userData.isBirthdayStepValid,
            onBack: onBack,
            onContinue: onNext
        ) {
            DatePicker(
                "Birthday",
                selection: Binding(
                    get: { userData.birthdate ?? defaultBirthdate },
                    set: { userData.birthdate = $0 }
                ),
                in: ...Date(),
                displayedComponents: .date
            )
            .datePickerStyle(.wheel)
            .labelsHidden()
        }
    }

    private var defaultBirthdate: Date {
        Calendar.current.date(byAdding: .year, value: -25, to: Date()) ?? Date()
    }
}
