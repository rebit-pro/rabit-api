import { test, expect, type Page } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { login, token } from './helpers.js';
import { auth, body, command, link, moscow, preparedLinkPath, type PreparedLink } from './f2-links.js';

// F2, part one: first in group B, so the minute of preparation is over when zzzz-links reports the delivery.
const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
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

test('F2: организатор проверяет ссылку группы и повторяет проверку после изменения', async ({ page }, testInfo) => {
  test.setTimeout(180000);
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
  const record: PreparedLink = {
    groupId: group.id,
    shootId: shoot.id,
    suffix,
    prepareMinute,
    galleryToken: ready.galleryToken!,
    signature: renamed.signature
  };
  mkdirSync('var', { recursive: true });
  writeFileSync(preparedLinkPath, JSON.stringify(record));
});
