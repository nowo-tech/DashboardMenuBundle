/**
 * Nested menu collapse without Bootstrap JS (2.6.0).
 *
 * The menu template emits Bootstrap 5 collapse markup (`data-bs-toggle="collapse"`, `data-bs-target`,
 * `.collapse` / `.collapse.show`). This module makes that markup work on hosts that do not load
 * Bootstrap's JavaScript:
 *
 * - acts on roots marked `data-dashboard-menu-collapse="<menu code>"` (emitted by `menu.html.twig` when
 *   `nowo_dashboard_menu.menu_collapse.enabled` is true, or set by the host on any ancestor);
 * - one delegated click listener per root (CSP-safe: no inline handlers, no eval);
 * - keeps `aria-expanded` / `aria-controls` on the toggles and `inert` / `aria-hidden` on the panels in sync;
 * - remembers the open / closed state per menu code in `localStorage` (key configurable; empty = off);
 * - always opens the ancestors of the current item (`<li>` with the branch class, default `active-branch`);
 * - mode `auto` (default): when Bootstrap's Collapse is present (`window.bootstrap.Collapse` or the jQuery
 *   plugin) the click handler stands down and Bootstrap toggles, so a panel never flips twice; the script then
 *   only restores / remembers the state and syncs ARIA from Bootstrap's `show|shown|hidden.bs.collapse` events.
 *   Mode `always` takes the toggles over (drops their `data-bs-toggle` so Bootstrap's data API ignores them).
 *
 * Height animation is left to CSS (e.g. `grid-template-rows: 0fr → 1fr` on `.collapse`); the root gets
 * `is-nav-collapse-ready` two frames after the initial state is applied so that state is not animated.
 */

export const ROOT_ATTR = 'data-dashboard-menu-collapse';
export const MODE_ATTR = 'data-dashboard-menu-collapse-mode';
export const STORAGE_KEY_ATTR = 'data-dashboard-menu-collapse-storage-key';
export const BRANCH_CLASS_ATTR = 'data-dashboard-menu-collapse-branch-class';
export const TOGGLE_ATTR = 'data-dashboard-menu-collapse-toggle';
export const DEFAULT_STORAGE_KEY = 'nowo.dashboardMenu.navCollapse';
export const DEFAULT_MENU_CODE = 'menu';
export const DEFAULT_BRANCH_CLASS = 'active-branch';
export const READY_CLASS = 'is-nav-collapse-ready';
export const TOGGLE_EVENT = 'dashboard-menu:collapse-toggle';

const TOGGLE_SELECTOR = `[data-bs-toggle="collapse"], [${TOGGLE_ATTR}]`;
const BOOTSTRAP_EVENTS = ['show.bs.collapse', 'shown.bs.collapse', 'hidden.bs.collapse'] as const;

export type CollapseMode = 'auto' | 'always';

export interface MenuCollapseOptions {
  /** Menu code: the namespace of this menu's entry in the store. */
  menuCode: string;
  /** localStorage key; empty string disables persistence. */
  storageKey: string;
  mode: CollapseMode;
  /** `<li>` class marking an ancestor of the current item (forced open). */
  branchClass: string;
}

type CollapseStateMap = Record<string, boolean>;
type CollapseStore = Record<string, CollapseStateMap>;

interface BootstrapLikeWindow {
  bootstrap?: { Collapse?: unknown };
  jQuery?: { fn?: { collapse?: unknown } };
}

/**
 * True when Bootstrap's collapse JavaScript is loaded (global bundle or jQuery plugin).
 */
export function hasBootstrapCollapse(win: Window): boolean {
  const w = win as unknown as BootstrapLikeWindow;
  return Boolean(w.bootstrap?.Collapse) || typeof w.jQuery?.fn?.collapse === 'function';
}

/**
 * Options from the root's data attributes, with defaults.
 */
export function readOptions(root: Element): MenuCollapseOptions {
  const storageKey = root.getAttribute(STORAGE_KEY_ATTR);
  return {
    menuCode: root.getAttribute(ROOT_ATTR) || DEFAULT_MENU_CODE,
    storageKey: storageKey === null ? DEFAULT_STORAGE_KEY : storageKey,
    mode: root.getAttribute(MODE_ATTR) === 'always' ? 'always' : 'auto',
    branchClass: root.getAttribute(BRANCH_CLASS_ATTR) || DEFAULT_BRANCH_CLASS,
  };
}

