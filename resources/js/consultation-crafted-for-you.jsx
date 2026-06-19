import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import CraftedForYouPage from './Pages/Consultation/CraftedForYouPage.jsx';
import { syncCsrfMetaTag } from './lib/csrfToken.js';

const rootEl = document.getElementById('mc-consultation-crafted-root');
const configEl = document.getElementById('mc-consultation-crafted-config');

/** @type {{ closeHref?: string; homeHref?: string; summaryHref?: string; loginUrl?: string; signOutUrl?: string; csrfToken?: string; isCustomerAccount?: boolean; isAdminPreview?: boolean; pageEyebrow?: string; adaptedMenuUrl?: string; planTier?: number | null; planTiers?: number[] }} */
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

if (typeof config.csrfToken === 'string' && config.csrfToken.trim() !== '') {
    syncCsrfMetaTag(config.csrfToken);
}

if (rootEl) {
    rootEl.classList.add('h-full', 'min-h-0');

    createRoot(rootEl).render(
        <StrictMode>
            <CraftedForYouPage
                closeHref={typeof config.closeHref === 'string' ? config.closeHref : undefined}
                homeHref={typeof config.homeHref === 'string' ? config.homeHref : undefined}
                summaryHref={typeof config.summaryHref === 'string' ? config.summaryHref : undefined}
                loginUrl={typeof config.loginUrl === 'string' ? config.loginUrl : undefined}
                signOutUrl={typeof config.signOutUrl === 'string' ? config.signOutUrl : undefined}
                csrfToken={typeof config.csrfToken === 'string' ? config.csrfToken : undefined}
                isCustomerAccount={config.isCustomerAccount === true}
                isAdminPreview={config.isAdminPreview === true}
                pageEyebrow={typeof config.pageEyebrow === 'string' ? config.pageEyebrow : undefined}
                adaptedMenuUrl={
                    typeof config.adaptedMenuUrl === 'string' ? config.adaptedMenuUrl : '/api/menu/adapted'
                }
                initialPlanTier={typeof config.planTier === 'number' ? config.planTier : null}
            />
        </StrictMode>,
    );
}
