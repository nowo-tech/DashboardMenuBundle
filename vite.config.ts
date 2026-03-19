import { defineConfig } from 'vite';

const entry = process.env.VITE_ENTRY as 'dashboard' | 'stimulus-live' | undefined;

const configs = {
  dashboard: {
    define: {
      __DASHBOARD_MENU_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
    },
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/dashboard.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/dashboard.js',
          assetFileNames: 'js/dashboard.[ext]',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'stimulus-live': {
    define: {
      __STIMULUS_LIVE_BUILD_TIME__: JSON.stringify(new Date().toISOString()),
    },
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/stimulus-live.ts',
        output: {
          format: 'es' as const,
          entryFileNames: 'js/stimulus-live.js',
          assetFileNames: 'js/stimulus-live.[ext]',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry = entry && configs[entry] ? entry : 'dashboard';

/**
 * Vite build for the Dashboard Menu Bundle.
 * Single config file; which entry to build is controlled by VITE_ENTRY:
 * - VITE_ENTRY=dashboard → IIFE → src/Resources/public/js/dashboard.js
 * - VITE_ENTRY=stimulus-live → ESM → src/Resources/public/js/stimulus-live.js
 * Default (no VITE_ENTRY): dashboard. Run both with: pnpm run build
 */
export default defineConfig(configs[effectiveEntry]);
