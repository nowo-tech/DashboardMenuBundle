# Demo Symfony 8: TypeScript assets

In `demo/symfony8` the Vite entrypoint is `./assets/app.ts`. Create these files (or run `scripts/create-ts-assets.sh` from `demo/symfony8`) and remove the equivalent `.js` files.

## assets/app.ts

```ts
import './bootstrap.ts';
import './app.css';

console.log('Happy coding!');
```

## assets/bootstrap.ts

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

## assets/controllers/hello_controller.ts

```ts
import { Controller } from '@hotwired/stimulus';

/**
 * Example Stimulus controller.
 * Any element with data-controller="hello" will run this.
 */
export default class extends Controller {
  connect(): void {
    this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.ts';
  }
}
```

## assets/controllers/csrf_protection_controller.ts

```ts
const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

declare global {
  interface Window {
    msCrypto?: Crypto;
  }
}

interface TurboSubmitStartDetail {
  formSubmission: {
    formElement: HTMLFormElement;
    fetchRequest: { headers: Record<string, string> };
  };
}

document.addEventListener('submit', (event: Event) => {
  generateCsrfToken(event.target as HTMLFormElement);
}, true);

document.addEventListener('turbo:submit-start', ((event: CustomEvent<TurboSubmitStartDetail>) => {
  const h = generateCsrfHeaders(event.detail.formSubmission.formElement);
  Object.keys(h).forEach((k) => {
    event.detail.formSubmission.fetchRequest.headers[k] = h[k];
  });
}) as EventListener);

document.addEventListener('turbo:submit-end', ((event: CustomEvent<{ formSubmission: { formElement: HTMLFormElement } }>) => {
  removeCsrfToken(event.detail.formSubmission.formElement);
}) as EventListener);

export function generateCsrfToken(formElement: HTMLFormElement): void {
  const csrfField = formElement.querySelector<HTMLInputElement>(
    'input[data-controller="csrf-protection"], input[name="_csrf_token"]'
  );

  if (!csrfField) return;

  let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
  let csrfToken = csrfField.value;

  if (!csrfCookie && nameCheck.test(csrfToken)) {
    csrfField.setAttribute('data-csrf-protection-cookie-value', (csrfCookie = csrfToken));
    const crypto = window.crypto ?? window.msCrypto;
    csrfField.defaultValue = csrfToken = btoa(
      String.fromCharCode(...crypto!.getRandomValues(new Uint8Array(18)))
    );
  }
  csrfField.dispatchEvent(new Event('change', { bubbles: true }));

  if (csrfCookie && tokenCheck.test(csrfToken)) {
    const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`;
    document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie;
  }
}

export function generateCsrfHeaders(formElement: HTMLFormElement): Record<string, string> {
  const headers: Record<string, string> = {};
  const csrfField = formElement.querySelector<HTMLInputElement>(
    'input[data-controller="csrf-protection"], input[name="_csrf_token"]'
  );

  if (!csrfField) return headers;

  const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

  if (tokenCheck.test(csrfField.value) && csrfCookie && nameCheck.test(csrfCookie)) {
    headers[csrfCookie] = csrfField.value;
  }

  return headers;
}

export function removeCsrfToken(formElement: HTMLFormElement): void {
  const csrfField = formElement.querySelector<HTMLInputElement>(
    'input[data-controller="csrf-protection"], input[name="_csrf_token"]'
  );

  if (!csrfField) return;

  const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');

  if (tokenCheck.test(csrfField.value) && csrfCookie && nameCheck.test(csrfCookie)) {
    const cookie = `${csrfCookie}_${csrfField.value}=0; path=/; samesite=strict; max-age=0`;
    document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie;
  }
}

/* stimulusFetch: 'lazy' */
export default 'csrf-protection-controller';
```

## After creating the .ts files

Remove the old JS files:

```bash
rm -f demo/symfony8/assets/app.js \
      demo/symfony8/assets/stimulus_bootstrap.js \
      demo/symfony8/assets/controllers/hello_controller.js \
      demo/symfony8/assets/controllers/csrf_protection_controller.js
```

Or from `demo/symfony8`:

```bash
rm -f assets/app.js assets/stimulus_bootstrap.js \
      assets/controllers/hello_controller.js \
      assets/controllers/csrf_protection_controller.js
```
