import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for Dashboard Menu Bundle TypeScript unit tests.
 * Uses happy-dom as the DOM environment and runs all `*.test.ts` files under Resources/assets.
 */
export default defineConfig({
  test: {
    environment: 'happy-dom',
    globals: true,
    include: ['src/Resources/assets/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      // Unit-testable modules at 100%; dashboard.ts / stimulus-live.ts / *-boot.ts entries are integration-heavy.
      include: [
        'src/Resources/assets/src/logger.ts',
        'src/Resources/assets/src/config.ts',
        'src/Resources/assets/src/menu-collapse.ts',
      ],
      exclude: ['**/*.test.ts', '**/node_modules/**'],
      thresholds: {
        lines: 100,
        functions: 100,
        branches: 100,
        statements: 100,
      },
    },
  },
});
