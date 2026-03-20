/**
 * Dashboard menu bundle: single entry for all dashboard view scripts.
 * Reads config from window.__nowoDashboardMenuConfig (set by Twig) and initializes modals, Stimulus connection, and form toggles.
 */
import { createBundleLogger } from './logger';

declare const __DASHBOARD_MENU_BUILD_TIME__: string | undefined;

const log = createBundleLogger('dashboard-menu', {
  buildTime: typeof __DASHBOARD_MENU_BUILD_TIME__ !== 'undefined' ? __DASHBOARD_MENU_BUILD_TIME__ : undefined,
});

declare global {
  interface Window {
    __nowoDashboardMenuConfig?: NowoDashboardMenuConfig;
    __dmScriptLoaded?: boolean;
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
  router?: {
    elementConnected?(el: Element): void;
    proposeToConnectScopeForElementAndIdentifier?(element: Element, identifier: string): void;
  };
}

export interface NowoDashboardMenuConfig {
  baseUrl?: string;
  menuId?: number;
  dashboardBase?: string;
  appRoutes?: Record<string, { params?: string[] }>;
  debug?: boolean;
}

const i18n = (key: string, fallback: string) =>
  (typeof window !== 'undefined' && window.dashboardMenuI18n?.[key]) ?? fallback;

function connectStimulusControllersInContainer(container: Element | null): void {
  if (!container) return;
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

function reinitIconSelectorInContainer(container: Element): void {
  const url =
    window.dashboardMenuIconSelectorScriptUrl ??
    ((container.getAttribute('data-icon-selector-script-url') ?? '').trim() ||
      (document.querySelector('script[src*="icon-selector"], script[src*="icon_selector"]') as HTMLScriptElement)?.src);
  if (!url) return;
  const script = document.createElement('script');
  script.src = `${url}${url.includes('?') ? '&' : '?'}_=${Date.now()}`;
  script.async = false;
  script.onload = () => {
    connectStimulusControllersInContainer(container);
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

function initShowPage(config: NowoDashboardMenuConfig): void {
  const menuId = config.menuId ?? 0;
  const baseUrl = (config.baseUrl ?? '').replace(/\/[^/]+$/, '');
  const loading = i18n('loading', 'Loading…');
  const errorMsg = i18n('errorLoadingForm', 'Error loading form.');
  const editItemLabel = i18n('editItem', 'Edit item');
  const addItemLabel = i18n('addItem', 'Add item');
  const deleteItemConfirmTpl = i18n('deleteItemConfirm', 'Delete this item and its children?');

  const editMenuLabel = i18n('editMenu', 'Edit menu');
  const editConfigLabel = i18n('editConfig', 'Edit configuration');
  const modalMenuForm = document.getElementById('modal-menu-form');
  if (modalMenuForm) {
    modalMenuForm.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-edit-menu')) return;
      const body = document.getElementById('modal-menu-form-body');
      const title = document.getElementById('modal-menu-form-label');
      const id = btn.getAttribute('data-id') ?? String(menuId);
      const section = btn.getAttribute('data-section') ?? '';
      if (title) {
        title.textContent = section === 'config' ? editConfigLabel : editMenuLabel;
      }
      if (body) {
        body.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
        let url = `${baseUrl}/${id}/edit?_partial=1`;
        if (section) url += `&section=${encodeURIComponent(section)}`;
        fetch(url)
          .then((r) => r.text())
          .then((html) => {
            body.innerHTML = html;
            if (section === 'config') {
              const configEl = document.getElementById('menu_config_section');
              if (configEl) configEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else if (section === 'basic') {
              const defEl = document.getElementById('menu_definition_section');
              if (defEl) defEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          })
          .catch(() => {
            body.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
          });
      }
    });
  }

  const copyMenuModal = document.getElementById('modal-menu-copy');
  if (copyMenuModal) {
    copyMenuModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-copy-menu')) return;
      const copyUrl = btn.getAttribute('data-copy-url');
      const body = document.getElementById('modal-menu-copy-body');
      if (!body || !copyUrl) return;
      body.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
      fetch(copyUrl)
        .then((r) => r.text())
        .then((html) => { body.innerHTML = html; })
        .catch(() => {
          body.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
        });
    });
  }

  const modalItemForm = document.getElementById('modal-item-form');
  if (modalItemForm) {
    modalItemForm.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      const isAdd = btn?.classList.contains('btn-add-item');
      const isEdit = btn?.classList.contains('btn-edit-item') ?? false;
      if (!isAdd && !isEdit) return;
      const body = document.getElementById('modal-item-form-body');
      const title = document.getElementById('modal-item-form-label');
      const mid = btn?.getAttribute('data-id') ?? String(menuId);
      const itemId = btn?.getAttribute('data-item-id');
      const parent = btn?.getAttribute('data-parent') ?? '';
      const section = btn?.getAttribute('data-section') ?? '';
      if (title) {
        if (isAdd) title.textContent = addItemLabel;
        else if (section === 'config') title.textContent = editConfigLabel;
        else title.textContent = editItemLabel;
      }
      if (body) {
        body.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
        let url: string;
        if (isEdit) {
          url = `${baseUrl}/${mid}/item/${itemId}/edit?_partial=1`;
          if (section) url += `&section=${encodeURIComponent(section)}`;
        } else {
          url = `${baseUrl}/${mid}/item/new?_partial=1${parent ? `&parent=${encodeURIComponent(parent)}` : ''}`;
        }
        fetch(url)
          .then((r) => r.text())
          .then((html) => {
            body.innerHTML = html;
            const container = document.getElementById('modal-item-form-container');
            if (container) {
              reinitIconSelectorInContainer(container);
              connectStimulusControllersInContainer(container);
              attachItemFormToggles(container, {});
              if (section === 'config') {
                const configEl = container.querySelector('#item_config_section');
                if (configEl) {
                  configEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
              } else if (section === 'basic') {
                const basicEl = container.querySelector('#item_basic_section');
                if (basicEl) {
                  basicEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
              }
              document.dispatchEvent(
                new CustomEvent('dashboard-menu:modal-content-loaded', { detail: { element: container } })
              );
              if (window.dashboardMenuDebugLive) {
                attachLiveComponentDebug(container);
              }
            }
          })
          .catch(() => {
            body.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
          });
      }
    });
  }

  const deleteItemModal = document.getElementById('modal-delete-item-confirm');
  if (deleteItemModal) {
    deleteItemModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-delete-item')) return;
      const form = document.getElementById('form-delete-item-confirm');
      const msg = document.getElementById('modal-delete-item-message');
      if (form && msg) {
        (form as HTMLFormElement).action = btn.getAttribute('data-url') ?? '';
        const tokenInput = form.querySelector<HTMLInputElement>('input[name="_token"]');
        if (tokenInput) tokenInput.value = btn.getAttribute('data-token') ?? '';
        msg.textContent = deleteItemConfirmTpl.replace(
          '%name%',
          btn.getAttribute('data-name') ?? ''
        );
      }
    });
  }
}

