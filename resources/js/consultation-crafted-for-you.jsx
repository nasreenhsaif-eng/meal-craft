import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import CraftedForYouPage from './Pages/Consultation/CraftedForYouPage.jsx';

const rootEl = document.getElementById('mc-consultation-crafted-root');
const configEl = document.getElementById('mc-consultation-crafted-config');

/** @type {{ closeHref?: string; homeHref?: string; pageEyebrow?: string; adaptedMenuUrl?: string; planTier?: number | null; planTiers?: number[] }} */
let config = {};

if (configEl) {
    try {
        const raw = JSON.parse(configEl.textContent ?? '{}');
        if (raw && typeof raw === 'object') {
            config = raw;
        }
    } catch {
        config = {};
    }
}

if (rootEl) {
    createRoot(rootEl).render(
        <StrictMode>
            <CraftedForYouPage
                closeHref={typeof config.closeHref === 'string' ? config.closeHref : undefined}
                homeHref={typeof config.homeHref === 'string' ? config.homeHref : undefined}
                pageEyebrow={typeof config.pageEyebrow === 'string' ? config.pageEyebrow : undefined}
                adaptedMenuUrl={
                    typeof config.adaptedMenuUrl === 'string' ? config.adaptedMenuUrl : '/api/menu/adapted'
                }
                initialPlanTier={typeof config.planTier === 'number' ? config.planTier : null}
            />
        </StrictMode>,
    );
}
