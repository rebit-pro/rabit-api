import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import { baseOrder, organization, photo } from './fixtures.js';
import type { ScopeSnapshot } from '../../../src/modules/morefoto/types.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { PhotoState } from '../../../src/modules/morefoto/photos/types.js';
import type { Refund } from '../../../src/modules/morefoto/settlement/types.js';
type DemoWindow = Window & { __MOREFOTO_MOCKS__?: { setNow(value: string): void; setDelay(value: number): void; failNextRequest(): void } };
const page = (w: CustomWorld) => {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
};
const path = '/cabinet/institutions/sun';
const blankSettlement = () => ({
  revision: 1,
  operations: [],
  corrections: [],
  refunds: [],
  decisions: [],
  recoveries: [],
  preparedFiles: [],
  hold: false,
  needsReprint: false
});
const refund = (amount: number, status: Refund['status']): Refund => ({
  id: 'refund-' + status,
  at: '2026-09-06T09:00:00Z',
  actorId: 102,
  actorName: 'Рита',
  reason: 'Тестовый возврат',
  amount,
  status,
  allocations: { 'line-1': amount },
  fileIds: [],
  hold: false,
  history: []
});
async function go(p: Page, base: string, target = path) {
  await p.goto(base + target, { waitUntil: 'networkidle' });
}
async function login(p: Page, base: string, role = 'head') {
  await p.evaluate(() => {
    for (const k of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + k);
  });
  await go(p, base, '/login');
  await p.getByLabel('Email', { exact: true }).fill(role + '@morefoto.test');
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function choose(p: Page, label: string, text: string) {
  await p.getByLabel(label, { exact: true }).locator('..').click();
  await p.getByRole('option', { name: text, exact: true }).click();
  await p.locator('h1').click();
}
async function read<T>(p: Page, key: string): Promise<T> {
  return p.evaluate((k) => JSON.parse(localStorage.getItem('morefoto:demo:' + k)!), key);
}
async function save(p: Page, key: string, value: unknown) {
  await p.evaluate(
    ({ key, value }) => {
      localStorage.setItem('morefoto:demo:' + key, JSON.stringify(value));
      window.dispatchEvent(new Event('morefoto:demo:changed'));
    },
    { key, value }
  );
}
async function capture(p: Page): Promise<ScopeSnapshot> {
  await expect.poll(() => p.evaluate(() => !!(window as Window & { __r13Scope?: ScopeSnapshot }).__r13Scope)).toBe(true);
  return p.evaluate(() => (window as Window & { __r13Scope?: ScopeSnapshot }).__r13Scope!);
}
const main = (p: Page) => p.locator('#cabinet-main');
const card = (p: Page, id = 'sun-stars') => p.getByTestId('summary-' + id);
async function head(p: Page, base: string) {
  await login(p, base);
  await go(p, base);
  await expect(p.getByTestId('dashboard-board')).toBeVisible();
}
async function teacher(p: Page, base: string) {
  await login(p, base, 'teacher');
  await expect(p.getByTestId('dashboard-board')).toBeVisible();
}
async function clearRequests(p: Page) {
  const state = await read<PhotoState>(p, 'photos:v1');
  state.staffRequests = [];
  await save(p, 'photos:v1', state);
}
async function prepare(p: Page, base: string) {
  await login(p, base, 'organizer');
  await go(p, base, '/cabinet/links?group=sun-ready');
  await p.getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  for (const label of [
    'Фотографии и коды проверены',
    'Продукция, цены и условия группы проверены',
    'Списки сотрудников и ответственные проверены'
  ])
    await p.getByLabel(label, { exact: true }).check();
  await p.getByRole('dialog').getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  await expect(p.getByRole('dialog')).toBeHidden();
}
Given('подготовлена сводка R13', async function (this: CustomWorld) {
  const p = page(this);
  await this.context!.addInitScript(() => {
    const clone = window.structuredClone.bind(window);
    window.structuredClone = function <T>(value: T, options?: StructuredSerializeOptions): T {
      const result = clone(value, options);
      if (result && typeof result === 'object' && 'dashboard' in result && 'groups' in result)
        (window as Window & { __r13Scope?: unknown }).__r13Scope = clone(result);
      return result;
    };
  });
  const org = structuredClone(organization);
  org.shoots.push({ id: 'sun-autumn', institutionId: 'sun', name: 'Осенняя съёмка', date: '2026-09-01', revision: 1 });
  org.groups.push(
    { ...org.groups[0]!, id: 'sun-staff', name: 'Сотрудники', kind: 'staff', teacherId: null, galleryToken: 'staff-r13' },
    { ...org.groups[0]!, id: 'sun-ready', name: 'Лучики', state: 'preparing', closesAt: null, galleryToken: 'ready-r13' },
    {
      ...org.groups[0]!,
      id: 'sun-autumn-group',
      name: 'Звёздочки',
      shootId: 'sun-autumn',
      shootName: 'Осенняя съёмка',
      state: 'closed',
      teacherId: null,
      closesAt: '2026-09-06T15:00:00Z',
      galleryToken: 'autumn-r13'
    }
  );
  const paid = structuredClone(baseOrder);
  paid.settlement = { ...blankSettlement(), refunds: [refund(10000, 'confirmed')] };
  const staff = {
    ...structuredClone(baseOrder),
    id: 'r13-staff',
    accessKey: '4'.repeat(32),
    groupId: 'sun-staff',
    audience: 'staff' as const,
    quote: { ...baseOrder.quote, total: 30000 },
    settlement: { ...blankSettlement(), refunds: [refund(5000, 'pending'), refund(1000, 'failed')] }
  };
  const autumn = {
    ...structuredClone(baseOrder),
    id: 'r13-autumn',
    accessKey: '5'.repeat(32),
    groupId: 'sun-autumn-group',
    quote: { ...baseOrder.quote, total: 10000 }
  };
  const orders = [
    paid,
    staff,
    autumn,
    { ...structuredClone(baseOrder), id: 'r13-unpaid', paymentStatus: 'unpaid', paidAt: undefined },
    {
      ...structuredClone(baseOrder),
      id: 'r13-foreign',
      groupId: 'school-1a',
      buyer: { ...baseOrder.buyer, email: 'private-foreign@example.test' }
    }
  ];
  const photos = ['sun-stars', 'sun-ready'].flatMap((groupId) =>
    [1, 2].map((n) => ({
      ...photo,
      id: groupId + '-A001-' + n,
      code: 'A001-0' + n,
      groupId,
      shootId: 'sun-summer-2026',
      originalGroupId: groupId,
      childCode: 'A001',
      sequence: n,
      filename: 'demo.webp',
      bytes: 0,
      fingerprint: groupId + n,
      source: 'seed',
      revision: 1
    }))
  );
  const requests = ['submitted', 'clarification', 'transferred'].map((status, i) => ({
    id: 'list-' + i,
    institutionId: 'sun',
    shootId: 'sun-summer-2026',
    createdBy: 104,
    createdByName: 'Мария',
    createdAt: '2026-09-0' + (5 + i) + 'T09:00:00Z',
    revision: 1,
    status,
    rows: [{ id: 'row-' + i, groupId: 'sun-stars', code: 'A001', childCode: 'A001', photoIds: ['sun-stars-A001-1', 'sun-stars-A001-2'] }],
    comment: 'Личный комментарий списка',
    history: [{ kind: status, actorId: 104, at: '2026-09-05T09:00:00Z', comment: 'Личная история списка' }]
  }));
  requests.push({ ...requests[0]!, id: 'foreign-list', createdBy: 999, comment: 'Чужой секретный список' });
  await save(p, 'organization:v1', org);
  await save(p, 'orders:v1', orders);
  await save(p, 'photos:v1', { photos, covers: {}, staffRequests: requests });
  await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-08T09:00:00Z'));
});
Then('R13 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'итоги учреждения и сотрудников') {
    await head(p, base);
    const totals = p.getByTestId('settlement-totals');
    await expect(totals).toContainText('Оплаченных заказов3');
    await expect(totals).toContainText('Итого после возвратов750 ₽');
    await expect(card(p)).toContainText('350 ₽');
    await expect(card(p, 'sun-staff')).toContainText('300 ₽');
    await expect(p.getByTestId('shoot-summary-sun-summer-2026')).toContainText('Оплаченных заказов: 2 · 650 ₽');
    await expect(main(p)).not.toContainText('MF-R13');
  } else if (name === 'полный возврат и рабочее правило количества') {
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.settlement!.refunds = [refund(45000, 'confirmed')];
    await save(p, 'orders:v1', orders);
    await head(p, base);
    await expect(p.getByTestId('settlement-totals')).toContainText('Оплаченных заказов3');
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов400 ₽');
    await expect(card(p)).toContainText('После возвратов0 ₽');
    await expect(main(p)).toContainText('заказ остаётся в количестве оплаченных');
  } else if (name === 'неоплаченные и неокончательные возвраты') {
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.settlement!.refunds = [refund(10000, 'pending')];
    await save(p, 'orders:v1', orders);
    await head(p, base);
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов850 ₽');
    orders[0]!.settlement!.refunds = [refund(10000, 'failed')];
    await save(p, 'orders:v1', orders);
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов850 ₽');
  } else if (name === 'съёмка поиск состояние и возврат из группы') {
    await head(p, base);
    await choose(p, 'Съёмка', 'Лето в кадре · 05.09.2026');
    await p.getByLabel('Поиск группы', { exact: true }).fill('ЗВЁЗ');
    await choose(p, 'Состояние приёма', 'Приём открыт');
    await expect(p.locator('[data-testid^="summary-"]')).toHaveCount(1);
    await expect(p.getByTestId('settlement-totals')).toContainText('350 ₽');
    await card(p).getByRole('link', { name: 'Открыть группу Звёздочки' }).click();
    await expect(p).toHaveURL(/groups\/sun-stars/);
    await expect(card(p)).toContainText('350 ₽');
    await p.getByRole('link', { name: '← К сводке' }).click();
    await expect(p.getByLabel('Поиск группы', { exact: true })).toHaveValue('ЗВЁЗ');
    await p.reload();
    await expect(p.locator('[data-testid^="summary-"]')).toHaveCount(1);
  } else if (name === 'одинаковые группы разных съёмок') {
    await head(p, base);
    await choose(p, 'Съёмка', 'Осенняя съёмка · 01.09.2026');
    await expect(card(p, 'sun-autumn-group')).toBeVisible();
    await expect(card(p)).toHaveCount(0);
    await expect(p.getByTestId('settlement-totals')).toContainText('100 ₽');
  } else if (name === 'пустой поиск и чужой фильтр') {
    await head(p, base);
    await p.getByLabel('Поиск группы', { exact: true }).fill('НичегоТакого');
    await expect(main(p)).toContainText('Группы не найдены');
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов0 ₽');
    await p.getByRole('button', { name: 'Сбросить', exact: true }).click();
    await expect(p.locator('[data-testid^="summary-"]')).toHaveCount(4);
    await go(p, base, path + '?shoot=school-summer-2026');
    await expect(main(p)).toContainText('Группы не найдены');
    await expect(main(p)).not.toContainText('Чужой класс');
  } else if (name === 'руководитель получает только безопасное представление') {
    await head(p, base);
    const scope = await capture(p),
      json = JSON.stringify(scope);
    expect(scope.groupTotals?.['sun-stars']?.net).toBe(35000);
    expect(scope.dashboard?.requests).toEqual([]);
    for (const text of [
      'buyer-r13',
      'private-foreign',
      'MF-R13',
      'quote',
      'digitalPhotos',
      'paymentAttempts',
      'accessKey',
      'Личный комментарий',
      'Личная история',
      'Чужой секретный'
    ])
      expect(json).not.toContain(text);
    await card(p).getByRole('link', { name: 'Открыть группу Звёздочки' }).click();
    await expect(main(p).locator('a[href*="/cabinet/orders/"]')).toHaveCount(0);
  } else if (name === 'воспитателю не передаются деньги и чужие списки') {
    await teacher(p, base);
    const scope = await capture(p),
      json = JSON.stringify(scope);
    expect(scope.totals).toBeUndefined();
    expect(scope.groupTotals).toBeUndefined();
    expect(scope.groups.map((g) => g.id)).toEqual(['sun-stars', 'sun-ready']);
    expect(scope.dashboard?.requests.map((r) => r.id)).toEqual(['list-0', 'list-1', 'list-2']);
    for (const text of [
      'paidCount',
      'refunded',
      'payment',
      'quote',
      'buyer',
      'foreign-list',
      'Личный комментарий',
      'photoIds',
      'childCode'
    ])
      expect(json).not.toContain(text);
    await expect(main(p)).not.toContainText('₽');
    await expect(p.getByTestId('settlement-totals')).toHaveCount(0);
  } else if (name === 'недоступные группа учреждение и заказ') {
    await teacher(p, base);
    await go(p, base, '/cabinet/groups/sun-staff');
    await expect(main(p)).toContainText('Группа недоступна');
    await go(p, base, '/cabinet/groups/school-1a');
    await expect(main(p)).toContainText('Группа недоступна');
    await go(p, base, '/cabinet/orders/r13-paid');
    await expect(p).toHaveURL(/access/);
    await expect(p.locator('body')).not.toContainText('buyer-r13');
    await login(p, base);
    await go(p, base, '/cabinet/institutions/school');
    await expect(main(p)).toContainText('Учреждение недоступно');
    await go(p, base, '/cabinet/groups/school-1a');
    await expect(main(p)).toContainText('Группа недоступна');
  } else if (name === 'копирование ссылки сохраняет даты') {
    await head(p, base);
    await this.context!.grantPermissions(['clipboard-read', 'clipboard-write']);
    const before = await read(p, 'organization:v1');
    await card(p).getByRole('button', { name: 'Скопировать ссылку Звёздочки' }).click();
    await expect(card(p)).toContainText('Ссылка скопирована');
    expect(await p.evaluate(() => navigator.clipboard.readText())).toBe(base + '/g/' + organization.groups[0]!.galleryToken);
    expect(await read(p, 'organization:v1')).toEqual(before);
  } else if (name === 'ошибка буфера оставляет ссылку доступной') {
    await teacher(p, base);
    await p.evaluate(() => {
      Object.defineProperty(navigator.clipboard, 'writeText', { value: () => Promise.reject(new Error('denied')) });
    });
    await card(p).getByRole('button', { name: 'Скопировать ссылку Звёздочки' }).click();
    await expect(card(p)).toContainText('Выделите ссылку в поле');
    await expect(card(p).getByLabel('Ссылка группы Звёздочки')).toHaveValue(base + '/g/' + organization.groups[0]!.galleryToken);
  } else if (name === 'галерея из карточки сохраняет контекст') {
    await teacher(p, base);
    await card(p).getByRole('link', { name: 'Открыть группу Звёздочки' }).click();
    await p.getByRole('link', { name: 'Открыть галерею', exact: true }).click();
    await expect(p).toHaveURL(new RegExp('/g/' + organization.groups[0]!.galleryToken));
    await expect(p.locator('body')).toContainText('Звёздочки');
  } else if (name === 'подготовленная ссылка и фактическая передача') {
    await prepare(p, base);
    await teacher(p, base);
    await expect(card(p, 'sun-ready')).toContainText('Готова к передаче');
    await card(p, 'sun-ready').getByRole('link', { name: 'Открыть группу Лучики' }).click();
    await p.getByRole('link', { name: 'Отметить передачу ссылки', exact: true }).click();
    await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    await p.getByLabel('Дата и время передачи (МСК)').fill('2026-09-08T11:00');
    await p.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    await p.getByRole('dialog').getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await go(p, base, '/cabinet/groups/sun-ready');
    await expect(card(p, 'sun-ready')).toContainText('15 сентября 2026');
    await expect(card(p, 'sun-ready')).toContainText('22 сентября 2026');
    const before = await read(p, 'organization:v1');
    await p.getByRole('link', { name: 'Ссылка и сроки', exact: true }).click();
    await expect(p.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);
    expect(await read(p, 'organization:v1')).toEqual(before);
  } else if (name === 'изменение подготовки снимает готовность') {
    await prepare(p, base);
    await teacher(p, base);
    await expect(card(p, 'sun-ready')).toContainText('Готова к передаче');
    const state = await read<PhotoState>(p, 'photos:v1');
    state.photos.find((v) => v.groupId === 'sun-ready')!.revision += 1;
    await save(p, 'photos:v1', state);
    await expect(card(p, 'sun-ready')).toContainText('Ссылка на проверке');
  } else if (name === 'срок закрывается по часам и продление обновляет доставку') {
    await head(p, base);
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-12T15:00:00Z'));
    await expect(card(p)).toContainText('Приём закрыт');
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.groups[0]!.closesAt = '2026-09-20T15:00:00Z';
    await save(p, 'organization:v1', org);
    await expect(card(p)).toContainText('Приём открыт');
    await expect(card(p)).toContainText('27 сентября 2026');
  } else if (name === 'уточнения списков открываются из обзора') {
    await teacher(p, base);
    const lists = p.getByTestId('teacher-requests');
    await expect(lists.locator('article').first()).toHaveAttribute('data-testid', 'request-summary-list-1');
    await lists.getByRole('link', { name: 'Уточнить список', exact: true }).click();
    await expect(p).toHaveURL(/staff-requests\/list-1/);
    await p.getByRole('button', { name: 'Уточнить список', exact: true }).click();
    await p.getByLabel('Комментарий к списку').fill('Подтверждены коды ребёнка');
    await p.getByRole('dialog').getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    // A second pending list for the same child must be resolved first.
    await expect(p.getByRole('dialog')).toContainText('Этот ребёнок уже есть в списке на проверке');
    await p.getByRole('dialog').getByRole('button', { name: 'Закрыть редактор', exact: true }).click();
  } else if (name === 'отправка списка и ответ куратора связаны с обзором') {
    await clearRequests(p);
    await teacher(p, base);
    await p.getByRole('link', { name: 'Списки и отправка', exact: true }).click();
    await p.getByRole('button', { name: 'Новый список', exact: true }).click();
    await p.getByLabel('Код ребёнка или снимка 1', { exact: true }).fill('A001');
    await p.getByRole('dialog').getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await go(p, base, '/cabinet/overview');
    await expect(p.getByTestId('teacher-requests')).toContainText('На проверке');
    const state = await read<PhotoState>(p, 'photos:v1'),
      id = state.staffRequests![0]!.id;
    await login(p, base, 'curator');
    await go(p, base, '/cabinet/staff-requests/' + id);
    await p.getByRole('button', { name: 'Запросить уточнение', exact: true }).click();
    await p.getByLabel('Что нужно уточнить').fill('Проверьте код ребёнка со списком учреждения');
    await p.getByRole('dialog').getByRole('button', { name: 'Запросить уточнение', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await teacher(p, base);
    await p.getByRole('link', { name: 'Уточнить список', exact: true }).click();
    await expect(p.getByTestId('request-detail')).toContainText('Проверьте код ребёнка со списком учреждения');
    await p.getByRole('button', { name: 'Уточнить список', exact: true }).click();
    await p.getByLabel('Комментарий к списку').fill('Код A001 проверен по исходной группе');
    await p.getByRole('dialog').getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await login(p, base, 'curator');
    await go(p, base, '/cabinet/staff-requests/' + id);
    await p.getByRole('button', { name: 'Проверить и перенести', exact: true }).click();
    await p.getByLabel('Проверены все кадры, подтверждаю перенос наборов').check();
    await p.getByRole('dialog').getByRole('button', { name: 'Подтвердить перенос', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await teacher(p, base);
    await expect(p.getByTestId('teacher-requests')).toContainText('Проверен и перенесён');
    await head(p, base);
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов750 ₽');
    await expect(card(p)).toContainText('350 ₽');
  } else if (name === 'статус переноса обновляется без перезагрузки') {
    await teacher(p, base);
    const state = await read<PhotoState>(p, 'photos:v1');
    state.staffRequests![0]!.status = 'transferred';
    await save(p, 'photos:v1', state);
    await expect(p.getByTestId('request-summary-list-0')).toContainText('Проверен и перенесён');
  } else if (name === 'обновление оплаты из другой вкладки') {
    await head(p, base);
    const other = await this.context!.newPage();
    await other.goto(base);
    const orders = await read<OrderSnapshot[]>(other, 'orders:v1');
    orders.find((o) => o.id === 'r13-unpaid')!.paymentStatus = 'paid';
    await save(other, 'orders:v1', orders);
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов1 200 ₽');
    await expect(p.getByTestId('settlement-totals')).toContainText('Оплаченных заказов4');
    await other.close();
  } else if (name === 'возврат из карточки куратора обновляет сводку') {
    await login(p, base, 'curator');
    await go(p, base, '/cabinet/orders/r13-paid');
    await p.getByRole('button', { name: 'Оформить возврат', exact: true }).click();
    await p.getByLabel('Сумма возврата, ₽', { exact: true }).fill('50');
    await p.getByLabel('Причина операции', { exact: true }).fill('Согласовано с родителем');
    await p.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
    await p.getByRole('dialog').getByRole('button', { name: 'Подтвердить операцию', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await p.getByRole('button', { name: 'Результат возврата', exact: true }).click();
    await p.getByLabel('Причина операции', { exact: true }).fill('Возврат подтверждён');
    await p.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
    await p.getByRole('dialog').getByRole('button', { name: 'Подтвердить операцию', exact: true }).click();
    await expect(p.getByRole('dialog')).toBeHidden();
    await head(p, base);
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов700 ₽');
  } else if (name === 'отзыв назначения убирает группу и список') {
    await teacher(p, base);
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.groups[0]!.teacherId = null;
    await save(p, 'organization:v1', org);
    await expect(card(p)).toHaveCount(0);
    await expect(p.getByTestId('teacher-requests').locator('article')).toHaveCount(0);
    await go(p, base, '/cabinet/groups/sun-stars');
    await expect(main(p)).toContainText('Группа недоступна');
  } else if (name === 'загрузка ошибка и повтор не оставляют старую сводку') {
    await login(p, base);
    await p.evaluate(() => {
      (window as DemoWindow).__MOREFOTO_MOCKS__?.setDelay(1500);
      (window as DemoWindow).__MOREFOTO_MOCKS__?.failNextRequest();
    });
    await p.getByRole('link', { name: 'Открыть учреждение', exact: true }).click();
    await expect(p.locator('.v-skeleton-loader')).toBeVisible();
    await expect(p.getByRole('alert')).toContainText('Не удалось загрузить данные');
    await expect(p.getByTestId('dashboard-board')).toHaveCount(0);
    await p.getByRole('button', { name: 'Повторить загрузку', exact: true }).click();
    await expect(p.getByTestId('dashboard-board')).toBeVisible();
  } else if (name === 'учреждение без съёмок и пустой кабинет') {
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.shoots = org.shoots.filter((s) => s.institutionId !== 'sun');
    org.groups = org.groups.filter((g) => g.institutionId !== 'sun');
    await save(p, 'organization:v1', org);
    await head(p, base);
    await expect(main(p)).toContainText('Съёмки пока не созданы');
    await login(p, base, 'teacher');
    await expect(main(p)).toContainText('Пока нет назначенных учреждений');
  } else if (name === 'контакт только назначенного куратора') {
    await teacher(p, base);
    await expect(main(p).getByRole('link', { name: 'curator@morefoto.test' })).toHaveAttribute('href', 'mailto:curator@morefoto.test');
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.institutions[0]!.curatorId = null;
    await save(p, 'organization:v1', org);
    await expect(main(p)).toContainText('Куратор пока не назначен');
    await expect(main(p).getByRole('link', { name: 'curator@morefoto.test' })).toHaveCount(0);
  } else if (name === 'фильтр воспитателя ограничивает группы и списки') {
    await teacher(p, base);
    await p.getByLabel('Поиск группы', { exact: true }).fill('Лучики');
    await expect(card(p, 'sun-ready')).toBeVisible();
    await expect(card(p)).toHaveCount(0);
    await expect(p.getByTestId('teacher-requests').locator('article')).toHaveCount(0);
    await expect(main(p)).not.toContainText('₽');
  } else if (name === 'клавиатура открывает группу и возвращает фильтр') {
    await teacher(p, base);
    await p.getByLabel('Поиск группы', { exact: true }).focus();
    await p.keyboard.type('Лучики');
    await expect(card(p, 'sun-ready')).toBeVisible();
    const link = card(p, 'sun-ready').getByRole('link', { name: 'Открыть группу Лучики' });
    await link.focus();
    await p.keyboard.press('Enter');
    await expect(p.getByRole('heading', { level: 1, name: 'Лучики', exact: true })).toBeVisible();
    await expect(p.getByLabel('Ссылка группы Лучики', { exact: true })).toBeVisible();
    await p.getByRole('link', { name: '← К сводке' }).focus();
    await p.keyboard.press('Enter');
    await expect(p.getByLabel('Поиск группы', { exact: true })).toHaveValue('Лучики');
  } else throw new Error('Неизвестный сценарий R13: ' + name);
});
Then('R13 показывает {string} шириной {int}', async function (this: CustomWorld, screen: string, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 900 });
  if (screen.startsWith('воспитатель') || screen === 'группа') await teacher(p, this.baseUrl);
  else await head(p, this.baseUrl);
  if (screen === 'группа') {
    await card(p).getByRole('link', { name: 'Открыть группу Звёздочки' }).click();
    await expect(p.getByRole('heading', { level: 1, name: 'Звёздочки', exact: true })).toBeVisible();
    await expect(p.getByRole('heading', { name: 'Сроки и ссылка группы', exact: true })).toBeVisible();
  }
  if (screen === 'пусто') {
    await p.getByLabel('Поиск группы', { exact: true }).fill('Неизвестная группа');
    await expect(main(p)).toContainText('Группы не найдены');
  }
  if (screen.includes('200')) {
    await p.evaluate(() => {
      const elements = [...document.querySelectorAll('#cabinet-main *')];
      const values = elements.map((e) => [e, getComputedStyle(e).fontSize] as const);
      for (const [e, size] of values) (e as HTMLElement).style.fontSize = parseFloat(size) * 2 + 'px';
    });
  }
  if (screen !== 'пусто') await expect(main(p).locator('input[readonly]').first()).toBeVisible();
  await p.evaluate(() => document.fonts.ready);
  expect(await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  await mkdir('reports/e2e/r13-visual', { recursive: true });
  await p.screenshot({ path: 'reports/e2e/r13-visual/' + screen + '-' + width + '.png', fullPage: true });
  if (screen !== 'пусто') await expect(main(p).locator('input[readonly]').first()).toBeVisible();
});
