import SwiftUI

/// Shared chrome for onboarding steps (header, back, continue).
struct OnboardingStepShell<Content: View>: View {
    let title: String
    let subtitle: String
    let canGoBack: Bool
    let isContinueEnabled: Bool
    let onBack: () -> Void
    let onContinue: () -> Void
    @ViewBuilder let content: () -> Content

    var body: some View {
        VStack(spacing: 0) {
            if canGoBack {
                HStack {
                    Button("Back", action: onBack)
                        .font(.subheadline)
                    Spacer()
                }
                .padding(.horizontal)
                .padding(.top, 8)
            }

            VStack(spacing: 8) {
                Text(title)
                    .font(.title2.bold())
                    .multilineTextAlignment(.center)

                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
            .padding(.horizontal, 24)
            .padding(.top, canGoBack ? 16 : 32)
            .padding(.bottom, 24)

            content()
                .frame(maxWidth: .infinity, maxHeight: .infinity)

            Button("Continue", action: onContinue)
                .buttonStyle(.borderedProminent)
                .controlSize(.large)
                .frame(maxWidth: .infinity)
                .padding(.horizontal, 24)
                .padding(.bottom, 32)
                .disabled(!isContinueEnabled)
        }
    }
}
