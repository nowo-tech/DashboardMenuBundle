# Demo: Symfony UX (Stimulus) and Vite (TypeScript)

The bundle demos use **Symfony UX** with **Stimulus** and **Vite** in **TypeScript** (pentatrion/vite-bundle + vite-plugin-symfony) so the icon selector and other Stimulus controllers work in the dashboard, including the AJAX-loaded item form inside the modal.

## Installed packages

- **symfony/stimulus-bundle**: Stimulus integration (controllers via `data-controller`, Twig `stimulus_controller()`, etc.).
- **pentatrion/vite-bundle**: Vite integration in Twig (`vite_entry_script_tags('app')`, `vite_entry_link_tags('app')`).
- **vite-plugin-symfony** (npm): generates `entrypoints.json` and helpers to start Stimulus with Vite.
- **typescript** and **@types/node** (npm): TypeScript support in assets.

## Asset structure (TypeScript)

At the root of each demo (e.g. `demo/symfony8/`) there are or are created:

**Note:** In `demo/symfony8` the Vite entrypoint is configured as `./assets/app.ts`. If `assets/app.js` and `assets/stimulus_bootstrap.js` still exist, create the `.ts` files listed below (and optionally delete the `.js`). In `demo/symfony7` create from scratch `assets/app.ts`, `assets/bootstrap.ts` and `assets/controllers/hello_controller.ts`.

- **tsconfig.json**: TypeScript build options (includes `assets/**/*.ts`, `vite.config.ts`).
- **env.d.ts**: Vite type references and `vite-plugin-symfony/stimulus/env`.
- **vite.config.ts**: `app` entrypoint -> `./assets/app.ts`, Stimulus plugin.
- **assets/app.ts**: imports `bootstrap.ts` and styles.
- **assets/bootstrap.ts**: starts Stimulus and registers controllers `*_controller.ts`.
- **assets/controllers/*_controller.ts**: Stimulus controllers written in TypeScript.

### 1. `vite.config.ts`

```ts
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

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
});
```

### 2. `assets/app.ts`

```ts
import './bootstrap.ts';
import './app.css';  // optional, if it exists

console.log('Happy coding!');
```

### 3. `assets/bootstrap.ts`

```ts
import { startStimulusApp, registerControllers } from 'vite-plugin-symfony/stimulus/helpers';

const app = startStimulusApp();
registerControllers(app, import.meta.glob('./controllers/*_controller.ts', {
  query: '?stimulus',
  eager: true,
}));

if (import.meta.hot) {
  (window as unknown as { $$stimulusApp$$: typeof app }).$$stimulusApp$$ = app;
}
```

### 4. `assets/controllers/hello_controller.ts` (example)

```ts
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect(): void {
    this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.ts';
  }
}
```

### 5. `package.json` (scripts and dependencies)

It includes `packageManager: "pnpm@9.15.0"`, and in `devDependencies`: `@hotwired/stimulus`, `@types/node`, `typescript`, `vite`, `vite-plugin-symfony`. See `demo/symfony8/package.json` or `demo/symfony7/package.json`.

Run inside the demo folder: `pnpm install` (if you use Node 16.13+, run `corepack enable` and then `pnpm install`). Alternatively `npm install`.

## Symfony configuration

- **config/packages/pentatrion_vite.yaml**: see the example in `demo/symfony8/config/packages/pentatrion_vite.yaml`.
- **config/bundles.php**: register `PentatrionViteBundle` and `StimulusBundle` (Flex can do this when running `composer require`).

## Dashboard layout

To make the dashboard pages load the Vite app (Stimulus), the demo must **override** the bundle layout and fill in the `dashboard_head` and `dashboard_scripts` blocks:

- **demo/symfony8**: `templates/bundles/NowoDashboardMenuBundle/dashboard/layout.html.twig`
  - In `{% block dashboard_head %}`: `{{ vite_entry_link_tags('app') }}`
  - In `{% block dashboard_scripts %}`: `{{ vite_entry_script_tags('app') }}`

So when opening the dashboard, the `app` entrypoint is loaded and starts Stimulus. When the item form modal injects HTML with `data-controller="icon-selector"` (or similar), the bundle script calls `connectStimulusControllersInContainer(container)` so Stimulus connects those elements. If the icon-selector is loaded as an additional script (`icon_selector_script_url`), a cache-buster is used when injecting it into the modal so it runs again.

## Development

1. In the demo folder: `npm run dev` (or `pnpm dev`) to start the Vite dev server (e.g. on port 5173).
2. Start the Symfony app (e.g. `symfony serve` or Docker).
3. In production, run `npm run build` and ensure the compiled assets are in `public/build/` (or the configured `build_directory`).

## Icon selector

If **nowo-tech/icon-selector-bundle** exposes a Stimulus controller, register it in `assets/controllers.json` (or in the `import.meta.glob` of `bootstrap.ts`) so it is available when the form is injected into the modal. If the bundle only publishes a classic script (`icon-selector.js`), the URL configured in `nowo_dashboard_menu.dashboard.icon_selector_script_url` is loaded in the layout and re-injected with a cache-buster when opening the modal so the fragment elements are initialized.
