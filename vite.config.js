/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from "@tailwindcss/vite";
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import { playwright } from '@vitest/browser-playwright';
const dirname = typeof __dirname !== 'undefined' ? __dirname : path.dirname(fileURLToPath(import.meta.url));

// More info at: https://storybook.js.org/docs/next/writing-tests/integrations/vitest-addon
export default defineConfig({
  plugins: [laravel({
    input: [
      'resources/css/app.css',
      'resources/js/app.js',
      'resources/js/admin-inertia.jsx',
      'resources/js/admin-intro.jsx',
      'resources/js/ingredient-analyzer.jsx',
      'resources/js/auth-login.jsx',
      'resources/js/consultation-crafted-for-you.jsx',
    ],
    refresh: true
  }), react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.join(dirname, 'src'),
    },
  },
  server: {
    cors: true,
    watch: {
      ignored: ['**/storage/framework/views/**']
    }
  },
  test: {
    projects: [{
      extends: true,
      plugins: [
      // The plugin will run tests for the stories defined in your Storybook config
      // See options at: https://storybook.js.org/docs/next/writing-tests/integrations/vitest-addon#storybooktest
      storybookTest({
        configDir: path.join(dirname, '.storybook')
      })],
      test: {
        name: 'storybook',
        browser: {
          enabled: true,
          headless: true,
          provider: playwright({}),
          instances: [{
            browser: 'chromium'
          }]
        }
      }
    }]
  }
});