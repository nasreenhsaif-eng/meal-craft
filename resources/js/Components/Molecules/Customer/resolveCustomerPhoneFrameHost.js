/**
 * Customer phone chrome host for overlays (consultation / onboarding).
 * Falls back to `document.body` when the frame is not mounted.
 *
 * @returns {HTMLElement | null}
 */
export function resolveCustomerPhoneFrameHost() {
    if (typeof document === 'undefined') {
        return null;
    }

    return (
        document.querySelector('[data-customer-onboarding-frame]') ||
        document.querySelector('[data-storybook-onboarding-frame]') ||
        document.body
    );
}
