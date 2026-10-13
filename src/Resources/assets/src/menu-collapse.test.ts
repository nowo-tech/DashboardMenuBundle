/**
 * Unit tests for the Bootstrap-free nested menu collapse (2.6.0).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  autoInit,
  DEFAULT_BRANCH_CLASS,
  DEFAULT_MENU_CODE,
  DEFAULT_STORAGE_KEY,
  getMenuCollapse,
  hasBootstrapCollapse,
  initMenuCollapse,
  MenuCollapse,
  READY_CLASS,
  readOptions,
  targetId,
  TOGGLE_ATTR,
  TOGGLE_EVENT,
} from './menu-collapse';

type LooseWindow = Window & { bootstrap?: unknown; jQuery?: unknown };

const w = window as LooseWindow;

/** Markup of menu.html.twig with nested_collapsible: two parents (one in the current branch), one nested level. */
function menuMarkup(attrs = 'data-dashboard-menu-collapse="sidebar"'): string {
  return `
    <ul class="dashboard-menu sidebar dashboard-menu-tree" ${attrs}>
      <li class="active-branch">
        <a href="/a">A</a>
        <button type="button" class="menu-nested-toggle" data-bs-toggle="collapse" data-bs-target="#sidebar-item-1" aria-expanded="true" aria-controls="sidebar-item-1">t</button>
        <div id="sidebar-item-1" class="collapse show"><ul><li><a href="/a1">A1</a></li></ul></div>
      </li>
      <li>
        <a href="/b">B</a>
        <button type="button" class="menu-nested-toggle" data-bs-toggle="collapse" data-bs-target="#sidebar-item-2" aria-expanded="true" aria-controls="sidebar-item-2"><span class="icon">v</span></button>
        <div id="sidebar-item-2" class="collapse show"><ul><li><a href="/b1">B1</a></li></ul></div>
      </li>
    </ul>`;
}

function mount(html: string = menuMarkup()): HTMLElement {
  document.body.innerHTML = html;
  return document.querySelector<HTMLElement>('[data-dashboard-menu-collapse]') as HTMLElement;
}

function panel(id: string): HTMLElement {
  return document.getElementById(id) as HTMLElement;
}

function toggleFor(id: string): HTMLElement {
  return document.querySelector<HTMLElement>(`[aria-controls="${id}"]`) as HTMLElement;
}

/**
 * Minimal stand-in for Bootstrap 5's collapse data API: a delegated document listener on
 * `[data-bs-toggle="collapse"]` that flips `.show` and fires show / shown / hidden events.
 */
function installFakeBootstrap(): { clicks: () => number; uninstall: () => void } {
  let count = 0;
  const listener = (event: Event): void => {
    const toggle = (event.target as Element).closest('[data-bs-toggle="collapse"]');
    if (!toggle) return;
    count++;
    const target = document.querySelector<HTMLElement>(toggle.getAttribute('data-bs-target') as string) as HTMLElement;
    const opening = !target.classList.contains('show');
    if (opening) target.dispatchEvent(new Event('show.bs.collapse', { bubbles: true }));
    target.classList.toggle('show', opening);
    target.dispatchEvent(new Event(opening ? 'shown.bs.collapse' : 'hidden.bs.collapse', { bubbles: true }));
  };
  w.bootstrap = { Collapse: class {} };
  document.addEventListener('click', listener);
  return {
    clicks: () => count,
    uninstall: () => {
      document.removeEventListener('click', listener);
      delete w.bootstrap;
    },
  };
}

function stored(key = DEFAULT_STORAGE_KEY): Record<string, Record<string, boolean>> {
  return JSON.parse(localStorage.getItem(key) ?? '{}');
}

beforeEach(() => {
  localStorage.clear();
  vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
    cb(0);
    return 0;
  });
});

