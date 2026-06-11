import SwiftUI

/// Example step view: binds selection to shared state and delegates navigation upward.
struct GenderView: View {
    @ObservedObject var userData: OnboardingUserData
    let onNext: () -> Void
    let onBack: () -> Void

    var body: some View {
        OnboardingStepShell(
            title: "Create your profile",
            subtitle: "Select your gender so we can personalize calorie and macro calculations.",
            canGoBack: false,
            isContinueEnabled: userData.isGenderStepValid,
            onBack: onBack,
            onContinue: onNext
        ) {
            HStack(spacing: 16) {
                ForEach(OnboardingGender.allCases) { option in
                    GenderOptionButton(
                        label: option == .male ? "Male" : "Female",
                        systemImage: option == .male ? "figure.stand" : "figure.stand.dress",
                        isSelected: userData.gender == option
                    ) {
                        userData.gender = option
                    }
                }
            }
            .padding(.horizontal)
        }
    }
}

// MARK: - Local UI (replace with your design system components)

private struct GenderOptionButton: View {
    let label: String
    let systemImage: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(spacing: 12) {
                Image(systemName: systemImage)
                    .font(.title2)
                    .frame(width: 44, height: 44)
                    .background(Color(.secondarySystemGroupedBackground))
                    .clipShape(Circle())

                Text(label)
                    .font(.subheadline.weight(.medium))
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 20)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .strokeBorder(isSelected ? Color.accentColor : Color(.separator), lineWidth: isSelected ? 2 : 1)
            )
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

#Preview {
    GenderView(
        userData: OnboardingUserData(),
        onNext: {},
        onBack: {}
    )
}
