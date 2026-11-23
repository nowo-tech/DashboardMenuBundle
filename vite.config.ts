import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { defineConfig } from 'vite';

/**
 * Deterministic source hash: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change; it is the change detector of buildTimeLabel() below.
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

/**
 * Readable, reproducible build time for the "script loaded" console line ("2026-11-23 14:05 UTC").
 * The committed `build-info.json` ({ sourceHash, builtAt }) keeps it stable: when the source hash equals the stored
 * one, the stored `builtAt` is reused (a rebuild of unchanged sources never rewrites the committed assets); when it
 * differs, the new hash is stored with the current UTC minute. Commit `build-info.json` with the rebuilt assets: a CI
 * dist-freshness rebuild of a clean checkout then reproduces the same bytes, and fails visibly if it was forgotten.
 */
function buildTimeLabel(dir: string): string {
  const sourceHash = sourceBuildId(dir);
  const file = new URL('build-info.json', import.meta.url);
  let info: { sourceHash?: string; builtAt?: string } = {};
  try {
    info = JSON.parse(readFileSync(file, 'utf8')) as typeof info;
  } catch {
    // Missing or unreadable: written below.
  }
  if (info.sourceHash !== sourceHash || typeof info.builtAt !== 'string') {
    info = { sourceHash, builtAt: `${new Date().toISOString().slice(0, 16)}Z` };
    writeFileSync(file, `${JSON.stringify(info, null, 2)}\n`);
    console.warn(`build-info.json updated (${info.builtAt}): commit it together with the rebuilt assets.`);
  }

  return (info.builtAt as string).replace('T', ' ').replace('Z', ' UTC');
}

const entry = process.env.VITE_ENTRY as 'dashboard' | 'stimulus-live' | 'menu-collapse' | 'autocomplete' | undefined;

const configs = {
  dashboard: {
    define: {
      __DASHBOARD_MENU_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets/src')),
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
      __STIMULUS_LIVE_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets/src')),
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
  autocomplete: {
    define: {
      __DASHBOARD_MENU_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets/src')),
    },
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/autocomplete.ts',
        // The module is imported at runtime and its default export is the controller: keep the entry's exports.
        preserveEntrySignatures: 'strict' as const,
        output: {
          format: 'es' as const,
          entryFileNames: 'js/autocomplete.js',
          assetFileNames: 'js/autocomplete.[ext]',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'menu-collapse': {
    define: {
      __DASHBOARD_MENU_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets/src')),
    },
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
 * - VITE_ENTRY=autocomplete → ESM → src/Resources/public/js/autocomplete.js (UX Autocomplete + Tom Select, lazy-loaded by dashboard.js, 2.25.0)
 * Default (no VITE_ENTRY): dashboard. Run all with: pnpm run build
 */
export default defineConfig(configs[effectiveEntry]);
