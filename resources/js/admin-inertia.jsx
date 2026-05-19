import '../css/app.css';
import axios from 'axios';
import { createInertiaApp } from '@inertiajs/react';
import { Component } from 'react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { configureLaravelAxios } from './lib/csrfToken.js';

configureLaravelAxios(axios);

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
                    <h1 className="text-lg font-semibold text-zinc-900">Admin app failed to render</h1>
                    <p className="mt-2 text-zinc-600">Open DevTools → Console for the full stack trace.</p>
                    <pre className="mt-4 max-h-48 overflow-auto rounded-md bg-zinc-100 p-3 text-xs">{this.state.error.message}</pre>
                </div>
            );
        }

        return this.props.children;
    }
}

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        const rootEl = el ?? document.getElementById('app');
        if (!rootEl) {
            // eslint-disable-next-line no-console
            console.error('Inertia root element not found (expected #app from @inertia).');
            document.body.insertAdjacentHTML(
                'beforeend',
                '<p class="p-6 font-sans text-sm text-zinc-800">Missing Inertia root <code>#app</code>. Check <code>resources/views/app.blade.php</code> includes <code>@inertia</code>.</p>',
            );
            return;
        }

        try {
            createRoot(rootEl).render(
                <InertiaErrorBoundary>
                    <App {...props} />
                </InertiaErrorBoundary>,
            );
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error(error);
            rootEl.innerHTML =
                '<p class="p-6 font-sans text-sm text-zinc-800">Failed to start the admin app. Open DevTools → Console for details.</p>';
        }
    },
    progress: {
        color: '#6E8C47',
    },
});
