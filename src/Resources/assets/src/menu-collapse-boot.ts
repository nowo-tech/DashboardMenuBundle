/**
 * Entry of `public/js/menu-collapse.js` (IIFE): connects `[data-dashboard-menu-collapse]` roots on DOM ready
 * and after Turbo visits. Logic and tests live in `menu-collapse.ts`.
 */
import { autoInit } from './menu-collapse';

autoInit(document, window);
