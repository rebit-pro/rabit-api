import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
const token = '158-group-7bc93615c4e94fd18a207d560b3e1f82';
const photo = {
  id: 'sun-stars-A001-1',
  code: 'A001-01',
  thumbSrc: '/demo/gallery-v1/sun-stars/0f21215dfee629dee643-thumb.webp',
  previewSrc: '/demo/gallery-v1/sun-stars/0f21215dfee629dee643-preview.webp',
  width: 1200,
  height: 1800
};
const product = {
  id: 'digital',
  name: 'Электронный кадр',
  description: 'Один файл выбранного снимка. Повторное скачивание в период доступа бесплатно.',
  kind: 'digital' as const,
  price: 25000,
  printCount: 0,
  staffDiscount: true,
  active: true
};
const baseOrder: OrderSnapshot = {
  id: 'r12-paid',
  accessKey: '1'.repeat(32),
  requestId: 'a'.repeat(32),
  number: 'MF-R12-001',
  groupId: 'sun-stars',
  galleryToken: token,
  institutionName: 'Детский сад «Солнечный»',
  groupName: 'Звёздочки',
  shootName: 'Лето в кадре',
  audience: 'regular',
  createdAt: '2026-09-05T09:00:00Z',
  closesAt: '2026-09-12T15:00:00Z',
  buyer: {
    name: 'Покупатель R12',
    phone: '+7 (900) 111-22-33',
    email: 'buyer-r12@example.test',
    comment: 'Комментарий покупателя',
    receiptChannel: 'email'
  },
  quote: {
    lines: [
      {
        id: 'line-1',
        childCode: 'A001',
        photoId: photo.id,
        productId: 'digital',
        quantity: 1,
        product,
        photo,
        unitPrice: 25000,
        total: 25000,
        discount: 0,
        coveredByGift: false
      }
    ],
    total: 25000,
    subtotal: 25000,
    discount: 0,
    giftSaving: 0,
    gifts: [],
    count: 1,
    invalid: [],
    revision: 1
  },
  digitalPhotos: [photo],
  paymentStatus: 'paid',
  paidAt: '2026-09-05T09:10:00Z',
  paymentAttempts: [
    {
      id: 'attempt-1',
      requestId: 'b'.repeat(32),
      amount: 25000,
      status: 'paid',
      startedAt: '2026-09-05T09:00:00Z',
      completedAt: '2026-09-05T09:10:00Z'
    }
  ],
  productionStatus: 'not-started',
  supportRequests: []
};
baseOrder.quote.lines.push({
  ...baseOrder.quote.lines[0]!,
  id: 'line-2',
  productId: 'print',
  product: { ...product, id: 'print', name: 'Фото 15×21', kind: 'physical', price: 10000, printCount: 1 },
  quantity: 2,
  unitPrice: 10000,
  total: 20000
});
baseOrder.quote.total = 45000;
baseOrder.quote.subtotal = 45000;
baseOrder.quote.count = 3;
baseOrder.paymentAttempts![0]!.amount = 45000;
const seedOrders: OrderSnapshot[] = [
  baseOrder,
  {
    ...baseOrder,
    id: 'r12-unpaid',
    accessKey: '2'.repeat(32),
    requestId: 'c'.repeat(32),
    number: 'MF-R12-002',
    buyer: { ...baseOrder.buyer, name: 'Другой покупатель' },
    paymentStatus: 'unpaid',
    paidAt: undefined,
    paymentAttempts: [],
    digitalPhotos: []
  },
  {
    ...baseOrder,
    id: 'r12-foreign',
    accessKey: '3'.repeat(32),
    requestId: 'd'.repeat(32),
    number: 'MF-FOREIGN',
    groupId: 'school-1a',
    galleryToken: 'other-r12',
    institutionName: 'Чужое учреждение',
    groupName: 'Чужой класс',
    buyer: { ...baseOrder.buyer, email: 'foreign-secret@example.test', name: 'Чужой покупатель' }
  }
];
const organization = {
  institutions: [
    { id: 'sun', name: 'Детский сад «Солнечный»', address: 'Учебная улица, 12', curatorId: 102, headId: 103, revision: 1 },
    { id: 'school', name: 'Чужое учреждение', address: 'Учебная улица, 45', curatorId: null, headId: null, revision: 1 }
  ],
  shoots: [
    { id: 'sun-summer-2026', institutionId: 'sun', name: 'Лето в кадре', date: '2026-09-05', revision: 1 },
    { id: 'school-summer-2026', institutionId: 'school', name: 'Другая съёмка', date: null, revision: 1 }
  ],
  groups: [
    {
      id: 'sun-stars',
      institutionId: 'sun',
      shootId: 'sun-summer-2026',
      shootName: 'Лето в кадре',
      name: 'Звёздочки',
      kind: 'regular',
      state: 'open',
      closesAt: '2026-09-12T15:00:00Z',
      teacherId: 104,
      galleryToken: token,
      revision: 1
    },
    {
      id: 'school-1a',
      institutionId: 'school',
      shootId: 'school-summer-2026',
      shootName: 'Другая съёмка',
      name: 'Чужой класс',
      kind: 'regular',
      state: 'open',
      closesAt: '2026-09-12T15:00:00Z',
      teacherId: null,
      galleryToken: 'other-r12',
      revision: 1
    }
  ],
  operations: []
};
const page = (w: CustomWorld) => {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
};
async function go(p: Page, base: string, path = '/cabinet/orders') {
  await p.goto(base + path, { waitUntil: 'networkidle' });
}
async function login(p: Page, base: string, role = 'curator') {
  await p.evaluate(() => {
    for (const k of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + k);
  });
  await go(p, base, '/login');
  await p.getByLabel('Email', { exact: true }).fill(role + '@morefoto.test');
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function orders(p: Page): Promise<OrderSnapshot[]> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1')!));
}
async function org(p: Page): Promise<OrganizationState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:organization:v1')!));
}
async function choose(p: Page, label: string, value: string) {
  await p.getByLabel(label, { exact: true }).locator('..').click();
  await p.getByRole('option', { name: value, exact: true }).click();
  if (await p.getByRole('dialog').count()) await p.getByRole('dialog').locator('h2').click();
  else await p.locator('h1').click();
}

