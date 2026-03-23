import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';

const localBundleLoggerPath = resolve(__dirname, '../../src/Resources/assets/src/logger.ts');
const containerBundleLoggerPath = '/var/dashboard-menu-bundle/src/Resources/assets/src/logger.ts';
const bundleLoggerPath = existsSync(localBundleLoggerPath) ? localBundleLoggerPath : containerBundleLoggerPath;

export default defineConfig({
  plugins: [
    symfonyPlugin({
      stimulus: true,
    }),
  ],
  build: {
    rollupOptions: {
      input: {
        app: './assets/app.ts',
      },
    },
  },
  resolve: {
    alias: {
      '@nowo-dashboard-menu/logger': bundleLoggerPath,
    },
  },
  server: {
    fs: {
      allow: [bundleLoggerPath],
    },
  },
});
