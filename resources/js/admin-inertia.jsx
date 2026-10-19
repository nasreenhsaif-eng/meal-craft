import '../css/app.css';
import axios from 'axios';
import { createInertiaApp } from '@inertiajs/react';
import { Component } from 'react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { configureLaravelAxios, configureLaravelInertia } from './lib/csrfToken.js';
import { router } from '@inertiajs/react';
import customerOnboardingLayout from './Layouts/customerOnboardingLayout.jsx';

configureLaravelAxios(axios);
configureLaravelInertia(router);

class InertiaErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    render() {
        if (this.state.error) {
            return (
                <div className="p-6 font-sans text-sm text-zinc-800">
                    <h1 className="text-lg font-semibold text-zinc-900">This page failed to render</h1>
                    <p className="mt-2 text-zinc-600">
                        Hard refresh (<kbd>Cmd</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd>), then try again. If it keeps
                        happening, open DevTools → Console and share the red error.
                    </p>
                    <pre className="mt-4 max-h-48 overflow-auto rounded-md bg-zinc-100 p-3 text-xs">
                        {this.state.error.message}
                    </pre>
                    <p className="mt-4">
                        <a className="font-semibold text-[#5A6B44] underline" href="/checkout/payment">
                            Open payment checkout
                        </a>
                    </p>
                </div>
            );
        }

        return this.props.children;
    }
}

createInertiaApp({
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        );

        if (name.startsWith('Onboarding/') && page.default && !page.default.layout) {
            page.default.layout = customerOnboardingLayout;
        }

        return page;
    },
    setup({ el, App, props }) {
        const rootEl = document.getElementById('app') ?? el;
        if (!rootEl) {
            // eslint-disable-next-line no-console
            console.error('Inertia root element not found (expected #app from @inertia).');
            document.body.insertAdjacentHTML(
                'beforeend',
                '<p class="p-6 font-sans text-sm text-zinc-800">Missing Inertia root <code>#app</code>. Check <code>resources/views/app.blade.php</code> includes <code>@inertia</code>.</p>',
            );
            return;
        }

        const app = (
            <InertiaErrorBoundary>
                <App {...props} />
            </InertiaErrorBoundary>
        );

        // Persist the React root across Vite HMR. Older builds stored
        // `{ current: Root }` — migrate that so .render() always exists.
        const existing = window.__MC_INERTIA_ROOT__;
        if (existing && typeof existing.render === 'function') {
            existing.render(app);
            return;
        }

        if (
            existing &&
            typeof existing === 'object' &&
            existing.current &&
            typeof existing.current.render === 'function'
        ) {
            window.__MC_INERTIA_ROOT__ = existing.current;
            window.__MC_INERTIA_ROOT__.render(app);
            return;
        }

        try {
            window.__MC_INERTIA_ROOT__ = createRoot(rootEl);
            window.__MC_INERTIA_ROOT__.render(app);
        } catch (error) {
            // eslint-disable-next-line no-console
            console.warn(error);
            if (!window.__MC_INERTIA_RELOAD_ATTEMPTED__) {
                window.__MC_INERTIA_RELOAD_ATTEMPTED__ = true;
                window.location.reload();
            }
        }
    },
    progress: {
        color: '#6E8C47',
    },
});
