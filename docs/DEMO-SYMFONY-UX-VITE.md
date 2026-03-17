# Demo: Symfony UX (Stimulus) y Vite (TypeScript)

Las demos del bundle usan **Symfony UX** con **Stimulus** y **Vite** en **TypeScript** (pentatrion/vite-bundle + vite-plugin-symfony) para que el icon selector y otros controladores Stimulus funcionen en el dashboard, incluido el formulario de ítem cargado por AJAX en el modal.

## Paquetes instalados

- **symfony/stimulus-bundle**: integración de Stimulus (controladores `data-controller`, Twig `stimulus_controller()`, etc.).
- **pentatrion/vite-bundle**: integración de Vite en Twig (`vite_entry_script_tags('app')`, `vite_entry_link_tags('app')`).
- **vite-plugin-symfony** (npm): genera `entrypoints.json` y helpers para arrancar Stimulus con Vite.
- **typescript** y **@types/node** (npm): soporte TypeScript en assets.

## Estructura de assets (TypeScript)

En la raíz de cada demo (p. ej. `demo/symfony8/`) hay o se crean:

**Nota:** En `demo/symfony8` el entrypoint de Vite está configurado como `./assets/app.ts`. Si todavía existen `assets/app.js` y `assets/stimulus_bootstrap.js`, crea los archivos `.ts` indicados abajo (y opcionalmente borra los `.js`). En `demo/symfony7` crea desde cero `assets/app.ts`, `assets/bootstrap.ts` y `assets/controllers/hello_controller.ts`.

- **tsconfig.json**: opciones de compilación TypeScript (incluye `assets/**/*.ts`, `vite.config.ts`).
- **env.d.ts**: referencias a tipos de Vite y `vite-plugin-symfony/stimulus/env`.
- **vite.config.ts**: entrypoint `app` → `./assets/app.ts`, plugin Stimulus.
- **assets/app.ts**: importa `bootstrap.ts` y estilos.
- **assets/bootstrap.ts**: arranque de Stimulus y registro de controladores `*_controller.ts`.
- **assets/controllers/*_controller.ts**: controladores Stimulus en TypeScript.

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
import './app.css';  // opcional, si existe

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

### 4. `assets/controllers/hello_controller.ts` (ejemplo)

```ts
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect(): void {
    this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.ts';
  }
}
```

### 5. `package.json` (scripts y dependencias)

Incluye `packageManager: "pnpm@9.15.0"`, y en `devDependencies`: `@hotwired/stimulus`, `@types/node`, `typescript`, `vite`, `vite-plugin-symfony`. Ver `demo/symfony8/package.json` o `demo/symfony7/package.json`.

Ejecutar en la carpeta de la demo: `pnpm install` (si usas Node 16.13+, ejecuta `corepack enable` y luego `pnpm install`). Alternativamente `npm install`.

## Configuración Symfony

- **config/packages/pentatrion_vite.yaml**: ver ejemplo en `demo/symfony8/config/packages/pentatrion_vite.yaml`.
- **config/bundles.php**: registrar `PentatrionViteBundle` y `StimulusBundle` (Flex puede hacerlo al hacer `composer require`).

## Layout del dashboard

Para que las páginas del dashboard carguen la app de Vite (Stimulus), la demo debe **sobrescribir** el layout del bundle y rellenar los bloques `dashboard_head` y `dashboard_scripts`:

- **demo/symfony8**: `templates/bundles/NowoDashboardMenuBundle/dashboard/layout.html.twig`
  - En `{% block dashboard_head %}`: `{{ vite_entry_link_tags('app') }}`
  - En `{% block dashboard_scripts %}`: `{{ vite_entry_script_tags('app') }}`

Así, al abrir el dashboard se carga el entrypoint `app`, que arranca Stimulus. Cuando el modal del formulario de ítem inyecta HTML con `data-controller="icon-selector"` (o similar), el script del bundle llama a `connectStimulusControllersInContainer(container)` para que Stimulus conecte esos elementos. Si el icon-selector se carga como script adicional (`icon_selector_script_url`), se usa un cache-buster al inyectarlo en el modal para que se ejecute de nuevo.

## Desarrollo

1. En la carpeta de la demo: `npm run dev` (o `pnpm dev`) para arrancar el servidor de Vite (p. ej. en el puerto 5173).
2. Arrancar la app Symfony (p. ej. `symfony serve` o Docker).
3. En producción, ejecutar `npm run build` y asegurarse de que los assets compilados estén en `public/build/` (o el `build_directory` configurado).

## Icon selector

Si **nowo-tech/icon-selector-bundle** expone un controlador Stimulus, regístralo en `assets/controllers.json` (o en el `import.meta.glob` de `bootstrap.ts`) para que esté disponible cuando se inyecte el formulario en el modal. Si el bundle solo publica un script clásico (`icon-selector.js`), la URL configurada en `nowo_dashboard_menu.dashboard.icon_selector_script_url` se carga en el layout y se reinyecta con cache-buster al abrir el modal para que inicialice los elementos del fragmento.
