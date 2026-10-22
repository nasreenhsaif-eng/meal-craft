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

const ROOT_KEY = '__mcInertiaRoot';

/**
 * Mount (or remount) Inertia into #app without calling createRoot twice.
 * Double createRoot blanks Chrome ("container already passed to createRoot").
 *
 * @param {HTMLElement} el
 * @param {import('react').ReactNode} app
 */
function mountInertiaRoot(el, app) {
    /** @type {import('react-dom/client').Root | undefined} */
    let root = el[ROOT_KEY];

    if (root && typeof root.render === 'function') {
        root.render(app);
        return;
    }

    try {
        root = createRoot(el);
    } catch {
        el.replaceChildren();
        root = createRoot(el);
    }

    el[ROOT_KEY] = root;
    root.render(app);
}

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
                        Hard refresh (<kbd>Cmd</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd>), then try again.
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
        mountInertiaRoot(
            el,
            <InertiaErrorBoundary>
                <App {...props} />
            </InertiaErrorBoundary>,
        );
    },
    progress: {
        color: '#6E8C47',
    },
});
