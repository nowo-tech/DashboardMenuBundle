import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { openSettingsItemDialog, openUserMenu, waitForToolbar } from './helpers';

/**
 * REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs).
 * Viewport captures: demo masthead, sidebar/aside menus rendered by the bundle, dashboard UI and the WebProfiler toolbar.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.use({ viewport: { width: 1440, height: 1000 } });

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('Dashboard Menu screenshots', () => {
  test('dashboard-menus-list — menus list with row actions', async ({ page }) => {
    // First hit warms the cache (keeps the profiler toolbar on one row).
    await page.goto('/admin/menus');
    await page.goto('/admin/menus');
    await expect(page.locator('table').first().getByText('User menu').first()).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'dashboard-menus-list.png') });
  });

  test('dashboard-menu-items — menu detail with its items table', async ({ page }) => {
    const items = await openUserMenu(page);
    await expect(items.locator('tr', { hasText: 'Logout' }).first()).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'dashboard-menu-items.png') });
  });

  test('dashboard-menu-item-dialog — item label translations in a dialog', async ({ page }) => {
    const dialog = await openSettingsItemDialog(page);
    await dialog.getByText('ES', { exact: true }).first().click();
    const esLabel = dialog.getByLabel('Label (es)');
    await expect(esLabel).toBeVisible();
    await esLabel.fill('Ajustes');
    await esLabel.blur();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'dashboard-menu-item-dialog.png') });
  });
});
