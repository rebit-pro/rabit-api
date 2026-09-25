import { test, expect, type APIResponse, type Page, type Response } from '@playwright/test';
import { login, token } from './helpers.js';

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
const key = () => crypto.randomUUID().replace(/-/g, '');
const failures = new WeakMap<Page, string[]>();

async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  if (new URL(response.url()).pathname.startsWith('/api/v1/staff-requests')) {
    expect(response.headers()['cache-control']).toBe('no-store');
    if (status === 201) expect(response.headers()['location']).toMatch(/^\/api\/v1\/staff-requests\/[a-f0-9-]+$/);
  }
  return response.json();
}
async function headers(page: Page, idempotencyKey = key()) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': idempotencyKey };
}
async function create(page: Page, path: string, data: Record<string, unknown>) {
  return (await body(await page.request.post(path, { headers: await headers(page), data }), 201)).data;
}
async function assignStaff(page: Page, email: string, role: 'teacher' | 'curator', institutionId: string, groupId?: string) {
  const listing = await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent(email), { headers: await headers(page) }));
  const staff = listing.data.items.find((item: { email: string }) => item.email === email);
  expect(staff).toBeTruthy();
  const detail = (await body(await page.request.get('/api/v1/users/' + staff.id, { headers: await headers(page) }))).data;
  const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers: await headers(page) }))).data;
  const institutionIds = role === 'curator' ? [...new Set([...detail.institutionIds, institutionId])] : [];
  const groupIds = role === 'teacher' && groupId ? [...new Set([...detail.groupIds, groupId])] : [];
  await body(
    await page.request.patch('/api/v1/users/' + staff.id, {
      headers: await headers(page),
      data: {
        name: detail.name,
        email: detail.email,
        role,
        active: true,
        institutionIds,
        groupIds,
        replaceAssignments: false,
        assignmentSignature: options.assignmentSignature,
        revision: detail.revision
      }
    })
  );
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  failures.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
  page.on('response', (response) => {
    const path = new URL(response.url()).pathname;
    if (path.startsWith('/api/') && response.status() >= 500) errors.push(response.status() + ' ' + path);
  });
});
test.afterEach(async ({ page }) => {
  expect(failures.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).some((name) => name.startsWith('morefoto:demo:')))).toBe(false);
});

