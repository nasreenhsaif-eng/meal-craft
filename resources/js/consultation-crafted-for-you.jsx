import { createRoot } from 'react-dom/client';
import { Component, StrictMode } from 'react';
import CraftedForYouPage from './Pages/Consultation/CraftedForYouPage.jsx';
import { syncCsrfMetaTag } from './lib/csrfToken.js';

const rootEl = document.getElementById('mc-consultation-crafted-root');
const configEl = document.getElementById('mc-consultation-crafted-config');

/** @type {{ closeHref?: string; homeHref?: string; summaryHref?: string; loginUrl?: string; signOutUrl?: string; csrfToken?: string; isCustomerAccount?: boolean; isAdminPreview?: boolean; pageEyebrow?: string; adaptedMenuUrl?: string; planTier?: number | null; planTiers?: number[]; editDraft?: object | null }} */
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

class ConsultationErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error) {
        // eslint-disable-next-line no-console
        console.error('Consultation page failed to render', error);
    }

    render() {
        if (this.state.error) {
            const message =
                this.state.error instanceof Error
                    ? this.state.error.message
                    : String(this.state.error ?? 'Unknown error');

            return (
                <div className="min-h-[100dvh] w-full bg-[#F8F9F6] p-6">
                    <div className="mx-auto w-full max-w-lg rounded-[12px] border border-red-200 bg-white p-6 font-sans text-sm text-[#262A22] shadow-sm">
                        <h1 className="font-montserrat text-lg font-bold">Could not load meal selection</h1>
                        <p className="mt-2 text-[#555555]">
                            Refresh the page. If this keeps happening, open DevTools → Console and share the error with
                            support.
                        </p>
                        <pre className="mt-4 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-[12px] bg-red-50 p-3 text-xs text-red-800">
                            {message}
                        </pre>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

if (rootEl) {
    rootEl.classList.add('h-full', 'min-h-0', 'w-full');

    createRoot(rootEl).render(
        <StrictMode>
            <ConsultationErrorBoundary>
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
                    initialPlanTiers={Array.isArray(config.planTiers) ? config.planTiers : undefined}
                    initialEditDraft={
                        config.editDraft && typeof config.editDraft === 'object' ? config.editDraft : null
                    }
                />
            </ConsultationErrorBoundary>
        </StrictMode>,
    );
}
