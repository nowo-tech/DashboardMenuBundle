/**
 * Dashboard menu bundle: single entry for all dashboard view scripts.
 * Reads config from the JSON island #nowo-dashboard-menu-config (CSP-safe; fallback: legacy window.__nowoDashboardMenuConfig) and initializes modals, Stimulus connection, and form toggles.
 */
import Sortable from 'sortablejs';

import { readDashboardMenuConfig, resolveCspNonce } from './config';
import { createBundleLogger } from './logger';

declare const __DASHBOARD_MENU_BUILD_TIME__: string | undefined;

/** Captured while the bundle evaluates (document.currentScript is null afterwards). */
const cspNonce = typeof document !== 'undefined' ? resolveCspNonce(document, document.currentScript) : '';

const log = createBundleLogger('dashboard-menu', {
  buildTime: typeof __DASHBOARD_MENU_BUILD_TIME__ !== 'undefined' ? __DASHBOARD_MENU_BUILD_TIME__ : undefined,
});

declare global {
  interface Window {
    /** @deprecated Since 2.2.0 — use the #nowo-dashboard-menu-config JSON island. */
    __nowoDashboardMenuConfig?: NowoDashboardMenuConfig;
    __dmScriptLoaded?: boolean;
    /** Prevents duplicate event listeners if dashboard.js is executed more than once (e.g. two script tags). */
    __dmDashboardMenuShowBound?: boolean;
    __dmDashboardMenuIndexBound?: boolean;
    __dmTomSelectModalPatchBound?: boolean;
    dashboardMenuI18n?: Record<string, string>;
    dashboardMenuIconSelectorScriptUrl?: string;
    dashboardMenuDebugLive?: boolean;
    Stimulus?: StimulusAppLike;
    $$stimulusApp$$?: StimulusAppLike;
    __stimulusApp__?: StimulusAppLike;
  }
}

/** Stimulus app shape: router may expose elementConnected (custom) or proposeToConnectScopeForElementAndIdentifier (Stimulus 3). */
interface StimulusAppLike {
  register?(identifier: string, controllerConstructor: unknown): void;
  router?: {
    elementConnected?(el: Element): void;
    proposeToConnectScopeForElementAndIdentifier?(element: Element, identifier: string): void;
    modulesByIdentifier?: Map<string, unknown>;
  };
}

export interface NowoDashboardMenuConfig {
  baseUrl?: string;
  menuId?: number;
  dashboardBase?: string;
  appRoutes?: Record<string, { params?: string[] }>;
  debug?: boolean;
  cssFramework?: string;
  /** dashboard.autocomplete_bootstrap (2.3.2): false = never load UX Autocomplete / Tom Select CSS from a CDN. */
  autocompleteBootstrap?: boolean;
  /** 2.7: translated dialog strings (loading, errorLoadingForm). */
  i18n?: Record<string, string>;
  /** 2.7: icon-selector script URL (dashboard.icon_selector_script_url) for re-init in loaded dialog bodies. */
  iconSelectorScriptUrl?: string | null;
  /** 2.7: the page is the full item form (page/form scaffold). */
  itemFormPage?: boolean;
}

let configI18n: Record<string, string> = {};

const i18n = (key: string, fallback: string) =>
  configI18n[key] ?? (typeof window !== 'undefined' ? window.dashboardMenuI18n?.[key] : undefined) ?? fallback;

const AUTOCOMPLETE_IDENTIFIER = 'symfony--ux-autocomplete--autocomplete';
let autocompleteControllerReady = false;
let autocompleteControllerPromise: Promise<void> | null = null;
let autocompleteBootstrapEnabled = true;

function describeStimulusAppLike(app: StimulusAppLike | null): Record<string, boolean> {
  return {
    hasApp: !!app,
    hasRegister: !!app?.register,
    hasRouter: !!app?.router,
    hasElementConnected: !!app?.router?.elementConnected,
    hasProposeToConnect: !!app?.router?.proposeToConnectScopeForElementAndIdentifier,
  };
}