/**
 * Panel id a toggle controls (`data-bs-target="#id"`, `href="#id"` or `aria-controls="id"`).
 */
export function targetId(toggle: Element): string {
  const raw = toggle.getAttribute('data-bs-target') || toggle.getAttribute('href') || '';
  if (raw.startsWith('#') && raw.length > 1) {
    return raw.slice(1);
  }
  return toggle.getAttribute('aria-controls') || '';
}

function readStore(win: Window, key: string): CollapseStore {
  if (key === '') {
    return {};
  }
  try {
    const raw = win.localStorage.getItem(key);
    const parsed: unknown = raw ? JSON.parse(raw) : null;
    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as CollapseStore) : {};
  } catch {
    return {};
  }
}

function writeStore(win: Window, key: string, store: CollapseStore): void {
  try {
    win.localStorage.setItem(key, JSON.stringify(store));
  } catch {
    // Quota / private mode: state is simply not remembered.
  }
}

const instances = new WeakMap<Element, MenuCollapse>();

export class MenuCollapse {
  readonly options: MenuCollapseOptions;

  private readonly onClick = (event: Event): void => this.handleClick(event);

  private readonly onBootstrapEvent = (event: Event): void => this.syncFromBootstrap(event);

  constructor(
    readonly root: HTMLElement,
    options: Partial<MenuCollapseOptions> = {},
    private readonly win: Window = window,
  ) {
    this.options = { ...readOptions(root), ...options };
  }

  /** Whether this instance handles clicks right now (false in `auto` mode once Bootstrap is loaded). */
  isActive(): boolean {
    return this.options.mode === 'always' || !hasBootstrapCollapse(this.win);
  }

  connect(): this {
    this.root.classList.remove(READY_CLASS);

    if (this.options.mode === 'always') {
      this.root.querySelectorAll('[data-bs-toggle="collapse"]').forEach((toggle) => {
        if (this.owns(toggle)) {
          toggle.removeAttribute('data-bs-toggle');
          toggle.setAttribute(TOGGLE_ATTR, '');
        }
      });
    }

    const saved = this.readMenuState();
    this.root.querySelectorAll<HTMLElement>('.collapse').forEach((panel) => {
      if (!this.owns(panel)) {
        return;
      }
      const item = panel.closest('li');
      const branchCurrent = item !== null && this.root.contains(item) && item.classList.contains(this.options.branchClass);
      const savedOpen = saved[panel.id];
      const open = branchCurrent || (typeof savedOpen === 'boolean' ? savedOpen : panel.classList.contains('show'));
      this.applyOpen(panel, open);
    });

    this.root.addEventListener('click', this.onClick);
    BOOTSTRAP_EVENTS.forEach((type) => this.root.addEventListener(type, this.onBootstrapEvent));
    // Two frames: let the browser paint the initial state before enabling CSS transitions.
    this.win.requestAnimationFrame(() => {
      this.win.requestAnimationFrame(() => this.root.classList.add(READY_CLASS));
    });

    return this;
  }

  disconnect(): void {
    this.root.removeEventListener('click', this.onClick);
    BOOTSTRAP_EVENTS.forEach((type) => this.root.removeEventListener(type, this.onBootstrapEvent));
  }

  /** Toggles (or forces, with `open`) the panel with the given id; returns the new state, null when not found. */
  toggle(panelId: string, open?: boolean): boolean | null {
    const panel = panelId === '' ? null : this.root.ownerDocument.getElementById(panelId);
    if (panel === null || !this.root.contains(panel)) {
      return null;
    }
    const next = open ?? !panel.classList.contains('show');
    this.applyOpen(panel, next);
    this.writePanelState(panel.id, next);
    panel.dispatchEvent(
      new CustomEvent(TOGGLE_EVENT, { bubbles: true, detail: { menuCode: this.options.menuCode, id: panel.id, open: next } }),
    );
    return next;
  }

