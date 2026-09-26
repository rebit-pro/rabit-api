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
  id: 'r11-paid',
  accessKey: '1'.repeat(32),
  requestId: 'a'.repeat(32),
  number: 'MF-R11-001',
  groupId: 'sun-stars',
  galleryToken: token,
  institutionName: 'Детский сад «Солнечный»',
  groupName: 'Звёздочки',
  shootName: 'Лето в кадре',
  audience: 'regular',
  createdAt: '2026-09-05T09:00:00Z',
  closesAt: '2026-09-12T15:00:00Z',
  buyer: {
    name: 'Покупатель R11',
    phone: '+7 (900) 111-22-33',
    email: 'buyer-r11@example.test',
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
const seedOrders: OrderSnapshot[] = [
  baseOrder,
  {
    ...baseOrder,
    id: 'r11-unpaid',
    accessKey: '2'.repeat(32),
    requestId: 'c'.repeat(32),
    number: 'MF-R11-002',
    buyer: { ...baseOrder.buyer, name: 'Другой покупатель' },
    paymentStatus: 'unpaid',
    paidAt: undefined,
    paymentAttempts: [],
    digitalPhotos: []
  },
  {
    ...baseOrder,
    id: 'r11-foreign',
    accessKey: '3'.repeat(32),
    requestId: 'd'.repeat(32),
    number: 'MF-FOREIGN',
    groupId: 'school-1a',
    galleryToken: 'other-r11',
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
      galleryToken: 'other-r11',
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
async function appeal(p: Page, base: string, index = 0) {
  const order = (await orders(p))[index]!;
  await go(p, base, '/orders/access/' + order.accessKey);
  await p.getByLabel('Ваш вопрос', { exact: true }).fill('Просим продлить срок приёма: не все успели выбрать фотографии.');
  await choose(p, 'Тема обращения', 'Продлить приём заказов');
  await p.getByTestId('send-support').click();
  await expect(p.getByTestId('support-history').locator('article')).toHaveCount((order.supportRequests?.length ?? 0) + 1);
  const updated = (await orders(p)).find((o) => o.id === order.id)!;
  return '/cabinet/support/' + updated.supportRequests![updated.supportRequests!.length - 1]!.id;
}
async function casePage(p: Page, base: string) {
  const path = await appeal(p, base);
  await go(p, base, path);
  await expect(p.getByTestId('case-detail')).toBeVisible();
  return path;
}
async function save(p: Page, label: string) {
  await p.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
}
async function extendOpen(p: Page, date = '2026-09-20T18:00') {
  await p.getByRole('button', { name: 'Продлить приём', exact: true }).click();
  await p.getByLabel('Новый срок приёма (МСК)', { exact: true }).fill(date);
  await p.getByLabel('Причина продления', { exact: true }).fill('Согласовано по просьбе родителей');
  await p.getByLabel('Подтверждаю новые сроки приёма и доставки для всей группы', { exact: true }).check();
}
async function extend(p: Page, date = '2026-09-20T18:00') {
  await extendOpen(p, date);
  await save(p, 'Подтвердить продление');
  await expect(p.getByTestId('case-detail')).toContainText('Приём продлён');
}
async function reply(p: Page, status = 'В работе', text = 'Проверяем обращение, ответ сохранён в истории.') {
  await p.getByRole('button', { name: 'Ответ и состояние', exact: true }).click();
  await p.getByLabel('Ответ родителю', { exact: true }).fill(text);
  await choose(p, 'Состояние обращения', status);
  await save(p, 'Сохранить ответ');
}
Given('подготовлена область куратора R11', async function (this: CustomWorld) {
  const p = page(this);
  await p.evaluate(
    ({ organization, seedOrders }) => {
      localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(organization));
      localStorage.setItem('morefoto:demo:orders:v1', JSON.stringify(seedOrders));
    },
    { organization, seedOrders }
  );
  await login(p, this.baseUrl);
  await go(p, this.baseUrl);
  await expect(p.getByRole('button', { name: 'Открыть MF-R11-001', exact: true }).filter({ visible: true })).toBeVisible();
});
Then('R11 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'родительское обращение приходит куратору') {
    await appeal(p, base);
    await go(p, base, '/cabinet/support');
    await expect(p.getByRole('button', { name: 'Открыть MF-R11-001-H01', exact: true }).filter({ visible: true })).toBeVisible();
    await p.getByRole('button', { name: 'Открыть MF-R11-001-H01', exact: true }).filter({ visible: true }).click();
    await expect(p.getByTestId('case-detail')).toContainText('buyer-r11@example.test');
    await expect(p.getByTestId('case-detail')).toContainText('Просим продлить срок');
  } else if (name === 'ответ и решение видны родителю') {
    await casePage(p, base);
    await reply(p, 'Решено', 'Вопрос решён, фотографии доступны в вашем заказе.');
    await go(p, base, '/orders/access/' + baseOrder.accessKey);
    await expect(p.getByTestId('support-history')).toContainText('Решено');
    await expect(p.getByTestId('support-history')).toContainText('Вопрос решён, фотографии доступны');
    await expect(p.getByTestId('support-history')).toContainText('Рита');
  } else if (name === 'решённое обращение возвращается в работу') {
    await casePage(p, base);
    await reply(p, 'Решено');
    await expect(p.getByRole('button', { name: 'Продлить приём', exact: true })).toHaveCount(0);
    await reply(p, 'В работе', 'Родитель уточнил вопрос, возвращаем в работу.');
    await expect(p.getByRole('button', { name: 'Продлить приём', exact: true })).toBeEnabled();
    expect((await orders(p))[0]!.supportRequests![0]!.history).toHaveLength(2);
  } else if (name === 'продление меняет даты во всех ролях') {
    await casePage(p, base);
    await extend(p);
    const state = await org(p);
    expect(state.groups[0]!.closesAt).toBe('2026-09-20T15:00:00.000Z');
    expect(state.groups[0]!.sentAt).toBe('2026-09-05T15:00:00.000Z');
    for (const role of ['curator', 'teacher', 'head', 'organizer']) {
      await login(p, base, role);
      await go(p, base, '/cabinet/links?group=sun-stars');
      await expect(p.locator('[data-row-id="sun-stars"]').filter({ visible: true })).toContainText('20 сент.');
      await expect(p.locator('[data-row-id="sun-stars"]').filter({ visible: true })).toContainText('27 сент.');
    }
    await go(p, base, '/orders/access/' + baseOrder.accessKey);
    await expect(p.getByTestId('order-period')).toContainText('20 сентября');
    await expect(p.getByTestId('order-period')).toContainText('27 сентября');
    await go(p, base, '/g/' + token);
    await expect(p.locator('.gallery-conditions time')).toContainText('20 сентября');
  } else if (name === 'закрытая группа открывается после продления') {
    const state = await org(p);
    state.groups[0]!.closesAt = '2026-09-06T15:00:00Z';
    state.groups[0]!.state = 'closed';
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(s)), state);
    await casePage(p, base);
    await extend(p);
    await go(p, base, '/g/' + token);
    await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
    await expect(p.getByTestId('add-to-cart')).toBeVisible();
  } else if (name === 'оплаченный состав и срок файлов сохраняются') {
    await casePage(p, base);
    const before = await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'));
    await extend(p, '2026-10-20T18:00');
    expect(await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'))).toBe(before);
    await go(p, base, '/orders/access/' + baseOrder.accessKey);
    await expect(p.getByTestId('order-total')).toContainText('250');
    await expect(p.getByTestId('order-payment-status')).toContainText('Оплачено');
    await expect(p.getByTestId('support-history')).toContainText('20 октября');
    await expect(p.getByTestId('download-deadline')).toContainText('5 октября');
  } else if (name === 'новая дата причина и подтверждение обязательны') {
    await casePage(p, base);
    await p.getByRole('button', { name: 'Продлить приём', exact: true }).click();
    await p.getByLabel('Новый срок приёма (МСК)').fill('2026-09-12T18:00');
    await p.getByRole('button', { name: 'Подтвердить продление', exact: true }).click();
    await expect(p.getByLabel('Новый срок приёма (МСК)')).toBeFocused();
    await expect(p.getByText('Укажите причину: от 5 до 500 символов.', { exact: true })).toBeVisible();
    expect((await org(p)).groups[0]!.extensions ?? []).toHaveLength(0);
  } else if (name === 'исправление передачи сохраняет продление') {
    await casePage(p, base);
    await extend(p);
    await go(p, base, '/cabinet/links?group=sun-stars');
    await p.getByRole('button', { name: 'Действия: Звёздочки', exact: true }).click();
    await p.getByRole('button', { name: 'Исправить дату передачи', exact: true }).click();
    await p.getByLabel('Дата и время передачи (МСК)').fill('2026-09-01T10:00');
    await p.getByLabel('Причина исправления').fill('Уточнена первоначальная дата передачи');
    await p.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    await expect(p.getByRole('dialog')).toContainText('20 сентября');
    await save(p, 'Исправить дату передачи');
    expect((await org(p)).groups[0]!.closesAt).toBe('2026-09-20T15:00:00.000Z');
    expect((await org(p)).groups[0]!.extensions).toHaveLength(1);
  } else if (name === 'черновик ответа и повтор ошибки') {
    await casePage(p, base);
    const opener = p.getByRole('button', { name: 'Ответ и состояние', exact: true });
    await opener.click();
    await p.getByLabel('Ответ родителю').fill('Сохранённый черновик ответа родителю');
    await p.getByRole('button', { name: 'Отмена', exact: true }).click();
    await expect(opener).toBeFocused();
    await opener.click();
    await expect(p.getByLabel('Ответ родителю')).toHaveValue('Сохранённый черновик ответа родителю');
    await p.evaluate(() =>
      (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest: () => void } }).__MOREFOTO_MOCKS__!.failNextRequest()
    );
    await p.getByRole('button', { name: 'Сохранить ответ', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Проверьте соединение');
    await save(p, 'Сохранить ответ');
    expect((await orders(p))[0]!.supportRequests![0]!.history).toHaveLength(1);
  } else if (name === 'повтор продления не создаёт второй записи') {
    const path = await casePage(p, base);
    await extendOpen(p);
    const other = await this.context!.newPage();
    await go(other, base, path);
    await extendOpen(other);
    await save(p, 'Подтвердить продление');
    const before = await org(p);
    await save(other, 'Подтвердить продление');
    expect(await org(p)).toEqual(before);
    expect(before.groups[0]!.extensions).toHaveLength(1);
    await other.close();
  } else if (name === 'конфликт дат требует актуальных данных') {
    const path = await casePage(p, base);
    await extendOpen(p, '2026-09-21T18:00');
    const other = await this.context!.newPage();
    await go(other, base, path);
    await extendOpen(other, '2026-09-20T18:00');
    await save(other, 'Подтвердить продление');
    await p.getByRole('button', { name: 'Подтвердить продление', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Данные изменились');
    await expect(p.getByLabel('Новый срок приёма (МСК)')).toHaveValue('2026-09-21T18:00');
    await p.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await p.getByLabel('Причина продления').fill('Дополнительное согласование срока');
    await p.getByLabel('Подтверждаю новые сроки приёма и доставки для всей группы').check();
    await save(p, 'Подтвердить продление');
    expect((await org(p)).groups[0]!.extensions).toHaveLength(2);
    await other.close();
  } else if (name === 'конфликт ответа сохраняет черновик') {
    const path = await casePage(p, base);
    await p.getByRole('button', { name: 'Ответ и состояние', exact: true }).click();
    await p.getByLabel('Ответ родителю').fill('Мой ответ, который не должен пропасть');
    const other = await this.context!.newPage();
    await go(other, base, path);
    await reply(other, 'Решено', 'Другой сотрудник уже решил вопрос');
    await p.getByRole('button', { name: 'Сохранить ответ', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Данные изменились');
    await expect(p.getByLabel('Ответ родителю')).toHaveValue('Мой ответ, который не должен пропасть');
    expect((await orders(p))[0]!.supportRequests![0]!.history).toHaveLength(1);
    await other.close();
  } else if (name === 'новая заявка не теряется при ответе') {
    await casePage(p, base);
    await p.getByRole('button', { name: 'Ответ и состояние', exact: true }).click();
    await p.getByLabel('Ответ родителю').fill('Ответ на первое обращение');
    const other = await this.context!.newPage();
    await go(other, base);
    await appeal(other, base);
    await save(p, 'Сохранить ответ');
    expect((await orders(p))[0]!.supportRequests).toHaveLength(2);
    expect((await orders(p))[0]!.supportRequests![0]!.history).toHaveLength(1);
    await other.close();
  } else if (name === 'куратор не видит чужой заказ и обращение') {
    const path = await appeal(p, base, 2);
    await go(p, base);
    await expect(p.locator('#cabinet-main')).not.toContainText('MF-FOREIGN');
    await go(p, base, '/cabinet/orders/r11-foreign');
    await expect(p.getByText('Запись не найдена или недоступна в вашей области.', { exact: true })).toBeVisible();
    await go(p, base, path);
    await expect(p.getByTestId('case-detail')).toHaveCount(0);
    await expect(p.locator('#cabinet-main')).not.toContainText('foreign-secret');
  } else if (name === 'руководитель и ответственный не видят заказы') {
    const path = await casePage(p, base);
    for (const role of ['head', 'teacher']) {
      await login(p, base, role);
      for (const route of ['/cabinet/orders', '/cabinet/orders/r11-paid', path]) {
        await go(p, base, route);
        await expect(p.getByTestId('staff-order-detail')).toHaveCount(0);
        await expect(p.getByTestId('case-detail')).toHaveCount(0);
        await expect(p.locator('body')).not.toContainText('buyer-r11@example.test');
      }
    }
  } else if (name === 'организатор видит все учреждения') {
    await login(p, base, 'organizer');
    await go(p, base);
    await expect(p.getByRole('button', { name: 'Открыть MF-FOREIGN', exact: true }).filter({ visible: true })).toBeVisible();
    await p.getByRole('button', { name: 'Открыть MF-FOREIGN', exact: true }).filter({ visible: true }).click();
    await expect(p.getByTestId('staff-order-detail')).toContainText('foreign-secret@example.test');
    await expect(p.locator('a[href*="/orders/access/"]')).toHaveCount(0);
  } else if (name === 'пустая область и пустой поиск') {
    await p.getByLabel('Поиск заказов').fill('не существует');
    await expect(p.getByText('Записей не найдено', { exact: true })).toBeVisible();
    await login(p, base, 'empty');
    await go(p, base);
    await expect(p.getByText('Записей не найдено', { exact: true })).toBeVisible();
  } else if (name === 'фильтры и возврат из карточки') {
    await p.getByLabel('Поиск заказов').fill('MF-R11-001');
    await choose(p, 'Оплата', 'Оплачено · демонстрация');
    await p.getByLabel('Создан с (МСК)').fill('2026-09-05');
    await p.getByRole('button', { name: 'Открыть MF-R11-001', exact: true }).filter({ visible: true }).click();
    await expect(p).toHaveURL(/payment=paid/);
    await p.getByRole('link', { name: 'К списку заказов', exact: true }).click();
    await expect(p.getByLabel('Поиск заказов')).toHaveValue('MF-R11-001');
    await expect(p.getByLabel('Создан с (МСК)')).toHaveValue('2026-09-05');
    await expect(p.getByRole('button', { name: 'Открыть MF-R11-002', exact: true })).toHaveCount(0);
  } else if (name === 'независимые статусы и поздняя оплата') {
    const state = await orders(p);
    state[0]!.latePayment = true;
    state[0]!.productionStatus = 'printing';
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:orders:v1', JSON.stringify(s)), state);
    await go(p, base);
    await p.getByLabel('Только поздние оплаты').check();
    await choose(p, 'Производство', 'В печати');
    await p.getByRole('button', { name: 'Открыть MF-R11-001', exact: true }).filter({ visible: true }).click();
    await expect(p.getByTestId('staff-order-detail')).toContainText('Оплачено');
    await expect(p.getByTestId('staff-order-detail')).toContainText('В печати');
    await expect(p.getByTestId('staff-order-detail')).toContainText('Нужна проверка поздней оплаты');
  } else if (name === 'отзыв области запрещает сохранение') {
    await casePage(p, base);
    await extendOpen(p);
    const state = await org(p);
    state.institutions[0]!.curatorId = null;
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(s)), state);
    await p.getByRole('button', { name: 'Подтвердить продление', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Заказ недоступен');
    expect((await org(p)).groups[0]!.extensions ?? []).toHaveLength(0);
  } else if (name === 'родитель видит только своё обращение') {
    await appeal(p, base, 0);
    await appeal(p, base, 1);
    await go(p, base, '/orders/access/' + baseOrder.accessKey);
    await expect(p.getByTestId('support-history')).toContainText('MF-R11-001-H01');
    await expect(p.getByTestId('support-history')).not.toContainText('MF-R11-002');
    await expect(p.getByRole('button', { name: 'Продлить приём', exact: true })).toHaveCount(0);
    await go(p, base, '/orders/access/r11-paid');
    await expect(p.getByRole('heading', { name: 'Заказ недоступен', exact: true })).toBeVisible();
  } else if (name === 'новый заказ оплата и обращение связаны') {
    await go(p, base, '/g/' + token);
    await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
    await p.locator('.product-selector').getByRole('combobox').first().click();
    await p.getByRole('option', { name: 'Электронный кадр', exact: true }).click();
    await p.getByTestId('add-to-cart').click();
    await expect(p.locator('.product-notice')).toBeVisible();
    await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
    await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
    await p.getByLabel('Имя покупателя', { exact: true }).fill('Новый покупатель R11');
    await p.getByLabel('Телефон', { exact: true }).fill('+7 (900) 123-45-67');
    await p.getByLabel('Email', { exact: true }).fill('new-r11@example.test');
    await p.getByLabel('Состав и условия проверены', { exact: true }).check();
    await p.getByTestId('create-order').click();
    await expect(p).toHaveURL(/orders\/access\/[a-f0-9]{32}$/);
    await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
    await p.getByTestId('pay-demo').click();
    await expect.poll(async () => (await orders(p))[3]?.paymentStatus).toBe('paid');
    const path = await appeal(p, base, 3);
    await go(p, base, path);
    await expect(p.getByTestId('case-detail')).toContainText('new-r11@example.test');
    await extend(p);
    const stored = (await orders(p))[3]!;
    expect(stored.quote.total).toBe(25000);
    expect(stored.paymentStatus).toBe('paid');
  } else if (name === 'пагинация и сортировка сохраняются') {
    const entries = Array.from({ length: 16 }, (_, i) => ({
      ...structuredClone(baseOrder),
      id: 'page-' + i,
      number: 'PAGE-' + String(i).padStart(2, '0'),
      accessKey: (i + 10).toString(16).padStart(32, '0'),
      quote: { ...baseOrder.quote, total: 25000 + i * 100 }
    }));
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:orders:v1', JSON.stringify(s)), entries);
    await go(p, base);
    await p.getByRole('button', { name: 'Сортировать: Сумма', exact: true }).click();
    await expect(p).toHaveURL(/sort=total/);
    await p.getByRole('button', { name: 'Следующая страница', exact: true }).click();
    await expect(p.getByTestId('ui-table-range')).toHaveText('11–16 из 16');
    await p.getByRole('button', { name: 'Открыть PAGE-10', exact: true }).filter({ visible: true }).click();
    await p.getByRole('link', { name: 'К списку заказов', exact: true }).click();
    await expect(p).toHaveURL(/page=2/);
    await expect(p).toHaveURL(/sort=total/);
    await expect(p.getByTestId('ui-table-range')).toHaveText('11–16 из 16');
  } else if (name === 'ошибка загрузки сохраняет фильтры') {
    await p.getByLabel('Поиск заказов').fill('MF-R11-001');
    await p.evaluate(() =>
      (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest: () => void } }).__MOREFOTO_MOCKS__!.failNextRequest()
    );
    await p.getByRole('button', { name: 'Обновить', exact: true }).click();
    await expect(p.getByRole('alert')).toContainText('Проверьте соединение');
    await p.getByRole('button', { name: 'Повторить', exact: true }).click();
    await expect(p.getByLabel('Поиск заказов')).toHaveValue('MF-R11-001');
    await expect(p.getByRole('button', { name: 'Открыть MF-R11-001', exact: true }).filter({ visible: true })).toBeVisible();
  } else throw new Error('Неизвестная проверка ' + name);
});
Then('R11 проверяет экран {string} шириной {int}', async function (this: CustomWorld, screen: string, width: number) {
  const p = page(this),
    base = this.baseUrl;
  await p.setViewportSize({ width, height: 900 });
  if (screen === 'заказ') await go(p, base, '/cabinet/orders/r11-paid');
  if (['обращения', 'обращение', 'продление', 'ответ', 'текст200'].includes(screen)) {
    const path = await appeal(p, base);
    await go(p, base, screen === 'обращения' ? '/cabinet/support' : path);
  }
  if (screen === 'продление' || screen === 'текст200') await extendOpen(p);
  if (screen === 'ответ') {
    await p.getByRole('button', { name: 'Ответ и состояние', exact: true }).click();
    await p.getByLabel('Ответ родителю').fill('Спасибо за обращение. Проверяем фотографии и согласуем срок.');
  }
  if (screen === 'текст200') await p.addStyleTag({ content: 'html{font-size:200% !important}' });
  await p.evaluate(() => window.scrollTo(0, 0));
  await mkdir('/app/reports/e2e/r11-visual', { recursive: true });
  await p.screenshot({
    path: '/app/reports/e2e/r11-visual/' + screen + '-' + width + '.png',
    fullPage: !(await p.getByRole('dialog').count()),
    animations: 'disabled'
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  if (await p.getByRole('dialog').count()) {
    await expect(p.locator('.admin-footer')).toBeInViewport();
    expect(await p.getByTestId('admin-dialog').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    expect(await p.locator('.admin-body').evaluate((el) => el.clientHeight)).toBeGreaterThanOrEqual(200);
  }
});