function ensureTomSelectCssLoaded(): void {
  if (typeof document === 'undefined') return;
  const existing = document.querySelector('link[data-dm-autocomplete-css="1"]');
  if (existing) {
    log.debug('Tom Select CSS already loaded.');
    return;
  }
  const href = 'https://cdn.jsdelivr.net/npm/tom-select@2.5.2/dist/css/tom-select.bootstrap5.css';
  if (document.querySelector(`link[href="${href}"]`)) {
    log.debug('Tom Select CSS found in document head.');
    return;
  }
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  link.dataset.dmAutocompleteCss = '1';
  (document.head ?? document.documentElement).appendChild(link);
  log.debug('Tom Select CSS injected by dashboard script.', { href });
}

async function ensureAutocompleteControllerRegistered(): Promise<void> {
  if (!autocompleteBootstrapEnabled || autocompleteControllerReady) return;
  if (autocompleteControllerPromise) return autocompleteControllerPromise;
  autocompleteControllerPromise = (async () => {
    try {
      const app =
        window.Stimulus ?? window.$$stimulusApp$$ ?? window.__stimulusApp__ ?? null;
      log.debug('Autocomplete bootstrap: resolved StimulusAppLike.', describeStimulusAppLike(app));
      if (!app || typeof app.register !== 'function') {
        log.debug('Autocomplete bootstrap skipped: StimulusAppLike.register not available yet.');
        return;
      }
      if (app.router?.modulesByIdentifier?.has(AUTOCOMPLETE_IDENTIFIER)) {
        // The host app already ships UX Autocomplete (and its Tom Select styles): do not override it.
        autocompleteControllerReady = true;
        log.debug('Autocomplete bootstrap skipped: controller already registered by the host app.');
        return;
      }

      const mod = await import('https://esm.sh/@symfony/ux-autocomplete@2');
      const controllerCtor = (mod as { default?: unknown }).default ?? mod;
      if (!controllerCtor) return;

      app.register(AUTOCOMPLETE_IDENTIFIER, controllerCtor);
      ensureTomSelectCssLoaded();
      autocompleteControllerReady = true;
      log.debug('Registered Symfony UX Autocomplete controller from bundle dashboard script.', {
        identifier: AUTOCOMPLETE_IDENTIFIER,
      });
    } catch (e) {
      log.warn('Could not auto-register Symfony UX Autocomplete controller.', e);
    } finally {
      autocompleteControllerPromise = null;
    }
  })();
  return autocompleteControllerPromise;
}

function connectStimulusControllersInContainer(container: Element | null): void {
  if (!container) return;
  void ensureAutocompleteControllerRegistered();
  const app =
    window.Stimulus ?? window.$$stimulusApp$$ ?? window.__stimulusApp__ ?? null;
  if (!app) {
    const attempts = parseInt(
      (container as HTMLElement).dataset.dmStimulusConnectAttempts ?? '0',
      10
    );
    const maxAttempts = 30;
    if (attempts < maxAttempts) {
      (container as HTMLElement).dataset.dmStimulusConnectAttempts = String(
        attempts + 1
      );
      if (attempts === 0) {
        log.debug('Stimulus app not found on window. Polling for up to ~6s.', {
          stimulusGlobals: Object.keys(window).filter((k) =>
            k.toLowerCase().includes('stim'),
          ),
        });
      }
      setTimeout(() => connectStimulusControllersInContainer(container), 200);
    } else {
      log.warn(
        'Stimulus app never appeared after polling. Ensure the layout loads the stimulus script (e.g. nowo_dashboard_menu.dashboard.stimulus_script_url or your app entry that sets window.Stimulus).',
      );
    }
    return;
  }
  delete (container as HTMLElement).dataset.dmStimulusConnectAttempts;
  const router = app.router;
  if (router?.elementConnected) {
    container.querySelectorAll('[data-controller]').forEach((el) => {
      try {
        router.elementConnected!(el);
      } catch {
        /* ignore */
      }
    });
    return;
  }
  if (router?.proposeToConnectScopeForElementAndIdentifier) {
    container.querySelectorAll('[data-controller]').forEach((el) => {
      const value = el.getAttribute('data-controller')?.trim();
      if (!value) return;
      const identifiers = value.split(/\s+/).filter(Boolean);
      identifiers.forEach((identifier) => {
        try {
          router.proposeToConnectScopeForElementAndIdentifier!(el, identifier);
        } catch {
          /* ignore */
        }
      });
    });
    return;
  }
  log.debug(
    'Stimulus router has neither elementConnected nor proposeToConnectScopeForElementAndIdentifier; relying on MutationObserver for dynamic content.',
  );
}