  private handleClick(event: Event): void {
    const target = event.target;
    const toggle = target instanceof Element ? target.closest(TOGGLE_SELECTOR) : null;
    if (toggle === null || !this.owns(toggle) || !this.isActive()) {
      return;
    }
    const id = targetId(toggle);
    if (id === '') {
      return;
    }
    event.preventDefault();
    this.toggle(id);
  }

  /** Bootstrap toggled a panel (auto mode with Bootstrap JS): mirror ARIA / inert and remember the state. */
  private syncFromBootstrap(event: Event): void {
    const panel = event.target;
    if (!(panel instanceof HTMLElement) || !panel.classList.contains('collapse') || !this.owns(panel)) {
      return;
    }
    if (event.type === 'show.bs.collapse') {
      // Opening: un-inert now so the content is reachable while Bootstrap animates; classes stay Bootstrap's.
      panel.removeAttribute('inert');
      panel.setAttribute('aria-hidden', 'false');
      return;
    }
    const open = event.type === 'shown.bs.collapse';
    this.applyOpen(panel, open);
    this.writePanelState(panel.id, open);
  }

  /** The element belongs to this root and not to a nested collapse root. */
  private owns(el: Element): boolean {
    return this.root.contains(el) && el.closest(`[${ROOT_ATTR}]`) === this.root;
  }

  private applyOpen(panel: HTMLElement, open: boolean): void {
    panel.classList.toggle('show', open);
    panel.toggleAttribute('inert', !open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');

    const item = panel.closest('li');
    if (item !== null && this.root.contains(item)) {
      item.classList.toggle('is-expanded', open);
      item.classList.toggle('is-collapsed', !open);
    }

    this.root.querySelectorAll(TOGGLE_SELECTOR).forEach((toggle) => {
      if (targetId(toggle) !== panel.id) {
        return;
      }
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!toggle.hasAttribute('aria-controls')) {
        toggle.setAttribute('aria-controls', panel.id);
      }
    });
  }

  private readMenuState(): CollapseStateMap {
    const menu = readStore(this.win, this.options.storageKey)[this.options.menuCode];
    return menu !== null && typeof menu === 'object' && !Array.isArray(menu) ? menu : {};
  }

  private writePanelState(panelId: string, open: boolean): void {
    const key = this.options.storageKey;
    if (key === '' || panelId === '') {
      return;
    }
    const store = readStore(this.win, key);
    store[this.options.menuCode] = { ...this.readMenuState(), [panelId]: open };
    writeStore(this.win, key, store);
  }
}

/**
 * Connects every `[data-dashboard-menu-collapse]` root under `scope` once (idempotent).
 */
export function initMenuCollapse(scope: ParentNode = document, win: Window = window): MenuCollapse[] {
  const created: MenuCollapse[] = [];
  const roots: HTMLElement[] = [];
  if (scope instanceof HTMLElement && scope.hasAttribute(ROOT_ATTR)) {
    roots.push(scope);
  }
  scope.querySelectorAll<HTMLElement>(`[${ROOT_ATTR}]`).forEach((root) => roots.push(root));
  roots.forEach((root) => {
    if (instances.has(root)) {
      return;
    }
    const instance = new MenuCollapse(root, {}, win).connect();
    instances.set(root, instance);
    created.push(instance);
  });
  return created;
}

/** The instance connected on `root`, if any. */
export function getMenuCollapse(root: Element): MenuCollapse | undefined {
  return instances.get(root);
}

declare global {
  interface Window {
    NowoDashboardMenuCollapse?: { init: typeof initMenuCollapse; get: typeof getMenuCollapse };
  }
}

/**
 * Boot: connect on DOM ready and after Turbo visits / frame loads; expose `window.NowoDashboardMenuCollapse`.
 */
export function autoInit(doc: Document = document, win: Window = window): void {
  win.NowoDashboardMenuCollapse = { init: initMenuCollapse, get: getMenuCollapse };
  const run = (): void => {
    initMenuCollapse(doc, win);
  };
  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
  doc.addEventListener('turbo:load', run);
  doc.addEventListener('turbo:frame-load', run);
}
