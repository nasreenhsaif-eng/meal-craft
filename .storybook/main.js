/** @type { import('@storybook/react-vite').StorybookConfig } */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname =
    typeof __dirname !== 'undefined'
        ? __dirname
        : path.dirname(fileURLToPath(import.meta.url));

const repoRoot = path.resolve(dirname, '..');

const config = {
    // Serve Laravel `public/` at dev-server root so `/images/...` works (matches Herd / `php artisan serve`).
    staticDirs: ['../public'],
    stories: [
        '../resources/js/Components/**/*.stories.@(js|jsx|mjs|ts|tsx)',
        '../resources/js/Pages/**/*.stories.@(js|jsx|mjs|ts|tsx)',
    ],
    addons: [
        '@chromatic-com/storybook',
        '@storybook/addon-vitest',
        '@storybook/addon-a11y',
        '@storybook/addon-docs',
        '@storybook/addon-onboarding',
        '@storybook/addon-designs',
    ],
    framework: '@storybook/react-vite',
    async viteFinal(config) {
        // Ensure Storybook can load source modules from the Laravel app tree.
        // Without this, Vite may treat `.storybook/` as root and block `/resources/**` imports.
        // Match `vite.config.js` so imports like `@/../public/...` resolve the same as the app.
        return {
            ...config,
            root: repoRoot,
            resolve: {
                ...config.resolve,
                alias: {
                    ...config.resolve?.alias,
                    '@': path.join(repoRoot, 'src'),
                },
            },
            server: {
                ...config.server,
                fs: {
                    ...config.server?.fs,
                    allow: [
                        ...(config.server?.fs?.allow ?? []),
                        repoRoot,
                    ],
                },
            },
        };
    },
};

export default config;