/** Tom Select (incl. icon-selector-bundle) defaults dropdown to the control wrapper; modal overflow clips it. Moving to body + {@code settings.dropdownParent = 'body'} enables {@code positionDropdown()}. */
type TomSelectPatchable = {
  settings: { dropdownParent?: string };
  dropdown: HTMLElement;
};

function patchTomSelectDropdownsToBody(container: Element | null): void {
  if (!container) return;
  // A native modal <dialog> is in the top layer: a dropdown moved to <body> would render below it.
  if (container.closest('dialog')) return;
  container.querySelectorAll('select').forEach((sel) => {
    const ts = (sel as HTMLSelectElement & { tomselect?: TomSelectPatchable }).tomselect;
    if (!ts?.dropdown || !ts.settings) return;
    if (ts.settings.dropdownParent === 'body') return;
    try {
      document.body.appendChild(ts.dropdown);
      ts.settings.dropdownParent = 'body';
    } catch {
      /* ignore */
    }
  });
}

function scheduleTomSelectModalPatches(container: Element): void {
  patchTomSelectDropdownsToBody(container);
  setTimeout(() => patchTomSelectDropdownsToBody(container), 50);
  setTimeout(() => patchTomSelectDropdownsToBody(container), 250);
}

function reinitIconSelectorInContainer(container: Element): void {
  const url =
    window.dashboardMenuIconSelectorScriptUrl ??
    ((container.getAttribute('data-icon-selector-script-url') ?? '').trim() ||
      (document.querySelector('script[src*="icon-selector"], script[src*="icon_selector"]') as HTMLScriptElement)?.src);
  if (!url) return;
  const script = document.createElement('script');
  const nonce = cspNonce || resolveCspNonce(document);
  if (nonce) script.nonce = nonce;
  script.src = `${url}${url.includes('?') ? '&' : '?'}_=${Date.now()}`;
  script.async = false;
  script.onload = () => {
    connectStimulusControllersInContainer(container);
    scheduleTomSelectModalPatches(container);
    script.remove();
  };
  (document.head ?? document.documentElement).appendChild(script);
}

function attachLiveComponentDebug(_container: Element): void {
  if (!window.dashboardMenuDebugLive) return;
  const container = _container;
  let observer: MutationObserver | null = null;

  const isInDNone = (el: Element | null) =>
    el ? !!el.closest('.d-none') : false;

  const logVisibilitySnapshot = (reason: string) => {
    const form = container.querySelector('form');
    if (!form) return;
    const phpDebugEl = container.querySelector('#dm-livecomponent-debug') as HTMLElement | null;
    const phpDebug = phpDebugEl?.dataset
      ? {
          itemType: phpDebugEl.dataset.phpItemType,
          linkType: phpDebugEl.dataset.phpLinkType,
          showLinkFields: phpDebugEl.dataset.phpShowLinkFields,
          showRouteFields: phpDebugEl.dataset.phpShowRouteFields,
          showExternalUrlField: phpDebugEl.dataset.phpShowExternalUrl,
          itemHasChildren: phpDebugEl.dataset.phpItemHasChildren,
        }
      : {};
    log.debug('Visibility snapshot:', reason, { php: phpDebug });
  };

  const setupListeners = () => {
    const form = container.querySelector('form');
    if (!form) return;
    const liveEl = container.querySelector('[data-controller="live"]');
    if (liveEl) log.debug('live controller element found');
    const itemType = form.querySelector<HTMLSelectElement>('[name*="[itemType]"]');
    const linkType = form.querySelector<HTMLSelectElement>('[name*="[linkType]"]');
    if (itemType && !itemType.dataset.dmLiveDebugBound) {
      itemType.dataset.dmLiveDebugBound = '1';
      itemType.addEventListener('change', () =>
        logVisibilitySnapshot('after Type change')
      );
    }
    if (linkType && !linkType.dataset.dmLiveDebugBound) {
      linkType.dataset.dmLiveDebugBound = '1';
      linkType.addEventListener('change', () =>
        logVisibilitySnapshot('after Link type change')
      );
    }
    logVisibilitySnapshot('after (re)bind');
  };

  setupListeners();
  observer = new MutationObserver(() => {
    setupListeners();
  });
  observer.observe(container, { childList: true, subtree: true });
  log.info('Debug enabled: listeners re-attached + console logs.');
}

