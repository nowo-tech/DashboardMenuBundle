import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const stub = (file: string): string => fileURLToPath(new URL(`./src/Resources/assets/test-support/${file}`, import.meta.url));

/**
 * Vitest configuration for Dashboard Menu Bundle TypeScript unit tests.
 * Uses happy-dom as the DOM environment and runs all `*.test.ts` files under Resources/assets.
 */
export default defineConfig({
  resolve: {
    // The entries import Stimulus and the UX controllers from esm.sh at runtime: tests use local doubles.
    alias: {
      'https://esm.sh/@hotwired/stimulus@3': stub('stimulus.ts'),
      'https://esm.sh/@symfony/ux-live-component@2': stub('live-controller.ts'),
      'https://esm.sh/@symfony/ux-autocomplete@2': stub('autocomplete.ts'),
      sortablejs: stub('sortable.ts'),
    },
  },
  test: {
    environment: 'happy-dom',
    // The code under test appends <script>/<link> elements: pretend the (disabled) file loading succeeded.
    environmentOptions: {
      happyDOM: {
        settings: { handleDisabledFileLoadingAsSuccess: true },
      },
    },
    globals: true,
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      // Every module of the bundle's frontend (REQ-TEST-003). The two entries that talk to the page and the CDN
      // (dashboard.ts, stimulus-live.ts) are held to the TS/JS bar of the requirement (>= 90 % lines); the rest stays at 100 %.
      include: ['src/Resources/assets/src/**/*.ts'],
      exclude: ['**/*.test.ts', '**/node_modules/**', 'src/Resources/assets/test-support/**'],
      thresholds: {
        'src/Resources/assets/src/{config,logger,menu-collapse,menu-collapse-boot}.ts': { lines: 100, functions: 100, branches: 100, statements: 100 },
        'src/Resources/assets/src/dashboard.ts': { lines: 95, functions: 95, branches: 85, statements: 95 },
        'src/Resources/assets/src/stimulus-live.ts': { lines: 95, functions: 95, branches: 70, statements: 90 },
      },
    },
  },
});
