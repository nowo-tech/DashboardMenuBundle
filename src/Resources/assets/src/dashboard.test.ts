import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type SortableStub from '../test-support/sortable';

type DashboardModule = typeof import('./dashboard');

type Booted = {
  mod: DashboardModule;
  sortable: typeof SortableStub;
};

const listeners: Array<[Document, string, EventListenerOrEventListenerObject, unknown]> = [];
const originalAddEventListener = document.addEventListener.bind(document);

function island(config: Record<string, unknown>): void {
  const script = document.createElement('script');
  script.type = 'application/json';
  script.id = 'nowo-dashboard-menu-config';
  script.textContent = JSON.stringify(config);
  document.head.appendChild(script);
}

async function boot(config?: Record<string, unknown>): Promise<Booted> {
  if (config) island(config);
  vi.resetModules();
  const sortable = (await import('../test-support/sortable')).default;
  const mod = await import('./dashboard');
  return { mod, sortable };
}

const flush = async (): Promise<void> => {
  for (let i = 0; i < 6; i += 1) await Promise.resolve();
};

function formHtml(extra = ''): string {
  return `
    <div id="modal-item-form-container" data-app-routes='{"home":{"params":["id"]}}'>
      <form>
        <select name="item[itemType]">
          <option value="link">link</option>
          <option value="service">service</option>
          <option value="section">section</option>
          <option value="divider">divider</option>
        </select>
        <select name="item[parent]"><option value="">-</option><option value="3">3</option></select>
        <div id="item_link_fields" data-item-has-children="false"></div>
        <div id="item_parent_field"></div>
        <select name="item[linkType]"><option value="route">route</option><option value="external">external</option></select>
        <div id="route_name_row"></div>
        <div id="route_fields"></div>
        <div id="external_field"></div>
        <div id="item_service_link_block"></div>
        <div id="item_classic_link_block"></div>
        <div id="item_basic_label_icon_fields"></div>
        <div id="divider_label_hint"></div>
        <div id="item_section_collapsible_row"></div>
        <select name="item[routeName]"><option value="">-</option><option value="home">home</option><option value="plain">plain</option><option value="none">none</option></select>
        <textarea name="item[routeParams]"></textarea>
        <div id="route_params_hint"></div>
        ${extra}
      </form>
    </div>`;
}

function change(el: Element, value: string): void {
  (el as HTMLSelectElement | HTMLInputElement).value = value;
  el.dispatchEvent(new Event('change', { bubbles: true }));
}