function serializeMenuSortable(root: HTMLElement): Array<{ id: number; parent_id: number | null; position: number }> {
  const out: Array<{ id: number; parent_id: number | null; position: number }> = [];
  const walk = (ul: HTMLElement, parentId: number | null): void => {
    const items = ul.querySelectorAll(':scope > li.nowo-menu-sortable-item');
    items.forEach((li, position) => {
      const raw = li.getAttribute('data-item-id');
      const id = raw ? parseInt(raw, 10) : 0;
      if (!id) {
        return;
      }
      out.push({ id, parent_id: parentId, position });
      const nested = li.querySelector(':scope > ul.nowo-menu-sortable-list');
      if (nested instanceof HTMLElement) {
        walk(nested, id);
      }
    });
  };
  const firstUl = root.querySelector(':scope > ul.nowo-menu-sortable-list');
  if (firstUl instanceof HTMLElement) {
    walk(firstUl, null);
  }
  return out;
}

function initMenuSortablePanel(): void {
  const root = document.getElementById('nowo-menu-sortable-root');
  if (!root) {
    return;
  }
  const lists = root.querySelectorAll<HTMLElement>('ul.nowo-menu-sortable-list');
  let sortableListsInitialized = 0;
  lists.forEach((list) => {
    const existing = Sortable.get(list);
    if (existing) {
      existing.destroy();
    }
    new Sortable(list, {
      group: { name: 'nowo-menu-dashboard', pull: true, put: true },
      animation: 150,
      handle: '.nowo-menu-sortable-handle',
      draggable: '.nowo-menu-sortable-item',
      fallbackOnBody: true,
      swapThreshold: 0.65,
      emptyInsertThreshold: 24,
      onMove: (evt) => {
        const dragged = evt.dragged as HTMLElement;
        if (dragged.getAttribute('data-item-type') === 'section') {
          const targetList = evt.to as HTMLElement;
          const parentId = targetList.getAttribute('data-parent-id');
          if (parentId !== null && parentId !== '') {
            log.debug('SortableJS: blocked moving section into nested level', {
              targetParentId: parentId,
            });
            return false;
          }
        }
        return true;
      },
      onEnd: (evt) => {
        const itemId = evt.item.getAttribute('data-item-id');
        const fromParentAttr = evt.from.getAttribute('data-parent-id');
        const toParentAttr = evt.to.getAttribute('data-parent-id');
        log.debug('SortableJS: menu item position changed', {
          itemId: itemId ?? null,
          oldIndex: evt.oldIndex,
          newIndex: evt.newIndex,
          fromParentId: fromParentAttr === null || fromParentAttr === '' ? null : fromParentAttr,
          toParentId: toParentAttr === null || toParentAttr === '' ? null : toParentAttr,
          sameList: evt.from === evt.to,
          pullMode: evt.pullMode ?? null,
        });
      },
    });
    sortableListsInitialized += 1;
  });
  if (sortableListsInitialized > 0) {
    log.announce(
      `SortableJS active (${sortableListsInitialized} drop zone(s); drag via ⋮⋮ handle)`,
    );
  }
  const form = document.getElementById('nowo-menu-sortable-form');
  const payload = document.getElementById('nowo-menu-sortable-payload') as HTMLInputElement | null;
  if (form && payload && !form.dataset.dmSortableSubmitBound) {
    form.dataset.dmSortableSubmitBound = '1';
    form.addEventListener('submit', () => {
      payload.value = JSON.stringify(serializeMenuSortable(root));
    });
  }
}

function initShowPage(): void {
  // Sortable must run on every (re)visit of the reorder page (e.g. Turbo / in-app navigation).
  initMenuSortablePanel();
}

/** Opener attribute of the dashboard form dialogs (value: the UiKit dialog id). */
const DIALOG_ATTRIBUTE = 'data-nowo-dashboard-menu-dialog';

function dialogTitleOf(opener: HTMLElement): string {
  return (
    opener.getAttribute('title') ??
    opener.getAttribute('aria-label') ??
    opener.querySelector('.nowo-ui-action__label')?.textContent ??
    opener.textContent ??
    ''
  ).trim();
}

