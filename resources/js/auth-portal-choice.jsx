import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import PortalChoicePage from './Pages/Auth/PortalChoicePage.jsx';

const rootEl = document.getElementById('mc-auth-portal-choice-root');
const configEl = document.getElementById('mc-auth-portal-choice-config');

if (rootEl && configEl) {
    let config = {};

    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <PortalChoicePage
                userName={config.userName ?? ''}
                onboardingHref={config.onboardingHref ?? '/onboarding/welcome'}
                adminHref={config.adminHref ?? '/admin/dashboard'}
            />
        </StrictMode>,
    );
}
