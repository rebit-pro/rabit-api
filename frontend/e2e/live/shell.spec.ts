import { test, expect, type Page } from '@playwright/test';
import { login, logout } from './helpers.js';

// Design system U3: logo, grouped navigation, user menu with avatar initials, tab titles and service pages in the shell.
const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
});

async function noHorizontalScroll(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
}

async function userMenuAndProfile(page: Page, account: string) {
  await page.getByRole('button', { name: 'Меню пользователя', exact: true }).click();
  const menu = page.locator('.mf-user-menu__panel');
  await expect(menu).toContainText(account + '@example.invalid');
  await menu.getByRole('link', { name: 'Профиль', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  await expect(page).toHaveTitle('Профиль — Море фото');
}

const roles = [
  {
    account: 'organizer',
    visible: ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников', 'Каталог и цены', 'Сотрудники'],
    hidden: []
  },
  {
    account: 'curator',
    visible: ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников'],
    hidden: ['Каталог и цены', 'Сотрудники']
  },
  { account: 'head', visible: ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников'], hidden: ['Каталог и цены'] },
  { account: 'teacher', visible: ['Мои группы', 'Ссылки и сроки', 'Списки сотрудников'], hidden: ['Учреждения', 'Каталог и цены'] }
] as const;

for (const role of roles) {
  test(`каркас кабинета на 1280: ${role.account}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page, role.account);
    const banner = page.getByRole('banner');
    await expect(banner.getByRole('link', { name: 'Море фото' })).toBeVisible();
    expect(Math.round((await banner.boundingBox())!.height)).toBe(64);
    await expect(page).toHaveTitle(/ — Море фото$/);

    const navigation = page.getByLabel('Основная навигация');
    await expect(navigation.getByRole('region', { name: 'Работа' })).toBeVisible();
    for (const title of role.visible) await expect(navigation.getByRole('link', { name: title, exact: true })).toBeVisible();
    for (const title of role.hidden) await expect(navigation.getByRole('link', { name: title, exact: true })).toHaveCount(0);
    await expect(navigation.getByRole('region', { name: 'Настройки' })).toHaveCount('organizer' === role.account ? 1 : 0);
    await expect(page.getByRole('link', { name: /^Профиль: / })).toBeVisible();

    await userMenuAndProfile(page, role.account);
    await page.goto('/missing-page');
    await expect(page.getByRole('heading', { name: 'Страница не найдена', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Меню пользователя', exact: true })).toBeVisible();
    await noHorizontalScroll(page);
    await logout(page);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.getByRole('heading', { name: 'Вход в кабинет', exact: true })).toBeVisible();
  });
}

for (const width of [390, 768, 1440]) {
  test(`каркас кабинета организатора на ${width}`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await login(page, 'organizer');
    const banner = page.getByRole('banner');
    await expect(banner.getByRole('link', { name: 'Море фото' })).toBeVisible();
    const drawerToggle = page.getByRole('button', { name: 'Открыть меню', exact: true });
    if (width < 960) {
      expect(Math.round((await banner.boundingBox())!.height)).toBe(56);
      await expect(banner).toContainText('Каталог и цены');
      await drawerToggle.click();
    } else {
      await expect(drawerToggle).toHaveCount(0);
    }
    const navigation = page.getByLabel('Основная навигация');
    await expect(navigation.getByRole('link', { name: 'Сотрудники', exact: true })).toBeVisible();
    await navigation.getByRole('link', { name: 'Сотрудники', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Сотрудники', exact: true })).toBeVisible();
    await expect(page).toHaveTitle('Сотрудники — Море фото');
    await noHorizontalScroll(page);
    await userMenuAndProfile(page, 'organizer');
    await noHorizontalScroll(page);
  });
}