test('F1: воспитатель подаёт список, куратор уточняет, сервер сохраняет право и историю', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(150000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create(page, '/api/v1/institutions', { name: 'F1 Детский сад ' + suffix, address: 'Москва' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'F1 Съёмка', date: '2026-10-20' });
  const group = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'F1 Ромашки', groupKind: 'regular' });
  const foreign = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'F1 Чужая группа', groupKind: 'regular' });
  const upload = await body(
    await page.request.post('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) },
      multipart: { groupId: group.id, file: { name: 'f1-child.png', mimeType: 'image/png', buffer: png } }
    }),
    202
  );
  const photoId = upload.data.id as string;
  let media: { data: { items: Array<{ id: string; status: string }>; revision: number } } | undefined;
  await expect
    .poll(
      async () => {
        media = await (
          await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
            headers: { Authorization: 'Bearer ' + (await token(page)) }
          })
        ).json();
        return media?.data.items.find((item) => item.id === photoId)?.status;
      },
      { timeout: 30000 }
    )
    .toBe('ready');
  await body(
    await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
      headers: await headers(page),
      data: { shootId: shoot.id, revision: media!.data.revision, photoIds: [photoId], childCode: 'A' }
    })
  );
  await assignStaff(page, 'teacher@example.invalid', 'teacher', institution.id, group.id);
  await assignStaff(page, 'curator@example.invalid', 'curator', institution.id);

  const teacherContext = await browser.newContext({ baseURL });
  const curatorContext = await browser.newContext({ baseURL });
  const unavailableContext = await browser.newContext({ baseURL });
  try {
    const teacher = await teacherContext.newPage();
    await login(teacher, 'teacher');
    // #26: the list reads one bounded page, not every page of the history.
    const firstPage = teacher.waitForRequest((request) => {
      const url = new URL(request.url());
      return url.pathname === '/api/v1/staff-requests' && url.searchParams.get('pageSize') === '20';
    });
    await teacher.getByLabel('Основная навигация').getByRole('link', { name: 'Списки сотрудников', exact: true }).click();
    await expect(teacher.getByRole('heading', { name: 'Заявки на списки сотрудников', exact: true })).toBeVisible();
    expect(new URL((await firstPage).url()).searchParams.get('page')).toBe('1');
    await teacher.getByRole('button', { name: 'Новый список', exact: true }).click();
    const dialog = teacher.getByTestId('admin-dialog');
    await dialog.getByLabel('Учреждение списка', { exact: true }).press('Enter');
    await teacher.getByRole('option', { name: 'F1 Детский сад ' + suffix, exact: true }).click();
    await dialog.getByLabel('Съёмка списка', { exact: true }).press('Enter');
    await teacher.getByRole('option', { name: 'F1 Съёмка', exact: true }).click();
    await dialog.getByLabel('Исходная группа 1', { exact: true }).press('Enter');
    await teacher.getByRole('option', { name: 'F1 Ромашки', exact: true }).click();
    await expect(dialog.getByLabel('Учреждение списка', { exact: true })).toHaveValue(institution.id);
    await expect(dialog.getByLabel('Съёмка списка', { exact: true })).toHaveValue(shoot.id);
    await expect(dialog.getByLabel('Исходная группа 1', { exact: true })).toHaveValue(group.id);
    let submittedRequests = 0;
    teacher.on('request', (request) => {
      if (new URL(request.url()).pathname === '/api/v1/staff-requests' && request.method() === 'POST') submittedRequests++;
    });
    await dialog.getByLabel('Код ребёнка или снимка 1', { exact: true }).fill('A0001');
    await dialog.getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    await expect(dialog.getByText('Укажите код ребёнка или снимка: например, A или A001. У снимка ровно три цифры.')).toBeVisible();
    expect(submittedRequests).toBe(0);
    await dialog.getByLabel('Код ребёнка или снимка 1', { exact: true }).fill('A001');
    await dialog.getByLabel('Комментарий к списку', { exact: true }).fill('Первичная заявка F1');
    const pendingRequest = teacher.waitForRequest(
      (request) => new URL(request.url()).pathname === '/api/v1/staff-requests' && request.method() === 'POST'
    );
    const pendingResponse = teacher.waitForResponse(
      (response) => new URL(response.url()).pathname === '/api/v1/staff-requests' && response.request().method() === 'POST'
    );
    await dialog.getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    const createRequest = await pendingRequest;
    const created = (await body(await pendingResponse, 201)).data;
    await expect(dialog).not.toBeVisible();
    await expect(teacher.locator('a[href="/cabinet/staff-requests/' + created.id + '"]')).toHaveCount(1);
    await teacher.screenshot({ path: testInfo.outputPath('f1-desktop-list.png'), fullPage: true });

    const createKey = createRequest.headers()['idempotency-key']!;
    const createBody = createRequest.postDataJSON();

    const latestMedia = await body(await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', { headers: await headers(page) }));
    await body(
      await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
        headers: await headers(page),
        data: { shootId: shoot.id, revision: latestMedia.data.revision, photoIds: [photoId], childCode: 'B' }
      })
    );
    const otherRequest = await create(page, '/api/v1/staff-requests', {
      ...createBody,
      rows: [{ ...createBody.rows[0], id: crypto.randomUUID(), code: 'B' }]
    });
    const ownList = await body(
      await teacher.request.get('/api/v1/staff-requests?shootId=' + shoot.id, {
        headers: await headers(teacher)
      })
    );
    expect(ownList.data.items.map((item: { id: string }) => item.id)).toEqual([created.id]);
    expect(ownList.meta).toMatchObject({ total: 1, totalPages: 1 });
    expect(ownList.data.scope.groups.map((item: { id: string }) => item.id)).toContain(group.id);
    expect(ownList.data.scope.groups.map((item: { id: string }) => item.id)).not.toContain(foreign.id);
    await body(
      await teacher.request.get('/api/v1/staff-requests/' + otherRequest.id, {
        headers: await headers(teacher)
      }),
      404
    );
    for (const invalid of [
      { ...createBody, comment: 42 },
      { ...createBody, idempotencyKey: 'body-must-not-supply-header' },
      { ...createBody, rows: { 0: createBody.rows[0] } },
      { ...createBody, rows: [{ ...createBody.rows[0], unexpected: true }] }
    ]) {
      await body(
        await teacher.request.post('/api/v1/staff-requests', {
          headers: await headers(teacher),
          data: invalid
        }),
        422
      );
    }
    await body(
      await teacher.request.put('/api/v1/staff-requests/' + created.id, {
        headers: await headers(teacher),
        data: { ...createBody, revision: '999' }
      }),
      422
    );
    await body(
      await teacher.request.get('/api/v1/staff-requests/' + created.id + '?requestId=' + created.id, {
        headers: await headers(teacher)
      }),
      422
    );
    await body(
      await teacher.request.put('/api/v1/staff-requests/' + created.id + '?extra=1', {
        headers: await headers(teacher),
        data: { ...createBody, revision: 999 }
      }),
      400
    );
    expect(
      (
        await body(
          await teacher.request.post('/api/v1/staff-requests', { headers: await headers(teacher, createKey), data: createBody }),
          201
        )
      ).data
    ).toEqual(created);
    expect(
      (
        await body(
          await teacher.request.post('/api/v1/staff-requests', {
            headers: await headers(teacher, createKey),
            data: { ...createBody, comment: 'Подмена тела' }
          }),
          409
        )
      ).error.code
    ).toBe('IDEMPOTENCY_CONFLICT');
    expect(
      (
        await body(
          await teacher.request.post('/api/v1/staff-requests', {
            headers: await headers(teacher),
            data: { ...createBody, rows: [{ ...createBody.rows[0], groupId: foreign.id }] }
          }),
          404
        )
      ).error.code
    ).toBe('GROUP_NOT_FOUND');
    expect(
      (
        await body(
          await teacher.request.put('/api/v1/staff-requests/' + created.id, {
            headers: await headers(teacher),
            data: { ...createBody, revision: 999 }
          }),
          409
        )
      ).error.code
    ).toBe('REVISION_CONFLICT');

    const unavailable = await unavailableContext.newPage();
    await login(unavailable, 'unassigned');
    expect(
      (
        await unavailable.request.get('/api/v1/staff-requests', {
          headers: { Authorization: 'Bearer ' + (await token(unavailable)) }
        })
      ).status()
    ).toBe(403);

    await body(
      await unavailable.request.post('/api/v1/staff-requests', {
        headers: await headers(unavailable),
        data: createBody
      }),
      403
    );

    const curator = await curatorContext.newPage();
    await login(curator, 'curator');
    await body(
      await curator.request.post('/api/v1/staff-requests/' + created.id + '/clarifications', {
        headers: await headers(curator),
        data: {
          revision: 1,
          comment: 'Тест строгого confirmed',
          confirmed: 'true'
        }
      }),
      422
    );
    const curatorReads: URL[] = [];
    curator.on('request', (request) => {
      const url = new URL(request.url());
      if (request.method() === 'GET' && url.pathname.startsWith('/api/v1/staff-requests')) curatorReads.push(url);
    });
    await curator.goto('/cabinet/staff-requests/' + created.id);
    await expect(curator.getByTestId('request-detail')).toBeVisible();
    // #26: the card is HND-08 plus the scope from a one-item HND-06 page; other pages are not downloaded.
    const listReads = curatorReads.filter((url) => url.pathname === '/api/v1/staff-requests');
    expect(listReads.length).toBeGreaterThan(0);
    expect(listReads.every((url) => url.searchParams.get('page') === '1' && url.searchParams.get('pageSize') === '1')).toBe(true);
    expect(curatorReads.filter((url) => url.pathname === '/api/v1/staff-requests/' + created.id)).toHaveLength(1);
    await expect(curator.getByTestId('staff-eligibility')).toContainText('Право сотрудника подтверждено сервером');
    // D3: the transfer is available, but this shoot has no staff folder yet.
    await expect(curator.getByRole('button', { name: 'Проверить и перенести', exact: true })).toBeDisabled();
    await expect(curator.getByRole('alert').filter({ hasText: 'В этой съёмке ещё нет папки сотрудников' })).toBeVisible();
    await curator.getByRole('button', { name: 'Запросить уточнение', exact: true }).click();
    const clarifyDialog = curator.getByTestId('admin-dialog');
    await clarifyDialog.getByLabel('Что нужно уточнить', { exact: true }).fill('Уточните номер фотографии ребёнка.');
    await clarifyDialog.getByLabel('Подтверждаю запрос уточнения и сохранение причины в истории', { exact: true }).check();
    const clarification = curator.waitForResponse(
      (response) => new URL(response.url()).pathname.endsWith('/clarifications') && response.request().method() === 'POST'
    );
    await clarifyDialog.getByRole('button', { name: 'Запросить уточнение', exact: true }).click();
    expect((await body(await clarification)).data).toMatchObject({ id: created.id, revision: 2, status: 'clarification' });

    // #26: the status tile asks the server for a filtered page; the pager appears only for several pages.
    await curator.getByRole('link', { name: 'Все списки', exact: true }).click();
    const filtered = curator.waitForResponse((response) => {
      const url = new URL(response.url());
      return url.pathname === '/api/v1/staff-requests' && url.searchParams.get('status') === 'clarification';
    });
    await curator
      .getByTestId('request-tiles')
      .getByRole('button', { name: /^Нужно уточнение:/ })
      .click();
    const filteredPage = await body(await filtered);
    expect(filteredPage.meta.pageSize).toBe(20);
    expect(filteredPage.data.items.every((item: { status: string }) => item.status === 'clarification')).toBe(true);
    expect(filteredPage.data.items.map((item: { id: string }) => item.id)).toContain(created.id);
    await expect(curator.locator('a[href="/cabinet/staff-requests/' + created.id + '"]')).toHaveCount(1);
    await expect(curator.getByTestId('request-pagination')).toHaveCount(filteredPage.meta.totalPages > 1 ? 1 : 0);

    await teacher.goto('/cabinet/staff-requests/' + created.id);
    await expect(teacher.getByTestId('request-detail')).toContainText('Нужно уточнение');
    await teacher.getByRole('button', { name: 'Уточнить список', exact: true }).click();
    const editDialog = teacher.getByTestId('admin-dialog');
    await editDialog.getByLabel('Комментарий к списку', { exact: true }).fill('Исправлено после уточнения');
    const resubmit = teacher.waitForResponse(
      (response) => new URL(response.url()).pathname === '/api/v1/staff-requests/' + created.id && response.request().method() === 'PUT'
    );
    await editDialog.getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    expect((await body(await resubmit)).data).toMatchObject({ id: created.id, revision: 3, status: 'submitted' });
    const detail = (await body(await teacher.request.get('/api/v1/staff-requests/' + created.id, { headers: await headers(teacher) })))
      .data;
    expect(detail.staffEligibility).toMatchObject({ eligible: true, source: 'verified_staff_assignment' });
    expect(detail.history.map((event: { kind: string }) => event.kind)).toEqual(['submitted', 'clarification', 'submitted']);
    expect(detail.rows[0]).toMatchObject({ groupId: group.id, childCode: 'A', photoIds: [photoId] });

    // #27: simultaneous identical commands with one Idempotency-Key share one result; the request changes once.
    const twinKey = key();
    const twinBody = {
      institutionId: institution.id,
      shootId: shoot.id,
      rows: createBody.rows,
      comment: 'Параллельный повтор',
      revision: 3
    };
    const twins = await Promise.all(
      Array.from({ length: 4 }, async () =>
        body(
          await teacher.request.put('/api/v1/staff-requests/' + created.id, { headers: await headers(teacher, twinKey), data: twinBody })
        )
      )
    );
    expect(twins.map((twin) => twin.data)).toEqual(Array(4).fill({ id: created.id, revision: 4, status: 'submitted' }));
    expect(
      (
        await body(
          await teacher.request.put('/api/v1/staff-requests/' + created.id, { headers: await headers(teacher, twinKey), data: twinBody })
        )
      ).data
    ).toEqual({ id: created.id, revision: 4, status: 'submitted' });
    expect(
      (
        await body(
          await teacher.request.put('/api/v1/staff-requests/' + created.id, {
            headers: await headers(teacher, twinKey),
            data: { ...twinBody, comment: 'Другое тело' }
          }),
          409
        )
      ).error.code
    ).toBe('IDEMPOTENCY_CONFLICT');
    const mediaForC = await body(await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', { headers: await headers(page) }));
    await body(
      await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
        headers: await headers(page),
        data: { shootId: shoot.id, revision: mediaForC.data.revision, photoIds: [photoId], childCode: 'C' }
      })
    );
    const createTwinKey = key();
    const createTwinBody = {
      institutionId: institution.id,
      shootId: shoot.id,
      rows: [{ id: crypto.randomUUID(), groupId: group.id, code: 'C' }],
      comment: 'Параллельное создание'
    };
    const createdTwins = await Promise.all(
      Array.from({ length: 4 }, async () =>
        body(
          await teacher.request.post('/api/v1/staff-requests', { headers: await headers(teacher, createTwinKey), data: createTwinBody }),
          201
        )
      )
    );
    expect(new Set(createdTwins.map((twin) => JSON.stringify(twin.data))).size).toBe(1);
    const twinList = await body(
      await teacher.request.get('/api/v1/staff-requests?shootId=' + shoot.id + '&pageSize=100', { headers: await headers(teacher) })
    );
    expect(twinList.data.items.filter((item: { comment: string }) => item.comment === 'Параллельное создание')).toHaveLength(1);
    const clarifyKey = key();
    const clarifyBody = { revision: 4, comment: 'Параллельное уточнение', confirmed: true };
    const clarifications = await Promise.all(
      Array.from({ length: 4 }, async () =>
        body(
          await curator.request.post('/api/v1/staff-requests/' + created.id + '/clarifications', {
            headers: await headers(curator, clarifyKey),
            data: clarifyBody
          })
        )
      )
    );
    expect(clarifications.map((item) => item.data)).toEqual(Array(4).fill({ id: created.id, revision: 5, status: 'clarification' }));
    expect(
      (
        await body(
          await curator.request.post('/api/v1/staff-requests/' + created.id + '/clarifications', {
            headers: await headers(curator, clarifyKey),
            data: { ...clarifyBody, comment: 'Другая причина' }
          }),
          409
        )
      ).error.code
    ).toBe('IDEMPOTENCY_CONFLICT');
    const afterTwins = (await body(await teacher.request.get('/api/v1/staff-requests/' + created.id, { headers: await headers(teacher) })))
      .data;
    expect(afterTwins).toMatchObject({ revision: 5, status: 'clarification', comment: 'Параллельный повтор' });
    expect(afterTwins.history.map((event: { kind: string }) => event.kind)).toEqual([
      'submitted',
      'clarification',
      'submitted',
      'submitted',
      'clarification'
    ]);

    await teacher.setViewportSize({ width: 390, height: 844 });
    await teacher.reload();
    await expect(teacher.getByTestId('request-detail')).toBeVisible();
    expect(await teacher.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await teacher.screenshot({ path: testInfo.outputPath('f1-mobile-detail.png'), fullPage: true });
  } finally {
    await Promise.all([teacherContext.close(), curatorContext.close(), unavailableContext.close()]);
  }
});

