import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import LandingPage from './Pages/Landing/LandingPage.jsx';

const rootEl = document.getElementById('mc-auth-welcome-root');
const configEl = document.getElementById('mc-auth-welcome-config');

if (rootEl && configEl) {
    let config = {};

    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <LandingPage loginHref={config.loginHref ?? '/login'} />
        </StrictMode>,
    );
}
