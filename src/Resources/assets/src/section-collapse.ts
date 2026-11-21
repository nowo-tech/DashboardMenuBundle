/**
 * Per-section collapse (2.26.0, `nowo_dashboard_menu.menu_layout.<code>.sections`).
 *
 * `menu.html.twig` renders a configured section as a `<button aria-expanded aria-controls>` header
 * (`data-dashboard-menu-section="<menu code>:<section id>"`) and a panel (`hidden` when collapsed). The server state is
 * the configured default, so the first paint never shifts; this script then:
 *
 * - applies the person's remembered choice (`localStorage`, `{ "<menu code>:<section id>": collapsed }`, key in
 *   `data-dashboard-menu-section-storage-key`, empty = no persistence); every storage access is wrapped in try / catch
 *   and falls back to the configured default;
 * - never hides the section that holds the current item (`data-dashboard-menu-section-current`) on load;
 * - toggles on click through one delegated listener (CSP-safe, no inline handlers); Enter / Space come from the
 *   native `<button>`.
 */

export const SECTION_ATTR = 'data-dashboard-menu-section';
export const SECTION_DEFAULT_ATTR = 'data-dashboard-menu-section-default';
export const SECTION_STORAGE_KEY_ATTR = 'data-dashboard-menu-section-storage-key';
export const SECTION_CURRENT_ATTR = 'data-dashboard-menu-section-current';
export const SECTION_TOGGLE_EVENT = 'dashboard-menu:section-toggle';
export const COLLAPSED_CLASS = 'is-collapsed';

const SECTION_SELECTOR = `[${SECTION_ATTR}]`;

type SectionStore = Record<string, boolean>;

function readStore(win: Window, storageKey: string): SectionStore {
  if (storageKey === '') {
    return {};
  }
  try {
    const raw = win.localStorage.getItem(storageKey);
    const parsed: unknown = raw ? JSON.parse(raw) : null;
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as SectionStore) : {};
  } catch {
    return {};
  }
}

function writeChoice(win: Window, storageKey: string, key: string, collapsed: boolean): void {
  if (storageKey === '') {
    return;
  }
  try {
    win.localStorage.setItem(storageKey, JSON.stringify({ ...readStore(win, storageKey), [key]: collapsed }));
  } catch {
    // Quota / private mode: the choice is simply not remembered.
  }
}

/** The section key; always present on elements matched by SECTION_SELECTOR. */
function keyOf(button: Element): string {
  return button.getAttribute(SECTION_ATTR) as string;
}

function storageKeyOf(button: Element): string {
  return button.getAttribute(SECTION_STORAGE_KEY_ATTR) ?? '';
}

/** Collapsed or expanded, in sync on the button (aria-expanded, class) and its panel (`hidden`). */
export function setSectionCollapsed(button: HTMLElement, collapsed: boolean): void {
  button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  button.classList.toggle(COLLAPSED_CLASS, collapsed);
  const panel = button.ownerDocument.getElementById(button.getAttribute('aria-controls') ?? '');
  if (panel !== null) {
    panel.hidden = collapsed;
  }
}

/** Applies the remembered choice to every configured section of the document. */
export function initSectionCollapse(doc: Document = document, win: Window = window): void {
  const stores = new Map<string, SectionStore>();
  doc.querySelectorAll<HTMLElement>(SECTION_SELECTOR).forEach((button) => {
    const storageKey = storageKeyOf(button);
    let store = stores.get(storageKey);
    if (store === undefined) {
      store = readStore(win, storageKey);
      stores.set(storageKey, store);
    }
    const remembered = store[keyOf(button)];
    const collapsed =
      !button.hasAttribute(SECTION_CURRENT_ATTR) &&
      (typeof remembered === 'boolean' ? remembered : button.getAttribute(SECTION_DEFAULT_ATTR) === 'collapsed');
    setSectionCollapsed(button, collapsed);
  });
}

/** Toggles a section, remembers the choice and announces it; returns the new collapsed state. */
export function toggleSection(button: HTMLElement, win: Window = window): boolean {
  const collapsed = button.getAttribute('aria-expanded') !== 'false';
  setSectionCollapsed(button, collapsed);
  const key = keyOf(button);
  writeChoice(win, storageKeyOf(button), key, collapsed);
  button.dispatchEvent(new CustomEvent(SECTION_TOGGLE_EVENT, { bubbles: true, detail: { key, collapsed } }));
  return collapsed;
}

const bound = new WeakSet<Document>();

/** Delegated click handler (once per document) plus the initial / Turbo-visit state application. */
export function autoInitSections(doc: Document = document, win: Window = window): void {
  if (bound.has(doc)) {
    return;
  }
  bound.add(doc);
  doc.addEventListener('click', (event: Event) => {
    const target = event.target;
    const button = target instanceof Element ? target.closest<HTMLElement>(SECTION_SELECTOR) : null;
    if (button !== null) {
      event.preventDefault();
      toggleSection(button, win);
    }
  });
  const run = (): void => initSectionCollapse(doc, win);
  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
  doc.addEventListener('turbo:load', run);
  doc.addEventListener('turbo:frame-load', run);
}