function openNativeDialog(dialog: HTMLDialogElement, opener: HTMLElement): void {
  const open = (window as Window & { nowoUiOpenDialog?: (d: HTMLDialogElement, o: HTMLElement) => boolean }).nowoUiOpenDialog;
  if (typeof open === 'function') {
    open(dialog, opener);
    return;
  }
  if (!dialog.open && typeof dialog.showModal === 'function') {
    dialog.showModal();
  }
}

function scrollToSection(container: Element, section: string): void {
  const ids: Record<string, string> = {
    config: '#item_config_section, #menu_config_section',
    basic: '#item_basic_section, #menu_definition_section',
    icon: '#item_icon_section',
  };
  const selector = ids[section];
  const el = selector ? container.querySelector(selector) : null;
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Wire a form body loaded into a dialog (or rendered on the item form page): item type toggles, Stimulus (Live
 * component, autocomplete), icon selector, import double-submit guard.
 */
function wireFormBody(body: Element, section: string, inDialog = true): void {
  const itemContainer = body.querySelector('#modal-item-form-container') ?? (body.matches('#modal-item-form-container') ? body : null);
  if (itemContainer) {
    if (inDialog) reinitIconSelectorInContainer(itemContainer);
    connectStimulusControllersInContainer(itemContainer);
    attachItemFormToggles(itemContainer, {});
    document.dispatchEvent(new CustomEvent('dashboard-menu:modal-content-loaded', { detail: { element: itemContainer } }));
    if (window.dashboardMenuDebugLive) {
      attachLiveComponentDebug(itemContainer);
    }
  }
  if (section) scrollToSection(body, section);
  body.querySelectorAll<HTMLFormElement>('form[data-import-form]').forEach((form) => {
    if (form.dataset.dmSubmitGuard === '1') return;
    form.dataset.dmSubmitGuard = '1';
    form.addEventListener('submit', (e) => {
      if (form.dataset.dmSubmitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.dmSubmitting = '1';
      const submitBtn = form.querySelector<HTMLButtonElement>('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
    });
  });
}

/**
 * Dialog openers (2.7): links to a form page carrying data-nowo-dashboard-menu-dialog="<dialog id>". The link is
 * fetched with ?_modal=1 into the dialog body and the UiKit dialog opens; without JavaScript (or without the
 * dialog on the page) the link opens the full form page.
 */
function initDialogOpeners(): void {
  if (typeof window !== 'undefined' && window.__dmDashboardMenuIndexBound) {
    return;
  }
  if (typeof window !== 'undefined') {
    window.__dmDashboardMenuIndexBound = true;
  }
  document.addEventListener(
    'click',
    (e) => {
      const target = e.target as Element | null;
      const opener = target?.closest<HTMLElement>(`[${DIALOG_ATTRIBUTE}]`);
      if (!opener || opener.getAttribute('aria-disabled') === 'true') return;
      const dialogId = opener.getAttribute(DIALOG_ATTRIBUTE) ?? '';
      const dialog = document.getElementById(dialogId);
      const href = opener.getAttribute('href');
      if (!(dialog instanceof HTMLDialogElement) || !href || href === '#') return;
      e.preventDefault();
      const body = dialog.querySelector<HTMLElement>('[data-nowo-dashboard-menu-dialog-body]');
      const title = document.getElementById(`${dialogId}-title`);
      const titleText = dialogTitleOf(opener);
      if (title && titleText) title.textContent = titleText;
      const url = new URL(href, window.location.href);
      url.searchParams.set('_modal', '1');
      const section = url.searchParams.get('section') ?? '';
      if (body) {
        const loading = document.createElement('p');
        loading.className = 'nowo-ui-muted';
        loading.textContent = i18n('loading', 'Loading…');
        body.replaceChildren(loading);
      }
      openNativeDialog(dialog, opener);
      if (!body) return;
      fetch(url.pathname + url.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => {
          if (!r.ok) throw new Error(String(r.status));
          return r.text();
        })
        .then((html) => {
          body.innerHTML = html;
          wireFormBody(body, section);
        })
        .catch(() => {
          const error = document.createElement('p');
          error.className = 'nowo-ui-flash nowo-ui-flash--error';
          error.textContent = i18n('errorLoadingForm', 'Error loading form.');
          body.replaceChildren(error);
        });
    },
    true,
  );
}

/** App routes map: route name -> { label, params }. Used for route selector and params hint. */
type AppRoutesMap = Record<string, { label?: string; params?: string[] }>;

/**
 * Attach visibility toggles (itemType → link fields, linkType → route vs external) and route params hint
 * to an item form inside the given container. Works with full page form and modal (including Live re-renders).
 */
function attachItemFormToggles(
  container: Element,
  appRoutes: AppRoutesMap = {},
): void {
  const form = container.matches('form') ? container : container.querySelector<HTMLFormElement>('form');
  if (!form) return;

  const resolvedAppRoutes =
    Object.keys(appRoutes).length > 0
      ? appRoutes
      : ((): AppRoutesMap => {
          try {
            const raw = container.getAttribute('data-app-routes');
            return raw ? (JSON.parse(raw) as AppRoutesMap) : {};
          } catch {
            return {};
          }
        })();

  function updateToggles(f: HTMLFormElement): void {
    const itemTypeField = f.querySelector<HTMLSelectElement>('[name*="[itemType]"]');
    const itemLinkFields = f.querySelector<HTMLElement>('#item_link_fields');
    const itemParentField = f.querySelector<HTMLElement>('#item_parent_field');
    const parentSelect = f.querySelector<HTMLSelectElement>('[name*="[parent]"]');
    const linkType = f.querySelector<HTMLSelectElement>('[name*="[linkType]"]');
    const routeNameRow = f.querySelector<HTMLElement>('#route_name_row');
    const routeFields = f.querySelector<HTMLElement>('#route_fields');
    const externalField = f.querySelector<HTMLElement>('#external_field');
    const serviceLinkBlock = f.querySelector<HTMLElement>('#item_service_link_block');
    const classicLinkBlock = f.querySelector<HTMLElement>('#item_classic_link_block');
    const basicLabelIconFields = f.querySelector<HTMLElement>('#item_basic_label_icon_fields');
    const dividerLabelHint = f.querySelector<HTMLElement>('#divider_label_hint');
    const sectionCollapsibleRow = f.querySelector<HTMLElement>('#item_section_collapsible_row');

    const initialFromData = itemLinkFields?.dataset.initialItemType?.trim();
    const type = itemTypeField?.value ?? initialFromData ?? 'link';
    const isLink = type === 'link';
    const isService = type === 'service';
    const isSectionOrDivider = type === 'section' || type === 'divider';
    const isDivider = type === 'divider';
    const hasChildren = itemLinkFields?.getAttribute('data-item-has-children') === 'true';
    // Service items always show the link area (resolver + targetBlank) regardless of DB children.
    // Link items with children are dropdown parents (no leaf destination), so hide link area.
    const showLinkArea = isService || (isLink && !hasChildren);

    if (basicLabelIconFields) {
      basicLabelIconFields.style.display = '';
    }
    if (dividerLabelHint) {
      dividerLabelHint.style.display = isDivider ? '' : 'none';
    }
    if (itemLinkFields) {
      itemLinkFields.style.display = showLinkArea ? '' : 'none';
    }
    if (showLinkArea) {
      /*if (serviceLinkBlock) {
        serviceLinkBlock.style.display = isService ? '' : 'none';
      }*/
      if (classicLinkBlock) {
        classicLinkBlock.style.display = isService ? 'none' : '';
      }
      if (isService) {
        if (routeFields) routeFields.style.display = 'none';
      } else if (isLink && linkType && routeFields && externalField) {
        const isExternal = linkType.value === 'external';
        if (routeNameRow) routeNameRow.style.display = isExternal ? 'none' : '';
        routeFields.style.display = isExternal ? 'none' : '';
        externalField.style.display = isExternal ? '' : 'none';
      }
    }
    if (itemParentField) {
      itemParentField.style.display = isSectionOrDivider ? 'none' : '';
    }
    if (isSectionOrDivider && parentSelect) {
      parentSelect.value = '';
    }
    if (sectionCollapsibleRow) {
      sectionCollapsibleRow.style.display = type === 'section' ? '' : 'none';
    }
  }

  function suggestParamsForForm(f: HTMLFormElement, routes: AppRoutesMap): void {
    const routeNameSelect = f.querySelector<HTMLSelectElement>('[name*="[routeName]"]');
    const routeParamsInput = f.querySelector<HTMLInputElement | HTMLTextAreaElement>('[name*="[routeParams]"]');
    const routeParamsHint = f.querySelector<HTMLElement>('#route_params_hint');
    if (!routeParamsHint) return;
    const routeName = (routeNameSelect?.value ?? '').trim();
    if (!routeName || !routes[routeName]) {
      routeParamsHint.textContent = '';
      return;
    }
    const params = routes[routeName]?.params ?? [];
    if (params.length === 0) {
      routeParamsHint.textContent = '';
      return;
    }
    const suggested: Record<string, string> = {};
    for (const p of params) suggested[p] = '';
    const suggestedStr = JSON.stringify(suggested, null, 2);
    routeParamsHint.textContent = `Suggested: ${suggestedStr}`;
    if (routeParamsInput) {
      if (
        !routeParamsInput.value ||
        routeParamsInput.value === '{}' ||
        routeParamsInput.value.trim() === ''
      ) {
        routeParamsInput.value = suggestedStr;
      }
      routeParamsInput.placeholder = suggestedStr;
    }
  }

  container.addEventListener('change', (e) => {
    const target = e.target as HTMLElement;
    const f = target.closest('form');
    if (!f || !container.contains(f)) return;
    if (target.matches('[name*="[itemType]"]') || target.matches('[name*="[linkType]"]')) {
      updateToggles(f);
    } else if (target.matches('[name*="[routeName]"]')) {
      suggestParamsForForm(f, resolvedAppRoutes);
    }
  });

  updateToggles(form);
  suggestParamsForForm(form, resolvedAppRoutes);

  let moDebounce: ReturnType<typeof setTimeout> | null = null;
  const observer = new MutationObserver(() => {
    if (moDebounce) clearTimeout(moDebounce);
    moDebounce = setTimeout(() => {
      moDebounce = null;
      const f = container.matches('form') ? container : container.querySelector<HTMLFormElement>('form');
      if (f) {
        updateToggles(f);
        suggestParamsForForm(f, resolvedAppRoutes);
      }
    }, 100);
  });
  observer.observe(container, { childList: true, subtree: true });
}

function run(): void {
  const config = readDashboardMenuConfig<NowoDashboardMenuConfig>(
    typeof document !== 'undefined' ? document : undefined,
    window.__nowoDashboardMenuConfig,
  );
  if (!config) return;
  autocompleteBootstrapEnabled = config.autocompleteBootstrap !== false;
  if (typeof document !== 'undefined' && !window.__dmTomSelectModalPatchBound) {
    window.__dmTomSelectModalPatchBound = true;
    document.addEventListener('dashboard-menu:modal-content-loaded', (ev) => {
      const detail = (ev as CustomEvent<{ element: HTMLElement }>).detail;
      if (detail?.element) scheduleTomSelectModalPatches(detail.element);
    });
  }
  void ensureAutocompleteControllerRegistered();
  if (!window.__dmScriptLoaded) {
    log.scriptLoaded();
    window.__dmScriptLoaded = true;
  }
  log.setDebug(!!config.debug);
  if (config.debug) window.dashboardMenuDebugLive = true;
  else window.dashboardMenuDebugLive = false;
  configI18n = config.i18n ?? {};
  if (config.iconSelectorScriptUrl && typeof window !== 'undefined') {
    window.dashboardMenuIconSelectorScriptUrl ??= config.iconSelectorScriptUrl;
  }
  initDialogOpeners();
  if (config.baseUrl != null || config.menuId != null) {
    initShowPage();
  }
  if (config.itemFormPage) {
    const container = document.getElementById('modal-item-form-container');
    if (container) wireFormBody(container, '', false);
  } else if (config.appRoutes != null && Object.keys(config.appRoutes).length > 0) {
    // 1.x full item page (appRoutes in the config island).
    attachItemFormToggles(document.body, config.appRoutes as AppRoutesMap);
  }
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
}

export { connectStimulusControllersInContainer, reinitIconSelectorInContainer, attachLiveComponentDebug };
