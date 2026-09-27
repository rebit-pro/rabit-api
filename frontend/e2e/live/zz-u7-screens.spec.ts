import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

// U7: working screens of the structure — counters of the list, breadcrumbs, tabs of a shoot, the split of groups,
// the readiness of photos and the organizer's contact in the profile.
async function headers(page: Page) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') };
}
async function created(response: APIResponse): Promise<{ id: string }> {
  expect(response.status(), await response.text()).toBe(201);
  return (await response.json()).data;
}
async function create(page: Page, path: string, data: Record<string, unknown>) {
  return created(await page.request.post(path, { headers: await headers(page), data }));
}

const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
});

test('U7: учреждение и съёмка — счётчики, хлебные крошки, вкладки, распределение групп и готовность фото', async ({ page }) => {
  await login(page);
  const institution = await create(page, '/api/v1/institutions', { name: 'U7 Учреждение', address: 'Москва, Тестовая улица, 7' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'U7 Съёмка', date: '2026-10-20' });
  await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'U7 Группа', groupKind: 'regular' });

  await page.goto('/cabinet/institutions');
  // The search runs by itself after typing: no button press.
  const search = page.waitForResponse((r) => r.url().includes('/api/v1/institutions?') && r.url().includes('q=U7'));
  await page.getByLabel('Поиск учреждений', { exact: true }).fill('U7 Учр');
  expect((await search).status()).toBe(200);
  const row = page.getByTestId('structure-row').filter({ hasText: 'U7 Учреждение' });
  await expect(row).toContainText('1 съёмка · 1 группа');
  await row.getByRole('link', { name: 'U7 Учреждение', exact: true }).click();

  const crumbs = page.getByRole('navigation', { name: 'Хлебные крошки' });
  await expect(crumbs.getByRole('link', { name: 'Учреждения', exact: true })).toBeVisible();
  await expect(crumbs.locator('[aria-current="page"]')).toHaveText('U7 Учреждение');
  await expect(page.getByTestId('institution-curator')).toContainText('Не назначен');
  // #92: the split of groups by state became tiles next to the details of the institution.
  await expect(page.getByTestId('institution-overview')).toContainText('Группы готовятся');
  await expect(page.getByTestId('institution-overview')).toContainText('из 1');

  await page.getByTestId('institution-shoots').getByRole('link', { name: 'U7 Съёмка', exact: true }).click();
  const tabs = page.getByRole('navigation', { name: 'Разделы съёмки' });
  await expect(tabs.getByRole('link', { name: 'Группы', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(crumbs.getByRole('link', { name: 'U7 Учреждение', exact: true })).toBeVisible();
  await expect(page.getByTestId('distribution').locator('table caption')).toContainText('из 1 группы');
  await expect(page.getByTestId('structure-row')).toContainText('Ответственный группы не назначен');

  await tabs.getByRole('link', { name: 'Условия', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/shoots/' + shoot.id + '/conditions'));
  await expect(page.getByRole('heading', { name: 'Условия групп', exact: true })).toBeVisible();
  await expect(tabs.getByRole('link', { name: 'Условия', exact: true })).toHaveAttribute('aria-current', 'page');

  await tabs.getByRole('link', { name: 'Фотографии', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/shoots/' + shoot.id + '/photos'));
  await expect(page.getByTestId('photo-stats').getByRole('progressbar', { name: 'Обработано' })).toHaveAttribute(
    'aria-valuetext',
    'Обработано 0 из 0 (0 %)'
  );
  await expect(page.getByText('Назначьте кадры ребёнку, чтобы включить предпросмотр.', { exact: true })).toBeVisible();
  await expect(page.getByTestId('upload-drop')).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  // The shell switches to the mobile drawer on a fresh render; a resized desktop page is not what a phone shows.
  await page.reload();
  await expect(crumbs.getByRole('link', { name: 'U7 Съёмка', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('U7: раздел «Помощь» профиля показывает контакт организатора', async ({ page }) => {
  await login(page, 'teacher');
  const help = page.getByRole('region', { name: 'Помощь' });
  await expect(help).toContainText('Организатор E2E');
  await expect(help.getByRole('link', { name: 'support@example.invalid', exact: true })).toHaveAttribute(
    'href',
    'mailto:support@example.invalid'
  );
  await expect(help.getByRole('link', { name: '+7 900 000-00-00', exact: true })).toHaveAttribute('href', 'tel:+79000000000');
});
