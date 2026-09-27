import { test, expect, type Locator, type Page } from '@playwright/test';
import { login, token } from './helpers.js';
import { auth, body, command, link } from './f2-links.js';

// #92: compact tables of the catalogue, the institution page and the links, with bulk actions and removal.
// Every test makes its own records with a unique suffix and removes what it leaves behind: group B continues on this stand.
const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
const problems = new WeakMap<Page, string[]>();
const suffix = () => crypto.randomUUID().slice(0, 8);
/** Visible rows of a table: the desktop row and the mobile card of one record share its ID. */
const rows = (scope: Page | Locator) => scope.locator('[data-row-id]').filter({ visible: true });
const rowOf = (scope: Page | Locator, name: string) => rows(scope).filter({ hasText: name });

async function create(page: Page, path: string, data: Record<string, unknown>): Promise<{ id: string; revision: number }> {
  return (await body(await page.request.post(path, { headers: await auth(page), data }), 201)).data;
}
async function product(page: Page, name: string): Promise<string> {
  return (
    await create(page, '/api/v1/catalog/products', {
      name,
      description: '',
      kind: 'physical',
      price: 15000,
      printCount: 1,
      format: '10×15',
      unit: 'шт.',
      staffDiscount: false,
      active: true
    })
  ).id;
}
async function catalog(page: Page): Promise<{ id: string; name: string; active: boolean }[]> {
  return (await body(await page.request.get('/api/v1/catalog/products?pageSize=100', { headers: await auth(page) }))).data.items;
}
/** Leaves no record for the next files of the group; an already removed one answers 404. */
async function remove(page: Page, path: string): Promise<void> {
  expect([204, 404]).toContain((await page.request.delete(path, { headers: await auth(page) })).status());
}
async function institution(page: Page, name: string) {
  const parent = await create(page, '/api/v1/institutions', { name, address: 'Москва, Табличная улица, 92' });
  const shoot = await create(page, '/api/v1/institutions/' + parent.id + '/shoots', { name: name + ' съёмка', date: '2026-10-15' });
  return { parent, shoot };
}
async function group(page: Page, shootId: string, name: string) {
  return create(page, '/api/v1/shoots/' + shootId + '/groups', { name, groupKind: 'regular' });
}
/** A group whose link parents already have: a labelled frame, a checked link and the reported delivery. */
async function openGroup(page: Page, shootId: string, groupId: string): Promise<void> {
  const upload = await body(
    await page.request.post('/api/v1/shoots/' + shootId + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) },
      multipart: { groupId, file: { name: 'i92-' + groupId + '.png', mimeType: 'image/png', buffer: png } }
    }),
    202
  );
  let revision = 0;
  await expect
    .poll(
      async () => {
        const media = await body(await page.request.get('/api/v1/shoots/' + shootId + '/photos', { headers: await auth(page) }));
        revision = media.data.revision;
        return media.data.items.find((item: { id: string }) => item.id === upload.data.id)?.status;
      },
      { timeout: 30000 }
    )
    .toBe('ready');
  await body(
    await page.request.post('/api/v1/groups/' + groupId + '/photo-assignments', {
      headers: await auth(page),
      data: { shootId, revision, photoIds: [upload.data.id], childCode: 'A' }
    })
  );
  const initial = await link(page, groupId);
  expect(initial.problems).toEqual([]);
  await command(page, groupId, 'link-preparations', {
    photosReviewed: true,
    conditionsReviewed: true,
    staffReviewed: true,
    confirmed: true,
    revision: initial.revision,
    signature: initial.signature
  });
  const prepared = await link(page, groupId);
  // The delivery may be reported from the minute the link was checked.
  const sentAt = prepared.history![0]!.at.slice(0, 16) + ':00+03:00';
  await command(page, groupId, 'link-transmissions', {
    revision: prepared.revision,
    signature: prepared.signature,
    confirmed: true,
    sentAt
  });
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  problems.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
  page.on('response', (response) => {
    if (new URL(response.url()).pathname.startsWith('/api/') && response.status() >= 500)
      errors.push(response.status() + ' ' + response.url());
  });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
});
test.afterEach(async ({ page }) => {
  expect(problems.get(page)).toEqual([]);
});

