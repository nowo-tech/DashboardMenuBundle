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

  /** Lazy dialogs: one shared Delete confirm for the whole items table, and form dialogs fetched with X-Nowo-Dialog. */
  test('item rows share one delete confirm and the item dialog is fetched on open', async ({ page }) => {
    const items = await openUserMenu(page);
    await expect(page.locator('dialog[data-nowo-confirm-shared]')).toHaveCount(1);
    await expect(items.locator('table dialog')).toHaveCount(0);
    const row = items.locator('tr', { hasText: 'Help' }).first();
    await row.locator('.nowo-ui-action--delete').click();
    const confirm = page.locator('dialog[open][data-nowo-confirm-shared]');
    await expect(confirm).toBeVisible();
    await expect(confirm).toContainText('Help');
    await confirm.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await expect(items.locator('tr', { hasText: 'Help' }).first()).toBeVisible();

    const fragment = page.waitForRequest((request) => request.headers()['x-nowo-dialog'] === '1');
    await items.locator('tr', { hasText: 'Settings' }).first().locator('.nowo-ui-action--edit').click();
    const request = await fragment;
    expect(new URL(request.url()).searchParams.has('_modal')).toBe(false);
    await expect(page.locator('dialog[open]').filter({ hasText: 'Edit label and translations' })).toBeVisible({ timeout: 15_000 });
  });

  /** 2.26 menu_layout: collapsible sections (remembered per person) and the footer slot of the sidebar example. */
  test('sidebar sections collapse, remember the choice, and the footer slot has its own landmark', async ({ page }) => {
    await page.goto('/?example=sidebar');
    const menu = page.getByTestId('demo-menu-sidebar');
    const resources = menu.getByRole('button', { name: 'Resources' });
    const external = menu.getByRole('button', { name: 'External' });

    // Configured defaults: Resources collapsed, External open.
    await expect(resources).toHaveAttribute('aria-expanded', 'false');
    await expect(external).toHaveAttribute('aria-expanded', 'true');
    const resourcesPanel = page.locator(`#${await resources.getAttribute('aria-controls')}`);
    await expect(resourcesPanel).toBeHidden();

    // Keyboard: Space toggles the native button.
    await resources.focus();
    await page.keyboard.press('Space');
    await expect(resources).toHaveAttribute('aria-expanded', 'true');
    await expect(resourcesPanel).toBeVisible();
    await external.click();
    await expect(external).toHaveAttribute('aria-expanded', 'false');

    // The choice survives a reload and wins over the configured default.
    await page.reload();
    await expect(menu.getByRole('button', { name: 'Resources' })).toHaveAttribute('aria-expanded', 'true');
    await expect(menu.getByRole('button', { name: 'External' })).toHaveAttribute('aria-expanded', 'false');

    // Footer slot: a separate <nav>, outside the main menu, holding the "Footer links" items.
    const footer = page.locator('nav.dashboard-menu-footer');
    await expect(footer).toHaveCount(1);
    await expect(footer).toHaveAttribute('aria-label', 'Menu footer');
    await expect(footer.getByRole('link', { name: 'Support' })).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Support' })).toHaveCount(0);
    await expect(menu.locator('nav.dashboard-menu-footer')).toHaveCount(0);
  });
});
