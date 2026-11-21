/**
 * Entry of `public/js/menu-collapse.js` (IIFE): connects `[data-dashboard-menu-collapse]` roots on DOM ready
 * and after Turbo visits. Logic and tests live in `menu-collapse.ts` and `section-collapse.ts`.
 */
import { autoInit } from './menu-collapse';
import { autoInitSections } from './section-collapse';

autoInit(document, window);
// 2.26.0: per-section collapse of menu_layout.<code>.sections (inert when the page has no such section).
autoInitSections(document, window);