describe('dashboard entry', () => {
  beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    for (const key of [
      '__nowoDashboardMenuConfig',
      '__dmScriptLoaded',
      '__dmDashboardMenuIndexBound',
      '__dmTomSelectModalPatchBound',
      'dashboardMenuI18n',
      'dashboardMenuIconSelectorScriptUrl',
      'dashboardMenuDebugLive',
      'nowoUiOpenDialog',
      'Stimulus',
      '$$stimulusApp$$',
      '__stimulusApp__',
    ]) {
      delete (window as unknown as Record<string, unknown>)[key];
    }
    vi.spyOn(document, 'addEventListener').mockImplementation((type, listener, options) => {
      listeners.push([document, type, listener, options]);
      originalAddEventListener(type, listener, options as AddEventListenerOptions);
    });
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'debug').mockImplementation(() => {});
    vi.spyOn(console, 'info').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    Element.prototype.scrollIntoView = vi.fn();
  });

  afterEach(() => {
    for (const [target, type, listener, options] of listeners.splice(0)) {
      target.removeEventListener(type, listener, options as EventListenerOptions);
    }
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  describe('configuration', () => {
    it('does nothing without a config island or legacy global', async () => {
      const { sortable } = await boot();

      expect(window.__dmScriptLoaded).toBeUndefined();
      expect(sortable.instances).toHaveLength(0);
    });

    it('falls back to the legacy window global and marks the script as loaded once', async () => {
      window.__nowoDashboardMenuConfig = { debug: true, i18n: { loading: 'Cargando' } };

      await boot();

      expect(window.__dmScriptLoaded).toBe(true);
      expect(window.dashboardMenuDebugLive).toBe(true);
    });

    it('lets the island override the legacy global and turns debug off by default', async () => {
      window.__nowoDashboardMenuConfig = { debug: true };

      await boot({ debug: false, iconSelectorScriptUrl: '/icons.js' });

      expect(window.dashboardMenuDebugLive).toBe(false);
      expect(window.dashboardMenuIconSelectorScriptUrl).toBe('/icons.js');
    });

    it('keeps an icon selector URL that the page already set', async () => {
      window.dashboardMenuIconSelectorScriptUrl = '/existing.js';

      await boot({ iconSelectorScriptUrl: '/other.js' });

      expect(window.dashboardMenuIconSelectorScriptUrl).toBe('/existing.js');
    });

    it('waits for DOMContentLoaded while the document is loading', async () => {
      Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'loading' });
      try {
        await boot({ debug: false });
        expect(window.__dmScriptLoaded).toBeUndefined();

        document.dispatchEvent(new Event('DOMContentLoaded'));

        expect(window.__dmScriptLoaded).toBe(true);
      } finally {
        delete (document as unknown as Record<string, unknown>).readyState;
      }
    });
  });

  describe('sortable reorder panel', () => {
    function panel(): void {
      document.body.innerHTML = `
        <div id="nowo-menu-sortable-root">
          <ul class="nowo-menu-sortable-list" data-parent-id="">
            <li class="nowo-menu-sortable-item" data-item-id="1" data-item-type="link">
              <ul class="nowo-menu-sortable-list" data-parent-id="1">
                <li class="nowo-menu-sortable-item" data-item-id="3" data-item-type="link"></li>
                <li class="nowo-menu-sortable-item" data-item-id="" data-item-type="link"></li>
              </ul>
            </li>
            <li class="nowo-menu-sortable-item" data-item-id="2" data-item-type="section"></li>
          </ul>
        </div>
        <form id="nowo-menu-sortable-form"><input id="nowo-menu-sortable-payload" /></form>`;
    }

    it('creates one Sortable per list, replacing earlier instances', async () => {
      panel();
      const root = document.getElementById('nowo-menu-sortable-root') as HTMLElement;
      const first = root.querySelector('ul') as HTMLElement;
      const { sortable } = await (async () => {
        vi.resetModules();
        const stub = (await import('../test-support/sortable')).default;
        const previous = new stub(first, {});
        island({ menuId: 5 });
        await import('./dashboard');
        expect(previous.destroyed).toBe(true);
        return { sortable: stub };
      })();

      expect(sortable.instances.length).toBe(3);
      expect(sortable.instances[1].options.handle).toBe('.nowo-menu-sortable-handle');
    });

    it('blocks a section from moving into a nested level but allows other moves', async () => {
      panel();
      const { sortable } = await boot({ baseUrl: '/menus' });
      const options = sortable.instances[0].options;
      const section = document.querySelector('[data-item-type="section"]') as HTMLElement;
      const link = document.querySelector('[data-item-type="link"]') as HTMLElement;
      const nested = document.querySelector('ul[data-parent-id="1"]') as HTMLElement;
      const top = document.querySelector('ul[data-parent-id=""]') as HTMLElement;

      expect(options.onMove?.({ dragged: section, to: nested })).toBe(false);
      expect(options.onMove?.({ dragged: section, to: top })).toBe(true);
      expect(options.onMove?.({ dragged: link, to: nested })).toBe(true);
      const orphan = document.createElement('ul');
      expect(options.onMove?.({ dragged: section, to: orphan })).toBe(true);
    });

    it('logs the end of a drag and serialises the tree on submit', async () => {
      panel();
      const { sortable } = await boot({ menuId: 1, debug: true });
      const options = sortable.instances[0].options;
      const item = document.querySelector('[data-item-id="3"]') as HTMLElement;
      const nested = document.querySelector('ul[data-parent-id="1"]') as HTMLElement;
      const top = document.querySelector('ul[data-parent-id=""]') as HTMLElement;
      const bare = document.createElement('ul');

      options.onEnd?.({ item, from: top, to: nested, oldIndex: 0, newIndex: 1, pullMode: 'clone' });
      options.onEnd?.({ item: bare, from: bare, to: bare, oldIndex: 0, newIndex: 0 });

      const form = document.getElementById('nowo-menu-sortable-form') as HTMLFormElement;
      form.dispatchEvent(new Event('submit'));
      const payload = document.getElementById('nowo-menu-sortable-payload') as HTMLInputElement;
      expect(JSON.parse(payload.value)).toEqual([
        { id: 1, parent_id: null, position: 0 },
        { id: 3, parent_id: 1, position: 0 },
        { id: 2, parent_id: null, position: 1 },
      ]);
    });

    it('binds the submit handler only once and tolerates a missing form or root', async () => {
      panel();
      await boot({ menuId: 1 });
      const form = document.getElementById('nowo-menu-sortable-form') as HTMLFormElement;
      expect(form.dataset.dmSortableSubmitBound).toBe('1');

      document.body.innerHTML = '';
      await boot({ menuId: 1 });
      document.body.innerHTML = '<div id="nowo-menu-sortable-root"></div>';
      const { sortable } = await boot({ menuId: 1 });
      expect(sortable.instances).toHaveLength(0);
    });

    it('serialises an empty tree when the root has no list', async () => {
      document.body.innerHTML = `
        <div id="nowo-menu-sortable-root"><span></span></div>`;
      const { sortable } = await boot({ menuId: 1 });
      expect(sortable.instances).toHaveLength(0);
    });
  });

  describe('dialog openers', () => {
    function page(): { opener: HTMLAnchorElement; dialog: HTMLDialogElement; body: HTMLElement } {
      document.body.innerHTML = `
        <a id="opener" href="/dashboard/items/new?section=config" data-nowo-dashboard-menu-dialog="dlg" title="New item"></a>
        <dialog id="dlg"><h2 id="dlg-title"></h2><div data-nowo-dashboard-menu-dialog-body></div></dialog>`;
      return {
        opener: document.getElementById('opener') as HTMLAnchorElement,
        dialog: document.getElementById('dlg') as HTMLDialogElement,
        body: document.querySelector('[data-nowo-dashboard-menu-dialog-body]') as HTMLElement,
      };
    }

    it('loads the form page into the dialog and wires it', async () => {
      const { opener, dialog, body } = page();
      const open = vi.fn();
      (window as unknown as Record<string, unknown>).nowoUiOpenDialog = open;
      const fetchMock = vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve(`${formHtml()}<form data-import-form><button type="submit">go</button></form><div id="item_config_section"></div>`) });
      vi.stubGlobal('fetch', fetchMock);
      await boot({ i18n: { loading: 'Cargando…' } });
      const wired = vi.fn();
      document.addEventListener('dashboard-menu:modal-content-loaded', wired);

      const event = new MouseEvent('click', { bubbles: true, cancelable: true });
      opener.dispatchEvent(event);
      expect(event.defaultPrevented).toBe(true);
      expect(body.textContent).toBe('Cargando…');
      expect(document.getElementById('dlg-title')?.textContent).toBe('New item');
      expect(open).toHaveBeenCalledWith(dialog, opener);
      // UiKit remote dialog protocol: the header selects the fragment, the URL stays the page URL (no ?_modal=1).
      expect(fetchMock).toHaveBeenCalledWith('/dashboard/items/new?section=config', { headers: { 'X-Nowo-Dialog': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });

      await flush();
      expect(body.querySelector('#modal-item-form-container')).not.toBeNull();
      expect(wired).toHaveBeenCalled();
      expect(Element.prototype.scrollIntoView).toHaveBeenCalled();

      const importForm = body.querySelector('form[data-import-form]') as HTMLFormElement;
      const submit = importForm.querySelector('button') as HTMLButtonElement;
      const first = new Event('submit', { cancelable: true });
      importForm.dispatchEvent(first);
      expect(submit.disabled).toBe(true);
      const second = new Event('submit', { cancelable: true });
      importForm.dispatchEvent(second);
      expect(second.defaultPrevented).toBe(true);
    });

    it('shows an error when the form cannot be loaded', async () => {
      const { opener, body } = page();
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500 }));
      await boot({});

      opener.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      await flush();

      expect(body.textContent).toBe('Error loading form.');
    });

    it('shows a translated error when the request fails', async () => {
      const { opener, body } = page();
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
      window.dashboardMenuI18n = { errorLoadingForm: 'No se pudo cargar' };
      await boot({});

      opener.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      await flush();

      expect(body.textContent).toBe('No se pudo cargar');
    });

    it('falls back to showModal when the UiKit opener is absent', async () => {
      const { opener, dialog } = page();
      dialog.showModal = vi.fn();
      vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})));
      await boot({});

      opener.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

      expect(dialog.showModal).toHaveBeenCalled();
    });

    it('does not reopen a dialog that is already open and survives a missing title and body', async () => {
      document.body.innerHTML = `
        <a id="opener" href="/x" data-nowo-dashboard-menu-dialog="dlg"><span class="nowo-ui-action__label"> Add </span></a>
        <dialog id="dlg"></dialog>`;
      const dialog = document.getElementById('dlg') as HTMLDialogElement;
      Object.defineProperty(dialog, 'open', { value: true });
      dialog.showModal = vi.fn();
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      await boot({});

      (document.getElementById('opener') as HTMLElement).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

      expect(dialog.showModal).not.toHaveBeenCalled();
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it.each([
      ['a disabled opener', '<a id="o" href="/x" aria-disabled="true" data-nowo-dashboard-menu-dialog="dlg"></a><dialog id="dlg"></dialog>'],
      ['a missing dialog', '<a id="o" href="/x" data-nowo-dashboard-menu-dialog="nope"></a>'],
      ['a placeholder href', '<a id="o" href="#" data-nowo-dashboard-menu-dialog="dlg"></a><dialog id="dlg"></dialog>'],
      ['a link without href', '<a id="o" data-nowo-dashboard-menu-dialog="dlg"></a><dialog id="dlg"></dialog>'],
    ])('leaves %s alone (the link opens the full page)', async (_name, html) => {
      document.body.innerHTML = html;
      vi.stubGlobal('fetch', vi.fn());
      await boot({});

      const event = new MouseEvent('click', { bubbles: true, cancelable: true });
      document.getElementById('o')?.dispatchEvent(event);

      expect(event.defaultPrevented).toBe(false);
    });

    it('ignores clicks outside any opener and binds only once per page', async () => {
      page();
      vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise(() => {})));
      await boot({});
      const before = listeners.filter(([, type]) => type === 'click').length;
      await import('./dashboard');
      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));

      expect(listeners.filter(([, type]) => type === 'click').length).toBe(before);
    });

    it('titles the dialog from aria-label, label text or text content', async () => {
      document.body.innerHTML = `
        <a id="a" href="/a" aria-label="Aria" data-nowo-dashboard-menu-dialog="dlg"></a>
        <a id="b" href="/b" data-nowo-dashboard-menu-dialog="dlg">Plain</a>
        <dialog id="dlg"><h2 id="dlg-title"></h2></dialog>`;
      (document.getElementById('dlg') as HTMLDialogElement).showModal = vi.fn();
      vi.stubGlobal('fetch', vi.fn());
      await boot({});

      document.getElementById('a')?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      expect(document.getElementById('dlg-title')?.textContent).toBe('Aria');
      document.getElementById('b')?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      expect(document.getElementById('dlg-title')?.textContent).toBe('Plain');
    });
  });

  describe('item form page', () => {
    it('wires the form in place and toggles the fields by item type', async () => {
      document.body.innerHTML = formHtml();
      await boot({ itemFormPage: true });
      const type = document.querySelector('[name="item[itemType]"]') as HTMLSelectElement;
      const link = document.getElementById('item_link_fields') as HTMLElement;
      const parentField = document.getElementById('item_parent_field') as HTMLElement;
      const hint = document.getElementById('divider_label_hint') as HTMLElement;
      const collapsible = document.getElementById('item_section_collapsible_row') as HTMLElement;
      const classic = document.getElementById('item_classic_link_block') as HTMLElement;
      const routeFields = document.getElementById('route_fields') as HTMLElement;

      expect(link.style.display).toBe('');
      expect(hint.style.display).toBe('none');
      expect(collapsible.style.display).toBe('none');

      change(type, 'section');
      expect(link.style.display).toBe('none');
      expect(parentField.style.display).toBe('none');
      expect(collapsible.style.display).toBe('');

      change(type, 'divider');
      expect(hint.style.display).toBe('');
      expect((document.querySelector('[name="item[parent]"]') as HTMLSelectElement).value).toBe('');

      change(type, 'service');
      expect(link.style.display).toBe('');
      expect(classic.style.display).toBe('none');
      expect(routeFields.style.display).toBe('none');

      change(type, 'link');
      expect(classic.style.display).toBe('');
      expect(parentField.style.display).toBe('');
    });

    it('switches between route and external destinations', async () => {
      document.body.innerHTML = formHtml();
      await boot({ itemFormPage: true });
      const linkType = document.querySelector('[name="item[linkType]"]') as HTMLSelectElement;

      change(linkType, 'external');
      expect((document.getElementById('route_fields') as HTMLElement).style.display).toBe('none');
      expect((document.getElementById('route_name_row') as HTMLElement).style.display).toBe('none');
      expect((document.getElementById('external_field') as HTMLElement).style.display).toBe('');

      change(linkType, 'route');
      expect((document.getElementById('external_field') as HTMLElement).style.display).toBe('none');
      expect((document.getElementById('route_fields') as HTMLElement).style.display).toBe('');
    });

    it('hides the link area of a link that has children and honours the initial type', async () => {
      document.body.innerHTML = formHtml().replace('data-item-has-children="false"', 'data-item-has-children="true" data-initial-item-type=" link "');
      document.querySelector('[name="item[itemType]"]')?.remove();
      await boot({ itemFormPage: true });

      expect((document.getElementById('item_link_fields') as HTMLElement).style.display).toBe('none');
    });

    it('suggests the route parameters for the chosen route', async () => {
      document.body.innerHTML = formHtml();
      await boot({ itemFormPage: true });
      const route = document.querySelector('[name="item[routeName]"]') as HTMLSelectElement;
      const params = document.querySelector('[name="item[routeParams]"]') as HTMLTextAreaElement;
      const hint = document.getElementById('route_params_hint') as HTMLElement;

      change(route, 'home');
      expect(hint.textContent).toContain('"id": ""');
      expect(params.value).toContain('"id"');
      expect(params.placeholder).toContain('"id"');

      params.value = '{"id":"7"}';
      change(route, 'home');
      expect(params.value).toBe('{"id":"7"}');

      change(route, 'unknown');
      expect(hint.textContent).toBe('');
      change(route, '');
      expect(hint.textContent).toBe('');
    });

    it('shows no hint for a route without parameters, and survives bad route data', async () => {
      document.body.innerHTML = formHtml().replace(/data-app-routes='[^']*'/, `data-app-routes='{"plain":{"params":[]},"none":{}}'`);
      await boot({ itemFormPage: true });
      const route = document.querySelector('[name="item[routeName]"]') as HTMLSelectElement;

      change(route, 'plain');
      expect((document.getElementById('route_params_hint') as HTMLElement).textContent).toBe('');
      change(route, 'none');
      expect((document.getElementById('route_params_hint') as HTMLElement).textContent).toBe('');

      document.body.innerHTML = formHtml().replace(/data-app-routes='[^']*'/, `data-app-routes='{broken'`);
      await boot({ itemFormPage: true });
      expect(document.getElementById('modal-item-form-container')).not.toBeNull();
    });

    it('works without a hint element or a parameters field', async () => {
      document.body.innerHTML = formHtml().replace('<div id="route_params_hint"></div>', '').replace('<textarea name="item[routeParams]"></textarea>', '');
      await boot({ itemFormPage: true });
      change(document.querySelector('[name="item[routeName]"]') as HTMLSelectElement, 'home');
      expect(document.getElementById('route_params_hint')).toBeNull();
    });

    it('works with a form that has neither route nor parameter fields, and with no form at all', async () => {
      document.body.innerHTML = '<div id="modal-item-form-container"><form></form></div>';
      await boot({ itemFormPage: true });
      expect(document.querySelector('form')).not.toBeNull();

      document.body.innerHTML = '<div id="modal-item-form-container"><p>no form</p></div>';
      await boot({ itemFormPage: true });
      expect(document.querySelector('p')).not.toBeNull();
    });

    it('ignores change events from outside a form of the container', async () => {
      document.body.innerHTML = formHtml('<input id="plain" name="other" />') + '<input id="outside" />';
      await boot({ itemFormPage: true });
      const outside = document.getElementById('outside') as HTMLElement;
      outside.dispatchEvent(new Event('change', { bubbles: true }));
      change(document.getElementById('plain') as HTMLElement, 'x');
      expect(document.getElementById('plain')).not.toBeNull();
    });

    it('re-applies the toggles after the Live component re-renders (debounced)', async () => {
      vi.useFakeTimers();
      document.body.innerHTML = formHtml();
      await boot({ itemFormPage: true });
      const container = document.getElementById('modal-item-form-container') as HTMLElement;
      const type = document.querySelector('[name="item[itemType]"]') as HTMLSelectElement;
      type.value = 'section';

      container.appendChild(document.createElement('span'));
      container.appendChild(document.createElement('span'));
      await vi.advanceTimersByTimeAsync(150);

      expect((document.getElementById('item_link_fields') as HTMLElement).style.display).toBe('none');
    });

    it('re-applies the toggles on a container that is the form itself', async () => {
      vi.useFakeTimers();
      document.body.innerHTML = '<form id="modal-item-form-container"><select name="item[itemType]"><option value="section">s</option></select><div id="item_link_fields"></div></form>';
      await boot({ itemFormPage: true });
      const container = document.getElementById('modal-item-form-container') as HTMLElement;

      container.appendChild(document.createElement('i'));
      await vi.advanceTimersByTimeAsync(150);

      expect((document.getElementById('item_link_fields') as HTMLElement).style.display).toBe('none');

      container.querySelector('select')?.remove();
      container.appendChild(document.createElement('b'));
      await vi.advanceTimersByTimeAsync(150);
    });

    it('wires a full item page from the appRoutes of the island (1.x)', async () => {
      document.body.innerHTML = formHtml();
      await boot({ appRoutes: { home: { params: ['id'] } } });

      change(document.querySelector('[name="item[routeName]"]') as HTMLSelectElement, 'home');
      expect((document.getElementById('route_params_hint') as HTMLElement).textContent).toContain('id');
    });

    it('does nothing for an item form page without its container', async () => {
      document.body.innerHTML = '<p>empty</p>';
      await boot({ itemFormPage: true });
      expect(document.querySelector('p')).not.toBeNull();
    });

    it('ignores an empty appRoutes map', async () => {
      document.body.innerHTML = formHtml();
      await boot({ appRoutes: {} });
      expect(document.getElementById('route_params_hint')?.textContent).toBe('');
    });
  });

  describe('Stimulus connection', () => {
    it('polls for the Stimulus app, then warns after the attempts run out', async () => {
      vi.useFakeTimers();
      document.body.innerHTML = formHtml();
      const { mod } = await boot({ debug: true });
      const container = document.getElementById('modal-item-form-container') as HTMLElement;

      mod.connectStimulusControllersInContainer(container);
      expect(container.dataset.dmStimulusConnectAttempts).toBe('1');
      await vi.advanceTimersByTimeAsync(200 * 31);

      expect(console.warn).toHaveBeenCalled();
    });

    it('connects the controllers through elementConnected and ignores its failures', async () => {
      document.body.innerHTML = '<div id="c"><div data-controller="a"></div><div data-controller="b"></div></div>';
      const connected: Element[] = [];
      window.Stimulus = {
        router: {
          elementConnected: (el: Element) => {
            connected.push(el);
            if (connected.length === 2) throw new Error('boom');
          },
        },
      };
      const { mod } = await boot({});
      const container = document.getElementById('c') as HTMLElement;
      container.dataset.dmStimulusConnectAttempts = '3';

      mod.connectStimulusControllersInContainer(container);

      expect(connected).toHaveLength(2);
      expect(container.dataset.dmStimulusConnectAttempts).toBeUndefined();
    });

    it('proposes each identifier when only proposeToConnectScopeForElementAndIdentifier exists', async () => {
      document.body.innerHTML = '<div id="c"><div data-controller="a b"></div><div data-controller="  "></div><div data-controller="c"></div></div>';
      const proposed: string[] = [];
      window.$$stimulusApp$$ = {
        router: {
          proposeToConnectScopeForElementAndIdentifier: (_el: Element, id: string) => {
            proposed.push(id);
            if (id === 'b') throw new Error('boom');
          },
        },
      };
      const { mod } = await boot({});

      mod.connectStimulusControllersInContainer(document.getElementById('c'));

      expect(proposed).toEqual(['a', 'b', 'c']);
    });

    it('relies on the MutationObserver when the router offers neither hook', async () => {
      document.body.innerHTML = '<div id="c"></div>';
      window.__stimulusApp__ = { router: {} };
      const { mod } = await boot({ debug: true });

      mod.connectStimulusControllersInContainer(document.getElementById('c'));
      mod.connectStimulusControllersInContainer(null);

      expect(console.debug).toHaveBeenCalled();
    });
  });

  describe('UX Autocomplete bootstrap', () => {
    const SCRIPT = '/bundles/nowodashboardmenu/js/autocomplete.js';
    const CSS = ['/bundles/nowouikit/css/vendor/tom-select.default.min.css', '/bundles/nowouikit/css/nowo-ui-tom-select.css'];

    it('registers the bundled controller once and injects the local Tom Select stylesheets (no CDN)', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { elementConnected: vi.fn(), modulesByIdentifier: new Map() } };
      document.body.innerHTML = formHtml();

      const { mod } = await boot({ itemFormPage: true, autocompleteScriptUrl: SCRIPT, tomSelectCssUrls: [...CSS, 'https://cdn.jsdelivr.net/x.css', '//cdn.example/x.css'] });
      await vi.waitFor(() => expect(register).toHaveBeenCalledTimes(1));
      mod.connectStimulusControllersInContainer(document.getElementById('modal-item-form-container'));
      await flush();

      expect(register).toHaveBeenCalledTimes(1);
      expect(register.mock.calls[0][0]).toBe('symfony--ux-autocomplete--autocomplete');
      expect(register.mock.calls[0][1].name).toBe('AutocompleteController');
      const links = [...document.querySelectorAll<HTMLLinkElement>('link[data-dm-autocomplete-css="1"]')].map((l) => l.getAttribute('href'));
      expect(links).toEqual(CSS);
      expect(document.querySelector('link[href*="jsdelivr"], link[href*="esm.sh"], style')).toBeNull();
    });

    it('refuses an absolute or protocol-relative script URL and never imports it', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map() } };
      for (const url of ['https://esm.sh/@symfony/ux-autocomplete@2', '//cdn.example/a.js', 'data:text/javascript,1']) {
        await boot({ autocompleteScriptUrl: url });
        await flush();
      }
      expect(register).not.toHaveBeenCalled();
    });

    it('does not inject the stylesheet when the host already has the Tom Select link', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map() } };
      const host = document.createElement('link');
      host.rel = 'stylesheet';
      host.setAttribute('href', CSS[0]);
      document.head.appendChild(host);

      await boot({ autocompleteScriptUrl: SCRIPT, tomSelectCssUrls: [CSS[0]] });
      await vi.waitFor(() => expect(register).toHaveBeenCalled());

      expect(document.querySelectorAll('link[data-dm-autocomplete-css="1"]')).toHaveLength(0);
    });

    it('does not duplicate a stylesheet it injected earlier', async () => {
      const link = document.createElement('link');
      link.dataset.dmAutocompleteCss = '1';
      document.head.appendChild(link);
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map() } };

      await boot({ autocompleteScriptUrl: SCRIPT, tomSelectCssUrls: CSS });
      await vi.waitFor(() => expect(register).toHaveBeenCalled());

      expect(document.querySelectorAll('link[data-dm-autocomplete-css="1"]')).toHaveLength(1);
    });

    it('leaves the host controller alone when it already registers the autocomplete', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map([['symfony--ux-autocomplete--autocomplete', {}]]) } };

      await boot({});
      await flush();

      expect(register).not.toHaveBeenCalled();
    });

    it('skips the bootstrap when it is disabled or the app cannot register yet', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: {} };
      await boot({ autocompleteBootstrap: false, autocompleteScriptUrl: '/bundles/nowodashboardmenu/js/autocomplete.js' });
      await flush();
      expect(register).not.toHaveBeenCalled();

      window.Stimulus = { router: {} };
      await boot({ autocompleteScriptUrl: '/bundles/nowodashboardmenu/js/autocomplete.js' });
      await flush();
      expect(register).not.toHaveBeenCalled();
    });

    it('registers nothing when the bundled module has no controller as default export', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map() } };
      vi.resetModules();
      vi.doMock('../test-support/autocomplete', () => ({ default: undefined }));
      island({ debug: true, autocompleteScriptUrl: '/bundles/nowodashboardmenu/js/autocomplete.js' });
      await import('./dashboard');
      await vi.waitFor(() => expect(console.warn).toHaveBeenCalled());
      expect(register).not.toHaveBeenCalled();
      vi.doUnmock('../test-support/autocomplete');
    });

    it('skips the bootstrap when the island carries no script URL', async () => {
      const register = vi.fn();
      window.Stimulus = { register, router: { modulesByIdentifier: new Map() } };
      await boot({});
      await flush();
      expect(register).not.toHaveBeenCalled();
    });

    it('warns instead of failing when the bundled module cannot be loaded', async () => {
      window.Stimulus = { register: vi.fn(), router: {} };
      vi.resetModules();
      vi.doMock('../test-support/autocomplete', () => {
        throw new Error('module unavailable');
      });
      island({ debug: true, autocompleteScriptUrl: '/bundles/nowodashboardmenu/js/autocomplete.js' });
      await import('./dashboard');
      await vi.waitFor(() => expect(console.warn).toHaveBeenCalled());
      vi.doUnmock('../test-support/autocomplete');
    });
  });

  describe('icon selector re-initialisation', () => {
    it('injects the script with a cache-busting parameter and a nonce, then reconnects on load', async () => {
      const nonceScript = document.createElement('script');
      nonceScript.setAttribute('nonce', 'abc');
      document.head.appendChild(nonceScript);
      document.body.innerHTML = '<div id="c" data-icon-selector-script-url="/icon-selector.js?v=1"></div>';
      const { mod } = await boot({});
      const append = vi.spyOn(document.head, 'appendChild');

      mod.reinitIconSelectorInContainer(document.getElementById('c') as HTMLElement);

      const injected = append.mock.calls[0][0] as HTMLScriptElement;
      expect(injected.getAttribute('src')).toMatch(/^\/icon-selector\.js\?v=1&_=\d+$/);
      expect(injected.async).toBe(false);
      expect(injected.nonce || injected.getAttribute('nonce')).toBe('abc');
      injected.dispatchEvent(new Event('load'));
      injected.onload?.(new Event('load'));
      expect(injected.isConnected).toBe(false);
    });

    it('prefers the configured URL, then a page script, and does nothing without any', async () => {
      document.body.innerHTML = '<div id="c"></div>';
      const { mod } = await boot({});
      const container = document.getElementById('c') as HTMLElement;
      const append = vi.spyOn(document.head, 'appendChild');

      mod.reinitIconSelectorInContainer(container);
      expect(append).not.toHaveBeenCalled();

      const page = document.createElement('script');
      page.setAttribute('src', 'https://example.test/icon_selector.js');
      document.head.appendChild(page);
      append.mockClear();
      mod.reinitIconSelectorInContainer(container);
      expect((append.mock.calls[0][0] as HTMLScriptElement).getAttribute('src')).toMatch(/^https:\/\/example\.test\/icon_selector\.js\?_=\d+$/);

      window.dashboardMenuIconSelectorScriptUrl = '/configured.js';
      append.mockClear();
      mod.reinitIconSelectorInContainer(container);
      expect((append.mock.calls[0][0] as HTMLScriptElement).getAttribute('src')).toMatch(/^\/configured\.js\?_=\d+$/);
    });
  });

  describe('Tom Select dropdown patch', () => {
    function select(extra: Record<string, unknown> | null): HTMLSelectElement {
      const el = document.createElement('select');
      if (extra) (el as unknown as { tomselect: unknown }).tomselect = extra;
      return el;
    }

    it('moves dropdowns to the body when modal content loads', async () => {
      vi.useFakeTimers();
      await boot({});
      const container = document.createElement('div');
      const dropdown = document.createElement('div');
      const moved = select({ dropdown, settings: {} });
      const already = select({ dropdown: document.createElement('div'), settings: { dropdownParent: 'body' } });
      const broken = select({ dropdown: null, settings: {} });
      const plain = select(null);
      container.append(moved, already, broken, plain);
      document.body.appendChild(container);

      document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded', { detail: { element: container } }));
      await vi.advanceTimersByTimeAsync(300);

      expect(dropdown.parentElement).toBe(document.body);
      expect((moved as unknown as { tomselect: { settings: { dropdownParent: string } } }).tomselect.settings.dropdownParent).toBe('body');
    });

    it('leaves dropdowns inside a native dialog alone and ignores events without an element', async () => {
      await boot({});
      const dialog = document.createElement('dialog');
      const container = document.createElement('div');
      const dropdown = document.createElement('div');
      container.appendChild(select({ dropdown, settings: {} }));
      dialog.appendChild(container);
      document.body.appendChild(dialog);

      document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded', { detail: { element: container } }));
      document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded', { detail: {} }));
      document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded'));

      expect(dropdown.parentElement).toBeNull();
    });

    it('ignores a dropdown that cannot be moved', async () => {
      await boot({});
      const container = document.createElement('div');
      const dropdown = document.createElement('div');
      const settings: Record<string, unknown> = {};
      container.appendChild(select({ dropdown, settings }));
      document.body.appendChild(container);
      vi.spyOn(document.body, 'appendChild').mockImplementation(() => {
        throw new Error('detached');
      });

      document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded', { detail: { element: container } }));

      expect(settings.dropdownParent).toBeUndefined();
    });
  });

  describe('Live component debug', () => {
    function liveForm(): HTMLElement {
      document.body.innerHTML = `
        <div id="c" data-controller="live">
          <div id="dm-livecomponent-debug" data-php-item-type="link" data-php-link-type="route"></div>
          <form>
            <select name="item[itemType]"></select>
            <select name="item[linkType]"></select>
          </form>
        </div>`;
      return document.getElementById('c') as HTMLElement;
    }

    it('logs a visibility snapshot on every change and re-binds after DOM updates', async () => {
      vi.stubGlobal('MutationObserver', class {
        static last: { callback: () => void } | null = null;
        constructor(callback: () => void) {
          (this.constructor as unknown as { last: unknown }).last = { callback };
        }
        observe(): void {}
        disconnect(): void {}
      });
      const { mod } = await boot({ debug: true });
      const container = liveForm();

      mod.attachLiveComponentDebug(container);
      const form = container.querySelector('form') as HTMLFormElement;
      form.querySelector('[name="item[itemType]"]')?.dispatchEvent(new Event('change'));
      form.querySelector('[name="item[linkType]"]')?.dispatchEvent(new Event('change'));
      const observer = (globalThis.MutationObserver as unknown as { last: { callback: () => void } }).last;
      observer.callback();

      expect(console.info).toHaveBeenCalled();
    });

    it('is a no-op unless the debug flag is on and tolerates forms without fields', async () => {
      const { mod } = await boot({});
      const container = liveForm();
      mod.attachLiveComponentDebug(container);
      expect(console.info).not.toHaveBeenCalled();

      window.dashboardMenuDebugLive = true;
      document.body.innerHTML = '<div id="c"></div>';
      mod.attachLiveComponentDebug(document.getElementById('c') as HTMLElement);
      document.body.innerHTML = '<div id="d"><form></form></div>';
      mod.attachLiveComponentDebug(document.getElementById('d') as HTMLElement);
      document.body.innerHTML = '<div id="e"><form><input /></form></div>';
      mod.attachLiveComponentDebug(document.getElementById('e') as HTMLElement);
    });

    it('attaches the debug helper while wiring a loaded form when debug is on', async () => {
      document.body.innerHTML = formHtml();
      await boot({ itemFormPage: true, debug: true });

      expect(console.info).toHaveBeenCalled();
    });
  });
});
