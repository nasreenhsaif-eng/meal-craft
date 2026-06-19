import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import LoginPage from './Pages/Auth/LoginPage.jsx';

const rootEl = document.getElementById('mc-auth-login-root');
const configEl = document.getElementById('mc-auth-login-config');

if (rootEl && configEl) {
    let config = {};
    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <LoginPage
                formAction={config.formAction}
                csrfToken={config.csrfToken}
                forgotPasswordHref={config.forgotPasswordHref ?? '#'}
                showForgotPassword={config.showForgotPassword !== false}
                signUpHref={config.signUpHref ?? '#'}
                showSignUp={config.showSignUp !== false}
                initialEmail={config.initialEmail ?? ''}
                initialRemember={Boolean(config.initialRemember)}
                emailError={config.emailError ?? ''}
                passwordError={config.passwordError ?? ''}
                statusMessage={config.statusMessage ?? ''}
                errorMessage={config.errorMessage ?? ''}
                splashDurationMs={typeof config.splashDurationMs === 'number' ? config.splashDurationMs : 0}
            />
        </StrictMode>,
    );
}
