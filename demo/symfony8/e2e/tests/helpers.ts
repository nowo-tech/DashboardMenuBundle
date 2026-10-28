import { expect, type Locator, type Page } from '@playwright/test';

/** Opens the "User menu" (code dropdown) detail page from the menus list (ids change on every fixture load). */
export async function openUserMenu(page: Page): Promise<Locator> {
  await page.goto('/admin/menus');
  await page.locator('tr', { hasText: 'User menu' }).first().getByRole('link', { name: 'Edit items' }).click();
  await expect(page).toHaveURL(/\/admin\/menus\/\d+$/);
  const items = page.getByTestId('dashboard-menu-items');
  await expect(items).toBeVisible();
  return items;
}

/** Opens the label/translations dialog of the "Settings" item. */
export async function openSettingsItemDialog(page: Page): Promise<Locator> {
  const items = await openUserMenu(page);
  await items.locator('tr', { hasText: 'Settings' }).first().locator('.nowo-ui-action--edit').click();
  const dialog = page.locator('dialog[open]').filter({ hasText: 'Edit label and translations' });
  await expect(dialog).toBeVisible({ timeout: 15_000 });
  await expect(dialog.locator('input[type="text"]').first()).toHaveValue('Settings');
  return dialog;
}

/** Waits for the Symfony WebProfiler toolbar so it is part of the capture. */
export async function waitForToolbar(page: Page): Promise<void> {
  await expect(page.locator('.sf-toolbar .sf-toolbarreset').first()).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(300);
}
