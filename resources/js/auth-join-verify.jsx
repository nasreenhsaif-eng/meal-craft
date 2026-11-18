import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import JoinVerifyPage from './Pages/Auth/JoinVerifyPage.jsx';

const rootEl = document.getElementById('mc-auth-join-verify-root');
const configEl = document.getElementById('mc-auth-join-verify-config');

if (rootEl && configEl) {
    let config = {};
    try {
        config = JSON.parse(configEl.textContent ?? '{}');
    } catch {
        config = {};
    }

    createRoot(rootEl).render(
        <StrictMode>
            <JoinVerifyPage
                formAction={config.formAction}
                resendAction={config.resendAction}
                csrfToken={config.csrfToken}
                email={config.email ?? ''}
                maskedPhone={config.maskedPhone ?? ''}
                codeError={config.codeError ?? ''}
                statusMessage={config.statusMessage ?? ''}
                warningMessage={config.warningMessage ?? ''}
                previewCode={config.previewCode ?? ''}
                deliveryWarning={config.deliveryWarning ?? ''}
            />
        </StrictMode>,
    );
}
