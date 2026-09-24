import { test, expect, type Page } from '@playwright/test';
import { login, logout, token } from './helpers.js';

// U6: the live overview and the tiles above lists show exactly the server summaries of U5. The spec runs after the
// handoff, order and transfer specs of its group, so the tiles and the queue show real groups.
async function get(page: Page, path: string) {
  const response = await page.request.get(path, { headers: { Authorization: 'Bearer ' + (await token(page)) } });
  expect(response.status(), path).toBe(200);
  return response.json();
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

test('U6: обзор организатора — плитки ссылок по сводке, очередь, распределение и финансы без цифр', async ({ page }) => {
  await login(page);
  const summary = (await get(page, '/api/v1/group-links?state=preparing&pageSize=5')).meta.summary;
  await page.goto('/cabinet/overview');

  const tiles = page.getByTestId('overview-links').getByTestId('stat-tile');
  await expect(tiles).toHaveCount(4);
  const expected = [
    ['Ждут проверки', Math.max(0, summary.byState.preparing - summary.prepared)],
    ['Готовы к передаче', summary.prepared],
    ['Приём открыт', summary.byState.open],
    ['Закрываются за 3 дня', summary.closingSoon]
  ] as const;
  for (const [index, [label, value]] of expected.entries()) {
    await expect(tiles.nth(index)).toHaveAttribute('aria-label', new RegExp('^' + label + ': ' + value + ' '));
  }
  await expect(page.getByTestId('queue')).toBeVisible();
  const distribution = page.getByTestId('distribution');
  await expect(distribution.locator('table caption')).toContainText('Группы по состоянию приёма — из ');
  const finance = page.getByRole('region', { name: 'Финансы' }).getByTestId('stat-tile');
  await expect(finance).toHaveCount(3);
  for (const tile of await finance.all()) {
    await expect(tile).toContainText('Недоступно до подключения оплаты');
    await expect(tile).not.toContainText(/\d/);
  }

  const waiting = expected[0][1];
  const links = page.getByLabel('Основная навигация').getByRole('link', { name: 'Ссылки и сроки', exact: true });
  if (waiting > 0) await expect(links.locator('.mf-navigation-count')).toHaveText(String(waiting));

  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.reload();
  const durations = await tiles.first().evaluate((element) => getComputedStyle(element).transitionDuration);
  expect(durations.split(',').every((part) => 0 === parseFloat(part))).toBe(true);
});

test('U6: учитель видит карточки своих групп со сроками', async ({ page }) => {
  await login(page, 'teacher');
  const links = await get(page, '/api/v1/group-links?pageSize=100');
  await page.goto('/cabinet/overview');
  await expect(page).toHaveTitle(/^Обзор — Море фото$/);
  const cards = page.getByTestId('overview-group');
  await expect(cards).toHaveCount(links.data.items.length);
  for (const card of await cards.all()) {
    await expect(card.getByTestId('countdown')).toHaveText(/Ссылка ещё не передана родителям|осталось|остался|просрочено|приём закрыт/);
  }
  // INF-11: a card names the curator of the group's institution when one is assigned.
  const curators = (links.data.items as { curatorName?: string | null }[]).filter((item) => item.curatorName).length;
  await expect(page.getByTestId('overview-curator')).toHaveCount(curators);
});

test('U6: плитки над сотрудниками и заказами совпадают со сводками и фильтруют список', async ({ page }) => {
  await login(page);
  const staff = (await get(page, '/api/v1/users?pageSize=1')).meta.summary.byAccountStatus;
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Сотрудники', exact: true }).click();
  const tiles = page.getByTestId('staff-tiles').getByTestId('stat-tile');
  await expect(tiles.nth(0)).toHaveAttribute('aria-label', new RegExp('^Ожидает регистрации: ' + staff.pending + ' '));
  await expect(tiles.nth(1)).toHaveAttribute('aria-label', new RegExp('^Активен: ' + staff.active + ' '));
  const filtered = page.waitForResponse((r) => r.url().includes('/api/v1/users?') && r.url().includes('accountStatus=pending'));
  await tiles.nth(0).click();
  expect((await filtered).status()).toBe(200);
  await expect(tiles.nth(0)).toHaveAttribute('aria-pressed', 'true');

  const orders = (await get(page, '/api/v1/orders?pageSize=1')).meta.summary;
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Заказы', exact: true }).click();
  await expect(page.getByTestId('order-summary').getByTestId('stat-tile')).toHaveAttribute(
    'aria-label',
    new RegExp('^Заказов найдено: ' + orders.total + ' ')
  );
  await logout(page);
});
