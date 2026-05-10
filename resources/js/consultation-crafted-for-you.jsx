import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import CraftedForYouPage from './Pages/Consultation/CraftedForYouPage.jsx';

const rootEl = document.getElementById('mc-consultation-crafted-root');
const configEl = document.getElementById('mc-consultation-crafted-config');

let closeHref = '';
if (configEl) {
    try {
        const raw = JSON.parse(configEl.textContent ?? '{}');
        if (typeof raw.closeHref === 'string') {
            closeHref = raw.closeHref;
        }
    } catch {
        closeHref = '';
    }
}

if (rootEl) {
    createRoot(rootEl).render(
        <StrictMode>
            <CraftedForYouPage closeHref={closeHref || undefined} />
        </StrictMode>,
    );
}
