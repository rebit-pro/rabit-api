import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, password, token } from './helpers.js';

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
const key = () => crypto.randomUUID().replace(/-/g, '');
type Link = {
  groupId: string;
  revision: number;
  signature: string;
  prepared: boolean;
  problems: string[];
  state: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  galleryToken?: string | null;
  history?: { kind: string; at: string; reason: string | null; previousSentAt: string | null }[];
};

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  if (new URL(response.url()).pathname.includes('link')) expect(response.headers()['cache-control']).toBe('no-store');
  return response.json();
}
async function auth(page: Page, idempotencyKey = key()) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': idempotencyKey };
}
async function link(page: Page, groupId: string): Promise<Link> {
  return (await body(await page.request.get('/api/v1/groups/' + groupId + '/link', { headers: await auth(page) }))).data;
}
async function command(page: Page, groupId: string, action: string, data: Record<string, unknown>, status = 200, idempotencyKey = key()) {
  return body(
    await page.request.post('/api/v1/groups/' + groupId + '/' + action, { headers: await auth(page, idempotencyKey), data }),
    status
  );
}
async function assign(page: Page, email: string, role: 'teacher' | 'curator' | 'head', institutionId: string, groupId: string) {
  const listing = await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent(email), { headers: await auth(page) }));
  const staff = listing.data.items.find((item: { email: string }) => item.email === email);
  const detail = (await body(await page.request.get('/api/v1/users/' + staff.id, { headers: await auth(page) }))).data;
  const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers: await auth(page) }))).data;
  await body(
    await page.request.patch('/api/v1/users/' + staff.id, {
      headers: await auth(page),
      data: {
        name: detail.name,
        email: detail.email,
        role,
        active: true,
        institutionIds: role === 'teacher' ? [] : [...new Set([...detail.institutionIds, institutionId])],
        groupIds: role === 'teacher' ? [...new Set([...detail.groupIds, groupId])] : [],
        replaceAssignments: false,
        assignmentSignature: options.assignmentSignature,
        revision: detail.revision
      }
    })
  );
}
/** Moscow form value of a moment, as the staff types it. */
const moscow = (moment: number) => new Date(moment + 3 * 3600000).toISOString().slice(0, 16);