for (const viewport of [
  { name: 'desktop', width: 1280, height: 720 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  for (const account of ['teacher', 'organizer', 'curator', 'head'] as const) {
    test(`F1 navigation: ${account} enters through ${viewport.name} menu after login`, async ({ page }, testInfo) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await login(page, account);
      if (viewport.name === 'mobile') await page.getByRole('button', { name: 'Открыть меню', exact: true }).click();
      const navigation = page.getByLabel('Основная навигация');
      await expect(navigation).toBeInViewport();
      const link = navigation.getByRole('link', { name: 'Списки сотрудников', exact: true });
      await expect(link).toBeVisible();
      if (account === 'teacher') {
        await page.screenshot({
          path: testInfo.outputPath(`f1-${viewport.name}-navigation.png`),
          fullPage: true,
          animations: 'disabled'
        });
      }
      await link.click();
      await expect(page).toHaveURL(/\/cabinet\/staff-requests$/);
      await expect(page.getByRole('heading', { name: 'Заявки на списки сотрудников', exact: true })).toBeVisible();
      if (viewport.name === 'mobile') await expect(navigation).not.toBeInViewport();
      const createButton = page.getByRole('button', { name: 'Новый список', exact: true });
      if (account === 'curator') {
        await expect(createButton).toHaveCount(0);
        return;
      }
      if (account === 'head') {
        // DS-14: the head reads the lists of their institutions and changes nothing.
        await expect(createButton).toHaveCount(0);
        await expect(page.getByText('Только просмотр', { exact: true })).toBeVisible();
        const lists = await body(await page.request.get('/api/v1/staff-requests', { headers: await headers(page) }), 200);
        expect(lists.data.scope.role).toBe('head');
        return;
      }
      await createButton.click();
      const dialog = page.getByTestId('admin-dialog');
      await expect(dialog).toBeVisible();
      await expect(dialog.getByLabel('Учреждение списка', { exact: true })).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      if (account === 'teacher') {
        await page.screenshot({
          path: testInfo.outputPath(`f1-${viewport.name}-new-request.png`),
          fullPage: true,
          animations: 'disabled'
        });
      }
    });
  }
}