test('#92 T09: каталог снимает с продажи выбранные позиции последовательными PATCH', async ({ page }) => {
  const mark = 'T09 ' + suffix();
  const ids = [await product(page, mark + ' Печать А'), await product(page, mark + ' Печать Б')];
  try {
    await page.getByRole('button', { name: 'Обновить каталог', exact: true }).click();
    await page.getByRole('textbox', { name: 'Название', exact: true }).fill(mark);
    await expect(rows(page)).toHaveCount(2);
    const patches: string[] = [];
    page.on('request', (request) => {
      if (request.method() === 'PATCH' && request.url().includes('/catalog/products/')) patches.push(request.headers()['idempotency-key']!);
    });
    await page.getByRole('checkbox', { name: 'Выбрать ' + mark + ' Печать А', exact: true }).check();
    await page.getByRole('checkbox', { name: 'Выбрать ' + mark + ' Печать Б', exact: true }).check();
    await page.getByTestId('catalog-bulk').getByRole('button', { name: 'Снять с продажи', exact: true }).click();
    await expect(page.getByTestId('catalog-result')).toContainText('Снято с продажи: 2 из 2.');
    await expect(rowOf(page, mark + ' Печать А')).toContainText('Отключено');
    await expect(rowOf(page, mark + ' Печать Б')).toContainText('Отключено');
    // Each save carries its own key and the revision of the previous answer; a repeat skips products already off.
    expect(new Set(patches).size).toBe(2);
    expect((await catalog(page)).filter((item) => ids.includes(item.id)).map((item) => item.active)).toEqual([false, false]);
    await page.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
    await page.getByTestId('catalog-bulk').getByRole('button', { name: 'Снять с продажи', exact: true }).click();
    await expect(page.getByTestId('catalog-result')).toContainText('Выбранная продукция уже снята с продажи.');
    expect(patches).toHaveLength(2);
  } finally {
    for (const id of ids) await remove(page, '/api/v1/catalog/products/' + id);
  }
});

test('#92 T07: неиспользованная продукция удаляется из каталога', async ({ page }) => {
  const name = 'T07 Магнит ' + suffix();
  const id = await product(page, name);
  await page.getByRole('button', { name: 'Обновить каталог', exact: true }).click();
  await page.getByRole('textbox', { name: 'Название', exact: true }).fill(name);
  await rowOf(page, name)
    .getByRole('button', { name: 'Удалить ' + name, exact: true })
    .click();
  const dialog = page.getByTestId('catalog-remove-dialog');
  await expect(dialog.getByRole('heading', { name: 'Удалить продукцию?', exact: true })).toBeVisible();
  await expect(dialog).toContainText(name);
  const deleted = page.waitForResponse((r) => r.request().method() === 'DELETE' && r.url().endsWith('/catalog/products/' + id));
  await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
  expect((await deleted).status()).toBe(204);
  await expect(page.getByTestId('catalog-result')).toContainText('Удалено: 1 из 1.');
  await expect(rows(page)).toHaveCount(0);
  expect((await catalog(page)).some((item) => item.id === id)).toBe(false);
});

