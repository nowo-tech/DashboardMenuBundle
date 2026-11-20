import { test, expect, type Page } from '@playwright/test';
import { openSettingsItemDialog } from './helpers';

/** Every request a page makes, as absolute URLs (data: and blob: are not network requests). */
function recordRequests(page: Page): string[] {
  const urls: string[] = [];
  page.on('request', (request) => {
    const url = request.url();
    if (!/^(?:data|blob|about):/.test(url)) {
      urls.push(url);
    }
  });
  return urls;
}

/**
 * Dev-only tooling of the demo itself (the FrankenPHP hot-reload helper of the dev environment, see
 * config/packages/dev/nowo_hot_reload.yaml): not part of the bundle, absent from every production page.
 */
const DEMO_DEV_TOOLING = [/^https:\/\/cdn\.jsdelivr\.net\/npm\/idiomorph@[\d.]+$/, /^https:\/\/cdn\.jsdelivr\.net\/npm\/frankenphp-hot-reload@[\d.]+\/\+esm$/];

/** The dashboard loads its autocomplete and Live scripts from the bundle's own assets: no request leaves the demo origin. */
test.describe('No external origin', () => {
  test('menus list, menu detail and the item dialog only talk to the demo origin', async ({ page, baseURL }) => {
    const origin = new URL(baseURL as string).origin;
    const urls = recordRequests(page);

    await page.goto('/admin/menus');
    await openSettingsItemDialog(page);
    // The Live Component dialog connects the autocomplete controller: wait until the bundled module was fetched.
    await expect.poll(() => urls.some((u) => u.includes('/bundles/nowodashboardmenu/js/autocomplete.js'))).toBeTruthy();
    await page.waitForLoadState('networkidle');

    const foreign = urls.filter((u) => new URL(u).origin !== origin && !DEMO_DEV_TOOLING.some((re) => re.test(u)));
    expect(foreign, `requests to another origin: ${foreign.join(', ')}`).toEqual([]);
    expect(urls.some((u) => u.includes('/bundles/nowodashboardmenu/js/stimulus-live.js'))).toBeTruthy();
    expect(urls.some((u) => /esm\.sh/.test(u))).toBeFalsy();
  });

  test('the bundled scripts inject no <style> and name no CDN', async ({ page, request }) => {
    await page.goto('/admin/menus');
    for (const file of ['autocomplete', 'stimulus-live', 'dashboard']) {
      const body = await (await request.get(`/bundles/nowodashboardmenu/js/${file}.js`)).text();
      expect(body, `${file}.js`).not.toMatch(/createElement\(["']style["']\)|insertRule|adoptedStyleSheets|esm\.sh|jsdelivr|unpkg\.com/);
    }
    // Evaluating a fresh instance of the autocomplete module must not add a <style> element to the document.
    const added = await page.evaluate(async () => {
      const count = () => document.querySelectorAll('style').length;
      const before = count();
      const mod = await import(`/bundles/nowodashboardmenu/js/autocomplete.js?probe=${Date.now()}`);
      return { exported: typeof mod.default, delta: count() - before };
    });
    expect(added).toEqual({ exported: 'function', delta: 0 });
  });
});
