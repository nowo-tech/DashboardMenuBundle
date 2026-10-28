import { test, expect } from '@playwright/test';
import { openSettingsItemDialog, openUserMenu } from './helpers';

/** REQ-DEMO-013 — Dashboard Menu demo happy path (menus list → menu items → item dialog). */
test.describe('Dashboard Menu demo', () => {
  test('menus list shows the fixture menus and the bundle-rendered sidebar', async ({ page }) => {
    const response = await page.goto('/admin/menus');
    expect(response?.ok()).toBeTruthy();
    const table = page.locator('table').first();
    for (const name of ['Sidebar', 'User menu', 'Footer', 'Language']) {
      await expect(table.getByText(name, { exact: true }).first()).toBeVisible();
    }
    await expect(page.getByRole('link', { name: 'Manage menus' }).first()).toBeVisible();
  });

  test('menu detail lists its items with routes and actions', async ({ page }) => {
    const items = await openUserMenu(page);
    for (const label of ['Settings', 'Help', 'Logout']) {
      await expect(items.locator('tr', { hasText: label }).first()).toBeVisible();
    }
    await expect(items.getByText('app_configuration').first()).toBeVisible();
  });

  test('item labels open in a dialog with one tab per locale', async ({ page }) => {
    const dialog = await openSettingsItemDialog(page);
    for (const locale of ['ES', 'FR']) {
      await expect(dialog.getByText(locale, { exact: true }).first()).toBeVisible();
    }
    await dialog.getByText('ES', { exact: true }).first().click();
    await expect(dialog.getByText('Label (es)')).toBeVisible();
  });
});
