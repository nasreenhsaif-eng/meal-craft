import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import JoinPage from './Pages/Auth/JoinPage.jsx';

const rootEl = document.getElementById('mc-auth-join-root');
const configEl = document.getElementById('mc-auth-join-config');

if (rootEl && configEl) {
    let config = {};
    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <JoinPage
                formAction={config.formAction}
                csrfToken={config.csrfToken}
                loginHref={config.loginHref ?? '/login'}
                initialName={config.initialName ?? ''}
                initialEmail={config.initialEmail ?? ''}
                initialPhone={config.initialPhone ?? ''}
                nameError={config.nameError ?? ''}
                emailError={config.emailError ?? ''}
                phoneError={config.phoneError ?? ''}
                passwordError={config.passwordError ?? ''}
                statusMessage={config.statusMessage ?? ''}
                errorMessage={config.errorMessage ?? ''}
            />
        </StrictMode>,
    );
}