test('F2: организатор проверяет ссылку, воспитатель отмечает передачу, куратор исправляет дату', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(240000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const create = async (path: string, data: Record<string, unknown>) =>
    (await body(await page.request.post(path, { headers: await auth(page), data }), 201)).data;
  const institution = await create('/api/v1/institutions', { name: 'F2 Детский сад ' + suffix, address: 'Москва' });
  const shoot = await create('/api/v1/institutions/' + institution.id + '/shoots', { name: 'F2 Съёмка', date: '2026-10-21' });
  const group = await create('/api/v1/shoots/' + shoot.id + '/groups', { name: 'F2 Ромашки ' + suffix, groupKind: 'regular' });
  await create('/api/v1/catalog/products', {
    name: 'F2 Печать ' + suffix,
    description: '',
    kind: 'physical',
    price: 15000,
    printCount: 1,
    format: '10×15',
    unit: 'шт.',
    staffDiscount: false,
    active: true
  });
  const upload = await body(
    await page.request.post('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) },
      multipart: { groupId: group.id, file: { name: 'f2-child.png', mimeType: 'image/png', buffer: png } }
    }),
    202
  );
  let revision = 0;
  await expect
    .poll(
      async () => {
        const media = await body(await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', { headers: await auth(page) }));
        revision = media.data.revision;
        return media.data.items.find((item: { id: string }) => item.id === upload.data.id)?.status;
      },
      { timeout: 30000 }
    )
    .toBe('ready');
  expect((await link(page, group.id)).problems).toEqual(['unassignedPhotos']);
  await body(
    await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
      headers: await auth(page),
      data: { shootId: shoot.id, revision, photoIds: [upload.data.id], childCode: 'A' }
    })
  );
  await assign(page, 'teacher@example.invalid', 'teacher', institution.id, group.id);
  await assign(page, 'curator@example.invalid', 'curator', institution.id, group.id);
  await assign(page, 'head@example.invalid', 'head', institution.id, group.id);

  const initial = await link(page, group.id);
  expect([initial.revision, initial.prepared, initial.problems, initial.galleryToken, initial.state]).toEqual([
    1,
    false,
    [],
    null,
    'preparing'
  ]);
  const list = await body(await page.request.get('/api/v1/group-links?shootId=' + shoot.id, { headers: await auth(page) }));
  const { summary, ...paging } = list.meta;
  expect(paging).toEqual({ page: 1, pageSize: 25, total: 1, totalPages: 1 });
  expect(summary.byState).toEqual({ preparing: 1, open: 0, closed: 0 });
  expect(list.data.items[0].groupId).toBe(group.id);
  expect(list.data.items[0]).not.toHaveProperty('galleryToken');
  const review = { photosReviewed: true, conditionsReviewed: true, staffReviewed: true, confirmed: true };
  expect(
    (await command(page, group.id, 'link-preparations', { ...review, revision: 2, signature: initial.signature }, 409)).error.code
  ).toBe('REVISION_CONFLICT');
  expect((await command(page, group.id, 'link-preparations', { ...review, revision: 1, signature: '0'.repeat(64) }, 409)).error.code).toBe(
    'SIGNATURE_CONFLICT'
  );
  expect(
    (
      await command(
        page,
        group.id,
        'link-preparations',
        { ...review, staffReviewed: false, revision: 1, signature: initial.signature },
        422
      )
    ).error.code
  ).toBe('REVIEW_REQUIRED');

  // Organizer prepares through the live screen.
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Ссылки и сроки', exact: true }).click();
  const card = page.getByTestId('link-' + group.id);
  await expect(card.getByText('Ссылка появится после проверки группы.')).toBeVisible();
  await card.getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  for (const label of [
    'Фотографии и коды проверены',
    'Продукция, цены и условия группы проверены',
    'Списки сотрудников и ответственные проверены'
  ])
    await dialog.getByLabel(label, { exact: true }).check();
  // #81: the server applies the preparation but the answer is lost; the form reports an unknown outcome and the
  // unchanged repeat carries the same key, so the server replays it instead of preparing twice.
  const isPreparation = (request: { url(): string; method(): string }) =>
    request.url().endsWith('/link-preparations') && request.method() === 'POST';
  let answerLost = false;
  await page.route(
    (url) => url.pathname.endsWith('/link-preparations'),
    async (route) => {
      if (answerLost || route.request().method() !== 'POST') return route.fallback();
      answerLost = true;
      await route.fetch();
      await route.abort('failed');
    }
  );
  const lostPreparation = page.waitForRequest(isPreparation);
  await dialog.getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  const lostKey = (await lostPreparation).headers()['idempotency-key'];
  await expect(dialog.getByText(/Ответ сервера не получен — изменение могло сохраниться/)).toBeVisible();
  await expect(dialog.getByText(/Сервер не сохранил/)).toHaveCount(0);
  const repeatedPreparation = page.waitForRequest(isPreparation);
  const prepared = page.waitForResponse((r) => isPreparation(r.request()));
  await dialog.getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  expect((await repeatedPreparation).headers()['idempotency-key']).toBe(lostKey);
  expect((await prepared).status()).toBe(200);
  await expect(card.getByText('Готова к передаче', { exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('f2-desktop-prepared.png'), fullPage: true, animations: 'disabled' });

  const ready = await link(page, group.id);
  expect(ready.galleryToken).toMatch(/^[a-f0-9]{64}$/);
  // The key is issued in the same transaction as the first preparation event: its minute is the earliest delivery.
  const prepareMinute = ready.history![0]!.at.slice(0, 16);
  expect([ready.revision, ready.prepared]).toEqual([2, true]);
  const galleryPath = '/api/v1/public/galleries/' + ready.galleryToken;
  expect((await body(await page.request.get(galleryPath))).data.state).toBe('preparing');

  // Renaming the group invalidates the confirmed state until the organizer checks again.
  await body(
    await page.request.patch('/api/v1/groups/' + group.id, {
      headers: await auth(page),
      data: { revision: 1, name: 'F2 Ромашки ' + suffix + ' (2)' }
    })
  );
  const renamed = await link(page, group.id);
  expect(renamed.prepared).toBe(false);
  const later = { revision: 2, confirmed: true, sentAt: moscow(Date.now()) + ':00+03:00' };
  expect((await command(page, group.id, 'link-transmissions', { ...later, signature: ready.signature }, 409)).error.code).toBe(
    'SIGNATURE_CONFLICT'
  );
  expect((await command(page, group.id, 'link-transmissions', { ...later, signature: renamed.signature }, 409)).error.code).toBe(
    'LINK_NOT_PREPARED'
  );
  await command(page, group.id, 'link-preparations', { ...review, revision: 2, signature: renamed.signature });
  // The link cannot be reported before it existed; wait for the next minute so the correction can go back to it.
  await expect.poll(() => moscow(Date.now()) > prepareMinute, { timeout: 65000, intervals: [1000] }).toBe(true);

  const teacherContext = await browser.newContext({ baseURL });
  const curatorContext = await browser.newContext({ baseURL });
  const headContext = await browser.newContext({ baseURL });
  try {
    const head = await headContext.newPage();
    await login(head, 'head');
    expect(
      (await command(head, group.id, 'link-transmissions', { ...later, revision: 3, signature: renamed.signature }, 403)).error.code
    ).toBe('FORBIDDEN');
    await head.goto('/cabinet/links');
    await expect(head.getByTestId('link-' + group.id)).toBeVisible();
    await expect(head.getByText('Только просмотр', { exact: true })).toBeVisible();
    await expect(head.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);

    const teacher = await teacherContext.newPage();
    await teacher.setViewportSize({ width: 390, height: 844 });
    await login(teacher, 'teacher');
    await teacher.goto('/cabinet/links');
    const teacherCard = teacher.getByTestId('link-' + group.id);
    await teacherCard.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    const sentAt = moscow(Date.now());
    await teacher.getByTestId('admin-dialog').getByLabel('Дата и время передачи (МСК)', { exact: true }).fill(sentAt);
    await teacher.getByTestId('admin-dialog').getByLabel('Подтверждаю факт передачи и указанные сроки', { exact: true }).check();
    const transmitted = teacher.waitForResponse((r) => r.url().endsWith('/link-transmissions') && r.request().method() === 'POST');
    await teacher.getByTestId('admin-dialog').getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    expect((await transmitted).status()).toBe(200);
    await expect(teacherCard.getByText('Приём открыт', { exact: true })).toBeVisible();
    expect(await teacher.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await teacher.screenshot({ path: testInfo.outputPath('f2-mobile-open.png'), fullPage: true, animations: 'disabled' });
    expect(
      (
        await command(
          teacher,
          group.id,
          'link-date-corrections',
          { ...later, revision: 4, signature: renamed.signature, reason: 'Ошибка' },
          403
        )
      ).error.code
    ).toBe('FORBIDDEN');

    const open = await link(page, group.id);
    expect(open.sentAt).toBe(sentAt + ':00+03:00');
    expect(Date.parse(open.closesAt!) - Date.parse(open.sentAt!)).toBe(7 * 86400000);
    expect(Date.parse(open.deliveryAt!) - Date.parse(open.closesAt!)).toBe(7 * 86400000);
    const gallery = (await body(await page.request.get(galleryPath))).data;
    expect([gallery.state, Date.parse(gallery.closesAt)]).toEqual(['open', Date.parse(open.closesAt!)]);
    const catalog = (await body(await page.request.get(galleryPath + '/catalog'))).data;
    const product = catalog.products.find((item: { name: string }) => item.name === 'F2 Печать ' + suffix);
    const assignmentId = gallery.children[0].photos[0].assignmentId as string;
    const quote = (
      await body(
        await page.request.post(galleryPath + '/quotes', { data: { lines: [{ assignmentId, productId: product.id, quantity: 2 }] } })
      )
    ).data;
    expect(quote.quote.total).toBeGreaterThan(0);
    const shootCard = (await body(await page.request.get('/api/v1/shoots/' + shoot.id, { headers: await auth(page) }))).data;
    expect(shootCard.groups.items[0].sentAt).toBe(open.sentAt);

    // A repeated report never moves deadlines, even with a new key and a stale form.
    const repeatKey = key();
    const repeat = (
      await command(page, group.id, 'link-transmissions', { ...later, revision: 1, signature: '0'.repeat(64) }, 200, repeatKey)
    ).data;
    expect([repeat.sentAt, repeat.closesAt]).toEqual([open.sentAt, open.closesAt]);
    expect(
      (await command(page, group.id, 'link-transmissions', { ...later, revision: 1, signature: '0'.repeat(64) }, 200, repeatKey)).data
    ).toEqual(repeat);
    expect(
      (await command(page, group.id, 'link-transmissions', { ...later, revision: 2, signature: '0'.repeat(64) }, 409, repeatKey)).error.code
    ).toBe('IDEMPOTENCY_CONFLICT');

    const curator = await curatorContext.newPage();
    await login(curator, 'curator');
    const before = { revision: open.revision, signature: open.signature, confirmed: true, reason: 'Передали раньше, чем отметили' };
    const earliest = Date.parse(prepareMinute + ':00+03:00') - 2 * 60000;
    expect(
      (await command(curator, group.id, 'link-date-corrections', { ...before, sentAt: moscow(earliest) + ':00+03:00' }, 422)).error.code
    ).toBe('SENT_AT_BEFORE_LINK');
    const corrected = (await command(curator, group.id, 'link-date-corrections', { ...before, sentAt: prepareMinute + ':00+03:00' })).data;
    expect(corrected.sentAt).toBe(prepareMinute + ':00+03:00');
    const history = (await link(page, group.id)).history!;
    expect(history.map((event) => event.kind)).toEqual(['prepared', 'prepared', 'transmitted', 'corrected']);
    expect([history[3]!.reason, history[3]!.previousSentAt]).toEqual(['Передали раньше, чем отметили', open.sentAt]);

    const foreign = await browser.newPage({ baseURL });
    await foreign.goto('/login');
    await foreign.getByRole('textbox', { name: 'Email', exact: true }).fill('another-teacher@example.invalid');
    await foreign.getByLabel('Пароль', { exact: true }).fill(password);
    await Promise.all([
      foreign.waitForResponse((r) => r.url().endsWith('/api/v1/me')),
      foreign.getByRole('button', { name: 'Войти', exact: true }).click()
    ]);
    expect(
      (await body(await foreign.request.get('/api/v1/groups/' + group.id + '/link', { headers: await auth(foreign) }), 404)).error.code
    ).toBe('GROUP_NOT_FOUND');
    await foreign.close();
  } finally {
    await teacherContext.close();
    await curatorContext.close();
    await headContext.close();
  }
});
