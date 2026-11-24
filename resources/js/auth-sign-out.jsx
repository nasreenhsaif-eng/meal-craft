import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import SignOutPage from './Pages/Auth/SignOutPage.jsx';

const rootEl = document.getElementById('mc-auth-sign-out-root');
const configEl = document.getElementById('mc-auth-sign-out-config');

if (rootEl && configEl) {
    let config = {};

    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <SignOutPage
                authenticated={config.authenticated === true}
                email={typeof config.email === 'string' ? config.email : ''}
                logoutAction={typeof config.logoutAction === 'string' ? config.logoutAction : '/logout'}
                csrfToken={typeof config.csrfToken === 'string' ? config.csrfToken : ''}
                joinHref={typeof config.joinHref === 'string' ? config.joinHref : '/join'}
                loginHref={typeof config.loginHref === 'string' ? config.loginHref : '/login'}
            />
        </StrictMode>,
    );
}
