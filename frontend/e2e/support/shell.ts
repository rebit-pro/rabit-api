import type { Page } from '@playwright/test';

/** Opens the user menu of the cabinet shell (avatar button in the app bar). */
export async function openUserMenu(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Меню пользователя', exact: true }).click();
}

/** Signs out through the user menu; the shell has no standalone logout button. */
export async function signOut(page: Page): Promise<void> {
  await openUserMenu(page);
  await page.getByRole('button', { name: 'Выйти', exact: true }).click();
}