const confirmLabel = 'Подтверждаю изменения и последствия для оплаты, файлов и исполнения';
async function card(p: Page, base: string) {
  await go(p, base, '/cabinet/orders/r12-paid');
  await expect(p.getByTestId('settlement-panel')).toBeVisible();
}
async function open(p: Page, label: string) {
  await p.getByTestId('settlement-panel').getByRole('button', { name: label, exact: true }).click();
  await expect(p.getByRole('dialog')).toBeVisible();
}
async function fillConfirm(p: Page, reason = 'Согласовано с родителем по обращению') {
  await p.getByLabel('Причина операции', { exact: true }).fill(reason);
  await p.getByLabel(confirmLabel, { exact: true }).check();
}
async function submit(p: Page) {
  await p.getByRole('button', { name: 'Подтвердить операцию', exact: true }).click();
  await expect(p.getByRole('dialog')).toBeHidden();
}
async function startRefund(p: Page, amount = '100', files = 'Сохранить доступ') {
  await open(p, 'Оформить возврат');
  await p.getByLabel('Сумма возврата, ₽', { exact: true }).fill(amount);
  await choose(p, 'Последующая выдача файлов', files);
  await fillConfirm(p);
  await submit(p);
  await expect(p.getByTestId('sale-refund').last()).toContainText('Обрабатывается');
}
async function result(p: Page, status = 'Подтверждён') {
  await open(p, 'Результат возврата');
  await choose(p, 'Результат возврата (демо)', status);
  await fillConfirm(p, 'Получен окончательный результат возврата');
  await submit(p);
}
async function patch(p: Page, value: Partial<OrderSnapshot>) {
  await p.evaluate((value) => {
    const rows = JSON.parse(localStorage.getItem('morefoto:demo:orders:v1')!);
    Object.assign(rows[0], value);
    localStorage.setItem('morefoto:demo:orders:v1', JSON.stringify(rows));
  }, value);
}
async function parent(p: Page, base: string) {
  await go(p, base, '/orders/access/' + baseOrder.accessKey);
}
async function contacts(p: Page, email = 'fixed-r12@example.test') {
  await open(p, 'Исправить контакты');
  await p.getByLabel('Проверенный email', { exact: true }).fill(email);
  await p.getByLabel('Заказ и контакт проверены по обращению родителя', { exact: true }).check();
  await fillConfirm(p);
}
async function late(p: Page, base: string, decision = 'Исполнить заказ') {
  await patch(p, { latePayment: true });
  await card(p, base);
  await open(p, 'Согласовать исполнение');
  await choose(p, 'Решение по исполнению', decision);
  await fillConfirm(p);
  await submit(p);
}
Given('подготовлено сопровождение R12', async function (this: CustomWorld) {
  const p = page(this);
  await p.evaluate(
    ({ organization, seedOrders }) => {
      localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(organization));
      localStorage.setItem('morefoto:demo:orders:v1', JSON.stringify(seedOrders));
    },
    { organization, seedOrders }
  );
  await login(p, this.baseUrl);
  await card(p, this.baseUrl);
});
Then('R12 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'исправленные контакты видны родителю и в поиске') {
    await contacts(p);
    await submit(p);
    await parent(p, base);
    await expect(p.locator('.order-contacts')).toContainText('fixed-r12@example.test');
    await expect(p.getByTestId('sale-correction')).toContainText('buyer-r12@example.test');
    await go(p, base);
    await p.getByLabel('Поиск заказов').fill('fixed-r12@');
    await expect(p.getByRole('button', { name: 'Открыть MF-R12-001', exact: true }).filter({ visible: true })).toBeVisible();
    expect((await orders(p))[0]!.buyer.email).toBe('buyer-r12@example.test');
  } else if (name === 'контакты требуют проверки и корректных полей') {
    await open(p, 'Исправить контакты');
    await p.getByLabel('Проверенный email').fill('bad');
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.getByLabel('Проверенный email')).toBeFocused();
    await expect(p.getByRole('dialog')).toContainText('Подтвердите проверку');
    expect((await orders(p))[0]!.settlement).toBeUndefined();
  } else if (name === 'замена кадра обновляет последующие файлы и ZIP') {
    await open(p, 'Исправить позицию');
    await choose(p, 'Новый кадр', 'A001-02');
    await fillConfirm(p);
    await submit(p);
    await parent(p, base);
    await expect(p.getByTestId('order-downloads')).toContainText('A001-02');
    await expect(p.getByTestId('order-downloads')).not.toContainText('A001-01');
    const download = p.waitForEvent('download');
    await p.getByTestId('download-archive').click();
    expect((await download).suggestedFilename()).toContain('.zip');
    const stored = (await orders(p))[0]!;
    expect(stored.digitalPhotos).toEqual(baseOrder.digitalPhotos);
    expect(stored.quote).toEqual(baseOrder.quote);
    expect(stored.settlement!.preparedFiles[0]!.photoIds).not.toContain(photo.id);
  } else if (name === 'уменьшение печатной позиции приостанавливает исполнение') {
    await open(p, 'Исправить позицию');
    await choose(p, 'Позиция заказа', 'Фото 15×21 · A001-01 · 2 шт.');
    await p.getByLabel('Новое количество').fill('1');
    await fillConfirm(p);
    await submit(p);
    await expect(p.getByTestId('settlement-history')).toContainText('Дальнейшее исполнение приостановлено');
    expect((await orders(p))[0]!.quote.total).toBe(45000);
    await open(p, 'Согласовать исполнение');
    await fillConfirm(p, 'Состав проверен для дальнейшего изготовления');
    await submit(p);
    expect((await orders(p))[0]!.settlement!.hold).toBe(false);
  } else if (name === 'начатая печать сохраняется и требует перепечатки') {
    await patch(p, { productionStatus: 'printing' });
    await card(p, base);
    await open(p, 'Исправить позицию');
    await choose(p, 'Позиция заказа', 'Фото 15×21 · A001-01 · 2 шт.');
    await p.getByLabel('Новое количество').fill('1');
    await fillConfirm(p);
    await submit(p);
    await expect(p.getByTestId('settlement-history')).toContainText('Требуется согласование перепечатки');
    expect((await orders(p))[0]!.productionStatus).toBe('printing');
    await open(p, 'Согласовать исполнение');
    await fillConfirm(p, 'Перепечатка согласована с родителем');
    await submit(p);
    expect((await orders(p))[0]!.settlement!.reprintApproved).toBe(true);
    expect((await orders(p))[0]!.productionStatus).toBe('printing');
  } else if (name === 'увеличение количества не создаёт неоплаченные позиции') {
    await open(p, 'Исправить позицию');
    await p.getByLabel('Новое количество').fill('2');
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.getByLabel('Новое количество')).toBeFocused();
    expect((await orders(p))[0]!.settlement).toBeUndefined();
  } else if (name === 'возврат в обработке резервирует остаток') {
    await startRefund(p, '100');
    await expect(p.getByTestId('settlement-totals')).toContainText('450 ₽');
    await open(p, 'Оформить возврат');
    await expect(p.getByRole('dialog')).toContainText('350 ₽');
    await p.getByLabel('Сумма возврата, ₽').fill('351');
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.getByLabel('Сумма возврата, ₽')).toBeFocused();
    expect((await orders(p))[0]!.settlement!.refunds).toHaveLength(1);
  } else if (name === 'частичный возврат меняет итог учреждения без контактов') {
    await startRefund(p);
    await result(p);
    await expect(p.getByTestId('settlement-totals')).toContainText('350 ₽');
    await login(p, base, 'head');
    await go(p, base, '/cabinet/institutions/sun');
    await expect(p.getByTestId('settlement-totals')).toContainText('350 ₽');
    await expect(p.locator('#cabinet-main')).not.toContainText('buyer-r12');
    await expect(p.locator('#cabinet-main')).not.toContainText('MF-R12-001');
    await login(p, base, 'teacher');
    await go(p, base, '/cabinet/institutions/sun');
    await expect(p.getByTestId('settlement-totals')).toHaveCount(0);
  } else if (name === 'ошибка возврата освобождает сумму и повторяет ту же запись') {
    await startRefund(p, '450');
    await result(p, 'Ошибка возврата');
    expect((await orders(p))[0]!.settlement!.refunds[0]!.status).toBe('failed');
    await open(p, 'Повторить возврат');
    await fillConfirm(p);
    await submit(p);
    expect((await orders(p))[0]!.settlement!.refunds).toHaveLength(1);
    await result(p);
    const r = (await orders(p))[0]!.settlement!.refunds[0]!;
    expect(r.history.map((h) => h.status)).toEqual(['pending', 'failed', 'pending', 'confirmed']);
  } else if (name === 'полный возврат прекращает новые файлы и сохраняет выданное') {
    await parent(p, base);
    const download = p.waitForEvent('download');
    await p.getByTestId('download-archive').click();
    await download;
    await card(p, base);
    await startRefund(p, '450', 'Прекратить выдачу всех файлов');
    await result(p);
    await parent(p, base);
    await expect(p.getByTestId('order-downloads')).toContainText('выдача файлов прекращена');
    await expect(p.getByTestId('download-archive')).toHaveCount(0);
    const o = (await orders(p))[0]!;
    expect(o.digitalPhotos).toEqual(baseOrder.digitalPhotos);
    expect(o.settlement!.preparedFiles).toHaveLength(1);
    expect(o.paymentStatus).toBe('paid');
    expect(o.paidAt).toBe(baseOrder.paidAt);
  } else if (name === 'возврат печатной позиции сохраняет электронные файлы') {
    await open(p, 'Оформить возврат');
    await choose(p, 'Способ расчёта возврата', 'Позиции заказа');
    await choose(p, 'Позиции для возврата', 'Фото 15×21 · A001-01 · остаток 200 ₽');
    await choose(p, 'Последующая выдача файлов', 'Прекратить выдачу выбранных файлов');
    await fillConfirm(p);
    await submit(p);
    await result(p);
    await parent(p, base);
    await expect(p.getByTestId('download-archive')).toBeVisible();
    expect((await orders(p))[0]!.settlement!.refunds[0]!.amount).toBe(20000);
  } else if (name === 'два частичных возврата не превышают оплату') {
    await startRefund(p, '300');
    await result(p);
    await startRefund(p, '150');
    await result(p);
    expect((await orders(p))[0]!.settlement!.refunds.reduce((n, r) => n + r.amount, 0)).toBe(45000);
    await expect(p.getByRole('button', { name: 'Оформить возврат', exact: true })).toBeDisabled();
  } else if (name === 'поздняя оплата разрешает исполнение без нового списания') {
    await late(p, base);
    await parent(p, base);
    await expect(p.getByTestId('order-late-decision')).toContainText('исполнение согласовано');
    await expect(p.getByTestId('download-archive')).toBeVisible();
    const o = (await orders(p))[0]!;
    expect(o.latePayment).toBe(true);
    expect(o.paymentAttempts).toEqual(baseOrder.paymentAttempts);
    expect(o.paidAt).toBe(baseOrder.paidAt);
  } else if (name === 'поздняя оплата возвращается через подтверждение') {
    await late(p, base, 'Вернуть доступный остаток');
    expect((await orders(p))[0]!.settlement!.refunds[0]!.status).toBe('pending');
    await result(p);
    await parent(p, base);
    await expect(p.getByTestId('order-downloads')).toContainText('согласован возврат');
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов0 ₽');
  } else if (name === 'исполнение не продлевает истёкший месячный срок') {
    await patch(p, { paidAt: '2026-07-31T09:10:00Z', latePayment: true });
    await card(p, base);
    await open(p, 'Согласовать исполнение');
    await fillConfirm(p);
    await submit(p);
    await parent(p, base);
    await expect(p.getByTestId('order-downloads')).toContainText('Срок доступа истёк');
    await expect(p.getByTestId('download-archive')).toHaveCount(0);
  } else if (name === 'повторное получение исправляет email и сохраняет срок') {
    await open(p, 'Повторное получение');
    await p.getByLabel('Проверенный email').fill('restored@example.test');
    await p.getByLabel('Заказ и контакт проверены по обращению родителя').check();
    await fillConfirm(p);
    await submit(p);
    await parent(p, base);
    await expect(p.locator('.order-contacts')).toContainText('restored@example.test');
    await expect(p.getByTestId('sale-recovery')).toContainText('Отправка имитирована');
    expect((await orders(p))[0]!.paidAt).toBe(baseOrder.paidAt);
  } else if (name === 'ошибка письма сохранена и доступ можно повторить') {
    await open(p, 'Повторное получение');
    await p.getByLabel('Заказ и контакт проверены по обращению родителя').check();
    await choose(p, 'Результат письма (демо)', 'Ошибка доставки');
    await fillConfirm(p);
    await submit(p);
    await expect(p.getByTestId('sale-recovery')).toContainText('Ошибка доставки');
    await open(p, 'Повторное получение');
    await p.getByLabel('Заказ и контакт проверены по обращению родителя').check();
    await fillConfirm(p);
    await submit(p);
    expect((await orders(p))[0]!.settlement!.recoveries.map((r) => r.status)).toEqual(['failed', 'sent']);
  } else if (name === 'восстановление не обходит полный возврат') {
    await startRefund(p, '450', 'Прекратить выдачу всех файлов');
    await result(p);
    await open(p, 'Повторное получение');
    await p.getByLabel('Заказ и контакт проверены по обращению родителя').check();
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('Повторная выдача недоступна');
  } else if (name === 'черновик переживает закрытие и сетевую ошибку') {
    await contacts(p);
    await p.getByRole('button', { name: 'Закрыть редактор' }).click();
    await open(p, 'Исправить контакты');
    await expect(p.getByRole('dialog')).toContainText('Восстановлен');
    await expect(p.getByLabel('Проверенный email')).toHaveValue('fixed-r12@example.test');
    await p.evaluate(() =>
      (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest: () => void } }).__MOREFOTO_MOCKS__!.failNextRequest()
    );
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('Проверьте соединение');
    await submit(p);
    expect((await orders(p))[0]!.settlement!.corrections).toHaveLength(1);
  } else if (name === 'потерянный ответ не создаёт второй возврат') {
    await open(p, 'Оформить возврат');
    await p.getByLabel('Сумма возврата, ₽').fill('100');
    await fillConfirm(p);
    await p.evaluate(() => localStorage.setItem('morefoto:demo:settlement:lose-response-once', 'true'));
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('не продублируется');
    await submit(p);
    expect((await orders(p))[0]!.settlement!.refunds).toHaveLength(1);
  } else if (name === 'конкурентный возврат требует актуального остатка') {
    const other = await p.context().newPage();
    await card(other, base);
    await open(other, 'Оформить возврат');
    await fillConfirm(other);
    await startRefund(p, '300');
    await other.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(other.locator('.management-error')).toContainText('измен');
    await other.getByRole('button', { name: 'Загрузить актуальные данные' }).click();
    await expect(other.getByLabel('Сумма возврата, ₽')).toHaveValue('150.00');
    await other.close();
  } else if (name === 'изменение производства требует повторной проверки формы') {
    await open(p, 'Оформить возврат');
    await fillConfirm(p);
    await patch(p, { productionStatus: 'printing' });
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('измен');
    expect((await orders(p))[0]!.settlement).toBeUndefined();
  } else if (name === 'отзыв области запрещает сохранение') {
    await contacts(p);
    const state = await org(p);
    state.institutions[0]!.curatorId = null;
    await p.evaluate((state) => localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(state)), state);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('недоступен');
    expect((await orders(p))[0]!.settlement).toBeUndefined();
  } else if (name === 'чужие роли и заказ не открывают операции') {
    await go(p, base, '/cabinet/orders/r12-foreign');
    await expect(p.getByTestId('settlement-panel')).toHaveCount(0);
    for (const role of ['head', 'teacher']) {
      await login(p, base, role);
      await go(p, base, '/cabinet/orders/r12-paid');
      await expect(p.getByTestId('settlement-panel')).toHaveCount(0);
    }
    await parent(p, base);
    await expect(p.getByRole('button', { name: 'Оформить возврат', exact: true })).toHaveCount(0);
  } else if (name === 'фильтр возврата сохраняется при возврате из карточки') {
    await startRefund(p);
    await result(p);
    await go(p, base);
    await choose(p, 'Возвраты и решения', 'Частичный возврат');
    await expect(p.getByRole('button', { name: 'Открыть MF-R12-002', exact: true }).filter({ visible: true })).toHaveCount(0);
    await p.getByRole('button', { name: 'Открыть MF-R12-001', exact: true }).filter({ visible: true }).click();
    await p.getByRole('link', { name: 'К списку заказов', exact: true }).click();
    await expect(p.getByLabel('Возвраты и решения')).toHaveValue('Частичный возврат');
  } else if (name === 'новое обращение не теряется при исправлении контакта') {
    await contacts(p);
    const other = await p.context().newPage();
    await parent(other, base);
    await other.getByLabel('Ваш вопрос').fill('Просим исправить email для получения фотографий.');
    await other.getByTestId('send-support').click();
    await expect(other.getByTestId('support-history').locator('article')).toHaveCount(1);
    await submit(p);
    expect((await orders(p))[0]!.supportRequests).toHaveLength(1);
    await other.close();
  } else if (name === 'подтверждение после начала печати не отменяет изготовление') {
    await open(p, 'Оформить возврат');
    await choose(p, 'Дальнейшее исполнение', 'Приостановить до согласования');
    await fillConfirm(p);
    await submit(p);
    await patch(p, { productionStatus: 'printing' });
    await card(p, base);
    await result(p);
    expect((await orders(p))[0]!.productionStatus).toBe('printing');
    expect((await orders(p))[0]!.settlement!.refunds[0]!.holdApplied).toBe(false);
    await expect(p.getByTestId('sale-refund')).toContainText('Автоматическая остановка не выполнена');
  } else if (name === 'невозможно исполнить полностью возвращённый поздний заказ') {
    await late(p, base, 'Вернуть доступный остаток');
    await result(p);
    await open(p, 'Согласовать исполнение');
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('Нельзя согласовать исполнение');
  } else if (name === 'операция связана с обращением у родителя и куратора') {
    await parent(p, base);
    await p.getByLabel('Ваш вопрос').fill('Просим исправить контакт и вернуть часть суммы заказа.');
    await p.getByTestId('send-support').click();
    await expect(p.getByTestId('support-history').locator('article')).toHaveCount(1);
    const id = (await orders(p))[0]!.supportRequests![0]!.id;
    await go(p, base, '/cabinet/support/' + id);
    await p.getByRole('link', { name: 'Заказ MF-R12-001', exact: true }).click();
    await startRefund(p);
    await result(p);
    expect((await orders(p))[0]!.settlement!.refunds[0]!.supportId).toBe(id);
    await go(p, base, '/cabinet/support/' + id);
    await expect(p.getByTestId('related-settlement')).toContainText('подтверждён');
    await parent(p, base);
    await expect(p.getByTestId('related-settlement')).toContainText('Возврат 100');
  } else if (name === 'выбранный электронный возврат прекращает только его выдачу') {
    await open(p, 'Оформить возврат');
    await choose(p, 'Способ расчёта возврата', 'Позиции заказа');
    await choose(p, 'Позиции для возврата', 'Электронный кадр · A001-01 · остаток 250 ₽');
    await choose(p, 'Последующая выдача файлов', 'Прекратить выдачу выбранных файлов');
    await fillConfirm(p);
    await submit(p);
    await result(p);
    await parent(p, base);
    await expect(p.getByTestId('order-downloads')).toContainText('выдача файлов прекращена');
    expect((await orders(p))[0]!.settlement!.refunds[0]!.fileIds).toEqual([photo.id]);
  } else if (name === 'полный возврат может явно сохранить доступ к файлам') {
    await startRefund(p, '450');
    await result(p);
    await parent(p, base);
    await expect(p.getByTestId('download-archive')).toBeVisible();
    expect((await orders(p))[0]!.settlement!.refunds[0]!.fileIds).toEqual([]);
  } else if (name === 'повтор ошибки не превышает изменившийся остаток') {
    await startRefund(p, '100');
    await result(p, 'Ошибка возврата');
    await startRefund(p, '400');
    await result(p);
    await open(p, 'Повторить возврат');
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('Остаток изменился');
    expect((await orders(p))[0]!.settlement!.refunds[0]!.status).toBe('failed');
  } else if (name === 'ожидающий возврат блокирует изменение позиции') {
    await startRefund(p);
    await open(p, 'Исправить позицию');
    await choose(p, 'Новый кадр', 'A001-02');
    await fillConfirm(p);
    await p.getByRole('button', { name: 'Подтвердить операцию' }).click();
    await expect(p.locator('.management-error')).toContainText('Дождитесь результата возврата');
  } else if (name === 'возврат во время подготовки архива запрещает выдачу') {
    await startRefund(p, '450', 'Прекратить выдачу всех файлов');
    const staff = await p.context().newPage();
    await card(staff, base);
    await parent(p, base);
    let release: () => void = () => {};
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    let requested = false;
    await p.route('**/*-preview.webp', async (route) => {
      requested = true;
      await gate;
      await route.continue();
    });
    await p.getByTestId('download-archive').click();
    await expect.poll(() => requested).toBe(true);
    await result(staff);
    release();
    await expect(p.getByTestId('delivery-error')).toContainText('недоступно');
    expect((await orders(p))[0]!.settlement!.preparedFiles).toHaveLength(0);
    await staff.close();
  } else throw new Error('Неизвестный сценарий ' + name);
});
Then('R12 проверяет экран {string} шириной {int}', async function (this: CustomWorld, screen: string, width: number) {
  const p = page(this),
    base = this.baseUrl;
  await p.setViewportSize({ width, height: 900 });
  await card(p, base);
  if (screen === 'карточка') {
    await startRefund(p);
    await result(p);
  } else if (screen === 'список') {
    await go(p, base);
  } else if (screen === 'контакты') {
    await contacts(p);
  } else if (screen === 'позиция') {
    await open(p, 'Исправить позицию');
    await choose(p, 'Новый кадр', 'A001-02');
    await fillConfirm(p);
  } else if (screen === 'возврат' || screen === 'текст200') {
    await open(p, 'Оформить возврат');
    await p.getByLabel('Сумма возврата, ₽').fill('100');
    await fillConfirm(p);
  } else if (screen === 'поздняя') {
    await patch(p, { latePayment: true });
    await card(p, base);
    await open(p, 'Согласовать исполнение');
    await choose(p, 'Решение по исполнению', 'Вернуть доступный остаток');
    await fillConfirm(p);
  } else if (screen === 'родитель') {
    await startRefund(p);
    await result(p);
    await parent(p, base);
  } else throw new Error(screen);
  if (screen === 'список' && width === 1440)
    await expect.poll(() => p.locator('.ui-table-desktop').evaluate((e) => e.scrollWidth <= e.clientWidth + 1)).toBe(true);
  if (screen === 'текст200') await p.addStyleTag({ content: 'html{font-size:200% !important}' });
  await expect.poll(() => p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  if (await p.getByRole('dialog').count()) {
    await expect(p.getByRole('button', { name: 'Подтвердить операцию' })).toBeInViewport();
    expect(await p.locator('.admin-body').evaluate((e) => e.clientHeight)).toBeGreaterThanOrEqual(200);
  }
  if (await p.getByRole('dialog').count())
    await p.locator('.admin-body').evaluate((e) => {
      e.scrollTop = 0;
    });
  await mkdir('reports/e2e/r12-visual', { recursive: true });
  await p.evaluate(() => scrollTo(0, 0));
  await p.screenshot({
    path: `reports/e2e/r12-visual/${screen}-${width}.png`,
    fullPage: !(await p.getByRole('dialog').count()),
    animations: 'disabled'
  });
});