function initIndexPage(config: NowoDashboardMenuConfig): void {
  const dashboardBase = (config.dashboardBase ?? '').replace(/\/$/, '');
  const loading = i18n('loading', 'Loading…');
  const errorMsg = i18n('errorLoadingForm', 'Error loading form.');
  const deleteMenuConfirmTpl = i18n('deleteMenuConfirm', 'Delete this menu and all its items?');

  const editMenuLabel = i18n('editMenu', 'Edit menu');
  const editConfigLabel = i18n('editConfig', 'Edit configuration');
  const editMenuModal = document.getElementById('modal-menu-form');
  if (editMenuModal) {
    editMenuModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-edit-menu')) return;
      const id = btn.getAttribute('data-id');
      const body = document.getElementById('modal-menu-form-body');
      const title = document.getElementById('modal-menu-form-label');
      if (!body || !id) return;
      const section = btn.getAttribute('data-section') ?? '';
      if (title) {
        title.textContent = section === 'config' ? editConfigLabel : editMenuLabel;
      }
      body.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
      let url = `${dashboardBase}/${id}/edit?_partial=1`;
      if (section) url += `&section=${encodeURIComponent(section)}`;
      fetch(url)
        .then((r) => r.text())
        .then((html) => {
          body.innerHTML = html;
          if (section === 'config') {
            const configEl = document.getElementById('menu_config_section');
            if (configEl) configEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          } else if (section === 'basic') {
            const defEl = document.getElementById('menu_definition_section');
            if (defEl) defEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        })
        .catch(() => {
          body.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
        });
    });
  }

  const copyMenuModal = document.getElementById('modal-menu-copy');
  if (copyMenuModal) {
    copyMenuModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-copy-menu')) return;
      const copyUrl = btn.getAttribute('data-copy-url');
      const body = document.getElementById('modal-menu-copy-body');
      if (!body || !copyUrl) return;
      body.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
      fetch(copyUrl)
        .then((r) => r.text())
        .then((html) => { body.innerHTML = html; })
        .catch(() => {
          body.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
        });
    });
  }

  const importModal = document.getElementById('modal-import');
  const importModalBody = document.getElementById('modal-import-body');
  if (importModal && importModalBody) {
    importModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      const importUrl = btn?.getAttribute('data-import-url') ?? `${dashboardBase}/import?_partial=1`;
      importModalBody.innerHTML = `<div class="text-center py-4 text-muted">${loading}</div>`;
      fetch(importUrl)
        .then((r) => r.text())
        .then((html) => { importModalBody.innerHTML = html; })
        .catch(() => {
          importModalBody.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
        });
    });
    importModalBody.addEventListener('submit', (e) => {
      const form = (e.target as HTMLElement).closest('form[data-import-form], form.import-form');
      if (!form || !importModalBody.contains(form as Node)) return;
      e.preventDefault();
      const formEl = form as HTMLFormElement;
      const action = formEl.action || `${dashboardBase}/import`;
      const formData = new FormData(formEl);
      fetch(action, { method: 'POST', body: formData, redirect: 'follow' })
        .then((r) => {
          if (r.redirected) {
            window.location.href = r.url || dashboardBase;
            return;
          }
          return r.text();
        })
        .then((html) => {
          if (html != null) importModalBody.innerHTML = html;
        })
        .catch(() => {
          importModalBody.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
        });
    }, true);
  }

  const deleteModal = document.getElementById('modal-delete-confirm');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (e) => {
      const btn = (e as Event & { relatedTarget: HTMLElement | null }).relatedTarget;
      if (!btn?.classList.contains('btn-delete-menu')) return;
      const form = document.getElementById('form-delete-confirm');
      const msg = document.getElementById('modal-delete-message');
      if (form && msg) {
        (form as HTMLFormElement).action = btn.getAttribute('data-url') ?? '';
        const tokenInput = form.querySelector<HTMLInputElement>('input[name="_token"]');
        if (tokenInput) tokenInput.value = btn.getAttribute('data-token') ?? '';
        msg.textContent = deleteMenuConfirmTpl.replace(
          '%name%',
          btn.getAttribute('data-name') ?? ''
        );
      }
    });
  }
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
    const routeFields = f.querySelector<HTMLElement>('#route_fields');
    const externalField = f.querySelector<HTMLElement>('#external_field');
    const basicLabelIconFields = f.querySelector<HTMLElement>('#item_basic_label_icon_fields');

    const type = itemTypeField?.value ?? 'link';
    const isLink = type === 'link';
    const isSectionOrDivider = type === 'section' || type === 'divider';
    const isDivider = type === 'divider';
    const hasChildren = itemLinkFields?.getAttribute('data-item-has-children') === 'true';

    if (basicLabelIconFields) {
      basicLabelIconFields.style.display = isDivider ? 'none' : 'block';
    }
    if (itemLinkFields) {
      itemLinkFields.style.display = isLink && !hasChildren ? 'block' : 'none';
    }
    if (isLink && !hasChildren && linkType && routeFields && externalField) {
      const isExternal = linkType.value === 'external';
      routeFields.style.display = isExternal ? 'none' : 'block';
      externalField.style.display = isExternal ? 'block' : 'none';
    }
    if (itemParentField) {
      itemParentField.style.display = isSectionOrDivider ? 'none' : 'block';
    }
    if (isSectionOrDivider && parentSelect) {
      parentSelect.value = '';
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

function initItemFormPage(config: NowoDashboardMenuConfig): void {
  const appRoutes = (config.appRoutes ?? {}) as AppRoutesMap;
  attachItemFormToggles(document.body, appRoutes);
}

function run(): void {
  const config = window.__nowoDashboardMenuConfig;
  if (!config) return;
  if (!window.__dmScriptLoaded) {
    log.scriptLoaded();
    window.__dmScriptLoaded = true;
  }
  log.setDebug(!!config.debug);
  if (config.debug) window.dashboardMenuDebugLive = true;
  else window.dashboardMenuDebugLive = false;
  if (config.baseUrl != null || config.menuId != null) {
    initShowPage(config);
  }
  if (config.dashboardBase != null) {
    initIndexPage(config);
  }
  if (config.appRoutes != null && Object.keys(config.appRoutes).length > 0) {
    initItemFormPage(config);
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