afterEach(() => {
  document.body.innerHTML = '';
  localStorage.clear();
  delete w.bootstrap;
  delete w.jQuery;
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('helpers', () => {
  it('reads options from data attributes with defaults', () => {
    const el = document.createElement('div');
    expect(readOptions(el)).toEqual({
      menuCode: DEFAULT_MENU_CODE,
      storageKey: DEFAULT_STORAGE_KEY,
      mode: 'auto',
      branchClass: DEFAULT_BRANCH_CLASS,
    });
    el.setAttribute('data-dashboard-menu-collapse', 'sidebar');
    el.setAttribute('data-dashboard-menu-collapse-storage-key', 'beacon.navCollapse');
    el.setAttribute('data-dashboard-menu-collapse-mode', 'always');
    el.setAttribute('data-dashboard-menu-collapse-branch-class', 'is-branch-current');
    expect(readOptions(el)).toEqual({
      menuCode: 'sidebar',
      storageKey: 'beacon.navCollapse',
      mode: 'always',
      branchClass: 'is-branch-current',
    });
    el.setAttribute('data-dashboard-menu-collapse-storage-key', '');
    el.setAttribute('data-dashboard-menu-collapse-mode', 'bogus');
    expect(readOptions(el).storageKey).toBe('');
    expect(readOptions(el).mode).toBe('auto');
  });

  it('resolves the target id from data-bs-target, href or aria-controls', () => {
    const b = document.createElement('button');
    expect(targetId(b)).toBe('');
    b.setAttribute('aria-controls', 'p1');
    expect(targetId(b)).toBe('p1');
    b.setAttribute('data-bs-target', '#p2');
    expect(targetId(b)).toBe('p2');
    b.setAttribute('data-bs-target', '#');
    expect(targetId(b)).toBe('p1');
    const a = document.createElement('a');
    a.setAttribute('href', '#p3');
    expect(targetId(a)).toBe('p3');
  });

  it('detects Bootstrap collapse JS (global bundle or jQuery plugin)', () => {
    expect(hasBootstrapCollapse(window)).toBe(false);
    w.bootstrap = {};
    expect(hasBootstrapCollapse(window)).toBe(false);
    w.bootstrap = { Collapse: class {} };
    expect(hasBootstrapCollapse(window)).toBe(true);
    delete w.bootstrap;
    w.jQuery = { fn: { collapse: () => undefined } };
    expect(hasBootstrapCollapse(window)).toBe(true);
    w.jQuery = { fn: {} };
    expect(hasBootstrapCollapse(window)).toBe(false);
  });
});

describe('MenuCollapse without Bootstrap JS', () => {
  it('applies the initial state, ARIA and ready class on connect', () => {
    const root = mount();
    const instance = new MenuCollapse(root).connect();
    expect(instance.isActive()).toBe(true);
    expect(root.classList.contains(READY_CLASS)).toBe(true);
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
    expect(panel('sidebar-item-2').hasAttribute('inert')).toBe(false);
    expect(panel('sidebar-item-2').getAttribute('aria-hidden')).toBe('false');
    expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('true');
    expect(panel('sidebar-item-2').closest('li')?.classList.contains('is-expanded')).toBe(true);
  });

  it('toggles once per click, keeps aria-expanded in sync and remembers the state', () => {
    const root = mount();
    new MenuCollapse(root).connect();
    const events: CustomEvent[] = [];
    root.addEventListener(TOGGLE_EVENT, (e) => events.push(e as CustomEvent));

    (toggleFor('sidebar-item-2').querySelector('.icon') as HTMLElement).click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    expect(panel('sidebar-item-2').hasAttribute('inert')).toBe(true);
    expect(panel('sidebar-item-2').getAttribute('aria-hidden')).toBe('true');
    expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('false');
    const li = panel('sidebar-item-2').closest('li') as HTMLElement;
    expect(li.classList.contains('is-collapsed')).toBe(true);
    expect(li.classList.contains('is-expanded')).toBe(false);
    expect(stored()).toEqual({ sidebar: { 'sidebar-item-2': false } });
    expect(events[0].detail).toEqual({ menuCode: 'sidebar', id: 'sidebar-item-2', open: false });

    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
    expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('true');
    expect(stored()).toEqual({ sidebar: { 'sidebar-item-2': true } });
  });

  it('restores the remembered state, forcing the current branch open', () => {
    localStorage.setItem(
      'beacon.navCollapse',
      JSON.stringify({ sidebar: { 'sidebar-item-1': false, 'sidebar-item-2': false }, other: { x: true } }),
    );
    const root = mount(menuMarkup('data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-storage-key="beacon.navCollapse"'));
    new MenuCollapse(root).connect();
    expect(panel('sidebar-item-1').classList.contains('show')).toBe(true);
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('false');

    toggleFor('sidebar-item-1').click();
    expect(stored('beacon.navCollapse')).toEqual({
      sidebar: { 'sidebar-item-1': false, 'sidebar-item-2': false },
      other: { x: true },
    });
  });

  it('honours a custom branch class and ignores non-boolean stored values', () => {
    localStorage.setItem(DEFAULT_STORAGE_KEY, JSON.stringify({ sidebar: { 'sidebar-item-1': false, 'sidebar-item-2': 'no' } }));
    const root = mount(menuMarkup('data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-branch-class="is-branch-current"'));
    new MenuCollapse(root).connect();
    expect(panel('sidebar-item-1').classList.contains('show')).toBe(false);
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
  });

  it('does not persist when the storage key is empty', () => {
    const root = mount(menuMarkup('data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-storage-key=""'));
    new MenuCollapse(root).connect();
    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    expect(localStorage.length).toBe(0);
  });

  it('survives invalid or unavailable storage', () => {
    for (const raw of ['{bad', '[1]', 'null', JSON.stringify({ sidebar: [true] }), JSON.stringify({ sidebar: null })]) {
      localStorage.setItem(DEFAULT_STORAGE_KEY, raw);
      const root = mount();
      new MenuCollapse(root).connect();
      expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
    }
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    const root = mount();
    new MenuCollapse(root).connect();
    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
  });

  it('ignores clicks outside toggles, toggles without target and unknown / foreign panels', () => {
    const root = mount(
      `${menuMarkup()}<div id="foreign" class="collapse show"></div>`,
    );
    const instance = new MenuCollapse(root).connect();
    (root.querySelector('a') as HTMLElement).click();
    root.firstChild?.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    const orphan = document.createElement('button');
    orphan.setAttribute(TOGGLE_ATTR, '');
    root.appendChild(orphan);
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    orphan.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);

    expect(instance.toggle('')).toBeNull();
    expect(instance.toggle('missing')).toBeNull();
    expect(instance.toggle('foreign')).toBeNull();
    expect(instance.toggle('sidebar-item-2', true)).toBe(true);
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
  });

  it('supports href toggles and adds a missing aria-controls', () => {
    const root = mount(`
      <div data-dashboard-menu-collapse="main">
        <a href="#dashboard-menu-main" class="dashboard-menu-toggle" data-bs-toggle="collapse">Main</a>
        <div id="dashboard-menu-main" class="collapse"><ul><li>x</li></ul></div>
      </div>`);
    new MenuCollapse(root).connect();
    const toggle = root.querySelector('a') as HTMLElement;
    expect(toggle.getAttribute('aria-controls')).toBe('dashboard-menu-main');
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    toggle.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(panel('dashboard-menu-main').classList.contains('show')).toBe(true);
    expect(stored()).toEqual({ main: { 'dashboard-menu-main': true } });
  });

  it('leaves <li> classes alone when the panel item is outside the root', () => {
    document.body.innerHTML = `
      <ul><li id="outer">
        <div data-dashboard-menu-collapse="x">
          <button data-bs-toggle="collapse" data-bs-target="#p">t</button>
          <div id="p" class="collapse show"></div>
        </div>
      </li></ul>`;
    const root = document.querySelector<HTMLElement>('[data-dashboard-menu-collapse]') as HTMLElement;
    new MenuCollapse(root).connect();
    (root.querySelector('button') as HTMLElement).click();
    expect(panel('p').classList.contains('show')).toBe(false);
    expect((document.getElementById('outer') as HTMLElement).className).toBe('');
  });

  it('does not double-toggle panels of a nested collapse root', () => {
    document.body.innerHTML = `
      <nav data-dashboard-menu-collapse="outer">${menuMarkup('data-dashboard-menu-collapse="sidebar"')}</nav>`;
    initMenuCollapse(document);
    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    expect(stored()).toEqual({ sidebar: { 'sidebar-item-2': false } });
  });

  it('stops listening on disconnect', () => {
    const root = mount();
    const instance = new MenuCollapse(root).connect();
    instance.disconnect();
    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
  });
});

describe('MenuCollapse with Bootstrap JS present', () => {
  it('auto mode: Bootstrap toggles, the script does not toggle again but syncs ARIA and remembers', () => {
    const bs = installFakeBootstrap();
    try {
      const root = mount();
      const instance = new MenuCollapse(root).connect();
      expect(instance.isActive()).toBe(false);
      expect(toggleFor('sidebar-item-2').getAttribute('data-bs-toggle')).toBe('collapse');

      toggleFor('sidebar-item-2').click();
      expect(bs.clicks()).toBe(1);
      expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
      expect(panel('sidebar-item-2').hasAttribute('inert')).toBe(true);
      expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('false');
      expect(stored()).toEqual({ sidebar: { 'sidebar-item-2': false } });

      toggleFor('sidebar-item-2').click();
      expect(panel('sidebar-item-2').classList.contains('show')).toBe(true);
      expect(panel('sidebar-item-2').hasAttribute('inert')).toBe(false);
      expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('true');
      expect(stored()).toEqual({ sidebar: { 'sidebar-item-2': true } });
    } finally {
      bs.uninstall();
    }
  });

  it('auto mode: stands down when Bootstrap loads after connect', () => {
    const root = mount();
    new MenuCollapse(root).connect();
    const bs = installFakeBootstrap();
    try {
      toggleFor('sidebar-item-2').click();
      expect(bs.clicks()).toBe(1);
      expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    } finally {
      bs.uninstall();
    }
  });

  it('show.bs.collapse un-inerts the panel without touching classes', () => {
    const root = mount();
    new MenuCollapse(root).connect();
    toggleFor('sidebar-item-2').click();
    const p = panel('sidebar-item-2');
    expect(p.hasAttribute('inert')).toBe(true);
    p.dispatchEvent(new Event('show.bs.collapse', { bubbles: true }));
    expect(p.hasAttribute('inert')).toBe(false);
    expect(p.getAttribute('aria-hidden')).toBe('false');
    expect(p.classList.contains('show')).toBe(false);
  });

  it('ignores Bootstrap events from non-panels and does not persist id-less panels', () => {
    const root = mount(`${menuMarkup()}`);
    new MenuCollapse(root).connect();
    const li = root.querySelector('li') as HTMLElement;
    li.dispatchEvent(new Event('hidden.bs.collapse', { bubbles: true }));
    const anon = document.createElement('div');
    anon.className = 'collapse show';
    root.appendChild(anon);
    anon.classList.remove('show');
    anon.dispatchEvent(new Event('hidden.bs.collapse', { bubbles: true }));
    expect(anon.hasAttribute('inert')).toBe(true);
    expect(localStorage.length).toBe(0);
  });

  it('always mode: takes the toggles over so Bootstrap never sees them', () => {
    const bs = installFakeBootstrap();
    try {
      const root = mount(menuMarkup('data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-mode="always"'));
      const instance = new MenuCollapse(root).connect();
      expect(instance.isActive()).toBe(true);
      expect(toggleFor('sidebar-item-2').hasAttribute('data-bs-toggle')).toBe(false);
      expect(toggleFor('sidebar-item-2').hasAttribute(TOGGLE_ATTR)).toBe(true);

      toggleFor('sidebar-item-2').click();
      expect(bs.clicks()).toBe(0);
      expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
      expect(toggleFor('sidebar-item-2').getAttribute('aria-expanded')).toBe('false');
    } finally {
      bs.uninstall();
    }
  });

  it('always mode: leaves toggles of a nested root alone', () => {
    document.body.innerHTML = `
      <nav data-dashboard-menu-collapse="outer" data-dashboard-menu-collapse-mode="always">
        ${menuMarkup('data-dashboard-menu-collapse="sidebar"')}
      </nav>`;
    const outer = document.querySelector<HTMLElement>('nav') as HTMLElement;
    new MenuCollapse(outer).connect();
    expect(toggleFor('sidebar-item-2').getAttribute('data-bs-toggle')).toBe('collapse');
  });
});

describe('initMenuCollapse / autoInit', () => {
  it('connects each root once, including the scope itself', () => {
    const root = mount();
    expect(initMenuCollapse(root)).toHaveLength(1);
    expect(initMenuCollapse(document)).toHaveLength(0);
    expect(getMenuCollapse(root)).toBeInstanceOf(MenuCollapse);
    toggleFor('sidebar-item-2').click();
    expect(panel('sidebar-item-2').classList.contains('show')).toBe(false);
    expect(initMenuCollapse()).toHaveLength(0);
  });

  it('boots on DOMContentLoaded and Turbo events and exposes a global', () => {
    const doc = document.implementation.createHTMLDocument('t');
    Object.defineProperty(doc, 'readyState', { value: 'loading', configurable: true });
    const fakeWin = { requestAnimationFrame: (cb: FrameRequestCallback) => cb(0), localStorage } as unknown as Window;
    doc.body.innerHTML = menuMarkup();
    autoInit(doc, fakeWin);
    expect(fakeWin.NowoDashboardMenuCollapse?.init).toBe(initMenuCollapse);
    expect(fakeWin.NowoDashboardMenuCollapse?.get).toBe(getMenuCollapse);
    const first = doc.querySelector('[data-dashboard-menu-collapse]') as Element;
    expect(getMenuCollapse(first)).toBeUndefined();
    doc.dispatchEvent(new Event('DOMContentLoaded'));
    expect(getMenuCollapse(first)).toBeDefined();

    doc.body.innerHTML = menuMarkup();
    const second = doc.querySelector('[data-dashboard-menu-collapse]') as Element;
    doc.dispatchEvent(new Event('turbo:load'));
    expect(getMenuCollapse(second)).toBeDefined();

    doc.body.innerHTML = menuMarkup();
    const third = doc.querySelector('[data-dashboard-menu-collapse]') as Element;
    doc.dispatchEvent(new Event('turbo:frame-load'));
    expect(getMenuCollapse(third)).toBeDefined();
  });

  it('boots immediately when the document is already parsed', () => {
    const root = mount();
    autoInit();
    expect(getMenuCollapse(root)).toBeDefined();
    expect(window.NowoDashboardMenuCollapse?.init).toBe(initMenuCollapse);
  });
});