test('#92 T10: две группы, съёмка и учреждение удаляются со страницы учреждения', async ({ page }) => {
  const mark = 'T10 ' + suffix();
  const { parent, shoot } = await institution(page, mark);
  const extra = await create(page, '/api/v1/institutions/' + parent.id + '/shoots', { name: mark + ' вторая съёмка', date: null });
  try {
    await group(page, shoot.id, mark + ' Ромашки');
    await group(page, shoot.id, mark + ' Васильки');
    await page.goto('/cabinet/institutions/' + parent.id);
    const groups = page.getByTestId('institution-groups');
    const shoots = page.getByTestId('institution-shoots');
    await expect(rows(groups)).toHaveCount(2);

    await groups.getByRole('checkbox', { name: 'Выбрать ' + mark + ' Ромашки', exact: true }).check();
    await groups.getByRole('checkbox', { name: 'Выбрать ' + mark + ' Васильки', exact: true }).check();
    await groups.getByRole('button', { name: 'Удалить', exact: true }).click();
    const dialog = page.getByTestId('structure-remove-dialog');
    await expect(dialog.getByRole('heading', { name: 'Удалить группы: 2?', exact: true })).toBeVisible();
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page.getByTestId('structure-removal')).toContainText('Удалено: 2 из 2.');
    await expect(rows(groups)).toHaveCount(0);
    expect(
      (await body(await page.request.get('/api/v1/institutions/' + parent.id, { headers: await auth(page) }))).data.groups.meta.total
    ).toBe(0);

    await shoots.getByRole('button', { name: 'Удалить ' + mark + ' вторая съёмка', exact: true }).click();
    await expect(dialog.getByRole('heading', { name: 'Удалить съёмку?', exact: true })).toBeVisible();
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page.getByTestId('structure-removal')).toContainText('Удалено: 1 из 1.');
    await expect(rows(shoots)).toHaveCount(1);
    expect((await page.request.get('/api/v1/shoots/' + extra.id, { headers: await auth(page) })).status()).toBe(404);

    await page.getByRole('button', { name: 'Удалить учреждение', exact: true }).click();
    await expect(dialog.getByRole('heading', { name: 'Удалить учреждение?', exact: true })).toBeVisible();
    await expect(dialog).toContainText(mark);
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page).toHaveURL(/\/cabinet\/institutions$/);
    expect((await page.request.get('/api/v1/institutions/' + parent.id, { headers: await auth(page) })).status()).toBe(404);
  } finally {
    await remove(page, '/api/v1/institutions/' + parent.id);
  }
});

test('#92 DEC-05: учреждение удаляется из списка учреждений', async ({ page }) => {
  const name = 'T04 ' + suffix();
  const { parent } = await institution(page, name);
  try {
    await page.goto('/cabinet/institutions');
    await page.getByLabel('Поиск учреждений', { exact: true }).fill(name);
    const card = page.getByTestId('structure-row').filter({ hasText: name });
    await expect(card).toHaveCount(1);
    await card.getByRole('button', { name: 'Удалить «' + name + '»', exact: true }).click();
    const dialog = page.getByTestId('structure-remove-dialog');
    await expect(dialog.getByRole('heading', { name: 'Удалить учреждение?', exact: true })).toBeVisible();
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Учреждение «' + name + '» удалено.' })).toBeVisible();
    await expect(card).toHaveCount(0);
  } finally {
    await remove(page, '/api/v1/institutions/' + parent.id);
  }
});

test('#92 T11 T12: ссылки фильтруются плиткой «Приём открыт» и копируются списком', async ({ page, context }) => {
  test.setTimeout(150000);
  const mark = 'T11 ' + suffix();
  const productId = await product(page, mark + ' Печать');
  const { parent, shoot } = await institution(page, mark);
  try {
    const second = await create(page, '/api/v1/institutions/' + parent.id + '/shoots', { name: mark + ' весна', date: '2026-10-16' });
    const first = await group(page, shoot.id, mark + ' Ромашки');
    const other = await group(page, second.id, mark + ' Васильки');
    await group(page, shoot.id, mark + ' Без кадров');
    await openGroup(page, shoot.id, first.id);
    await openGroup(page, second.id, other.id);
    const tokens = [(await link(page, first.id)).galleryToken, (await link(page, other.id)).galleryToken];

    await page.goto('/cabinet/links');
    await page.getByRole('textbox', { name: 'Группа или съёмка', exact: true }).fill(mark);
    await expect(rows(page)).toHaveCount(3);
    const tile = page.getByTestId('links-tiles').getByRole('button', { name: /^Приём открыт: / });
    await tile.click();
    await expect(tile).toHaveAttribute('aria-pressed', 'true');
    await expect(rows(page)).toHaveCount(2);
    await expect(rowOf(page, mark + ' Без кадров')).toHaveCount(0);

    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
    await page.getByTestId('links-bulk').getByRole('button', { name: 'Скопировать ссылки', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Скопировано ссылок: 2.' })).toBeVisible();
    const origin = new URL(page.url()).origin;
    const lines = (await page.evaluate(() => navigator.clipboard.readText())).split('\n').sort();
    expect(lines).toEqual([mark + ' Васильки — ' + origin + '/g/' + tokens[1], mark + ' Ромашки — ' + origin + '/g/' + tokens[0]].sort());
  } finally {
    await remove(page, '/api/v1/institutions/' + parent.id);
    await remove(page, '/api/v1/catalog/products/' + productId);
  }
});
