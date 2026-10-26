import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { defineConfig } from 'vite';

/**
 * Deterministic build id: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change, so rebuilding unchanged sources (e.g. in `make release-check`) leaves the committed bundle
 * byte-identical (the former wall-clock stamp rewrote it on every build).
 */
function sourceBuildId(dir: string): string {
  const root = new URL(`${dir}/`, import.meta.url);
  const hash = createHash('sha256');
  const files = (readdirSync(root, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(new URL(file, root)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(new URL(file, root)));
  }
  hash.update(readFileSync(new URL('pnpm-lock.yaml', import.meta.url)));

  return hash.digest('hex').slice(0, 12);
}

const entry = process.env.VITE_ENTRY as 'dashboard' | 'stimulus-live' | 'menu-collapse' | undefined;

const configs = {
  dashboard: {
    define: {
      __DASHBOARD_MENU_BUILD_TIME__: JSON.stringify(sourceBuildId('src/Resources/assets/src')),
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
      __STIMULUS_LIVE_BUILD_TIME__: JSON.stringify(sourceBuildId('src/Resources/assets/src')),
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
  'menu-collapse': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/menu-collapse-boot.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/menu-collapse.js',
          assetFileNames: 'js/menu-collapse.[ext]',
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
 * - VITE_ENTRY=menu-collapse → IIFE → src/Resources/public/js/menu-collapse.js (Bootstrap-free nested collapse, 2.6.0)
 * Default (no VITE_ENTRY): dashboard. Run all with: pnpm run build
 */
export default defineConfig(configs[effectiveEntry]);
