/**
 * Bundled UX Autocomplete controller (2.25.0): `@symfony/ux-autocomplete`, Tom Select and Stimulus are built into
 * `js/autocomplete.js` (versions pinned in package.json, licenses in THIRD_PARTY.md). dashboard.js imports this module
 * lazily from the bundle's own asset path (config island `autocompleteScriptUrl`); nothing is fetched from a CDN.
 */
import AutocompleteController from '@symfony/ux-autocomplete';
import { createBundleLogger } from './logger';

declare const __DASHBOARD_MENU_BUILD_TIME__: string;

createBundleLogger('dashboard-menu/autocomplete', {
  buildTime: typeof __DASHBOARD_MENU_BUILD_TIME__ !== 'undefined' ? __DASHBOARD_MENU_BUILD_TIME__ : undefined,
}).scriptLoaded();

export default AutocompleteController;
