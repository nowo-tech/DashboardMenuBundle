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
