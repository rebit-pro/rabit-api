import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import { galleryChildren } from '../../../src/modules/morefoto/gallery/mocks/photos.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { PhotoState } from '../../../src/modules/morefoto/photos/types.js';
const token = '158-group-7bc93615c4e94fd18a207d560b3e1f82';
const linkPath = '/cabinet/links?group=sun-stars';
const sourcePhotos = galleryChildren['sun-stars']![0]!.photos.map((p, index) => ({
  ...p,
  groupId: 'sun-stars',
  originalGroupId: 'sun-stars',
  shootId: 'sun-summer-2026',
  childCode: 'A001',
  sequence: index + 1,
  filename: p.code + '.webp',
  bytes: 0,
  fingerprint: p.id,
  source: 'seed' as const,
  revision: 1
}));
const organization = {
  institutions: [
    { id: 'sun', name: 'Детский сад «Солнечный»', address: 'Учебный адрес, 12', curatorId: 102, headId: 103, revision: 1 },
    { id: 'school', name: 'Другое учреждение', address: 'Учебный адрес, 45', curatorId: null, headId: null, revision: 1 }
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
      state: 'preparing',
      closesAt: null,
      teacherId: 104,
      galleryToken: token,
      revision: 1
    },
    {
      id: 'sun-staff',
      institutionId: 'sun',
      shootId: 'sun-summer-2026',
      shootName: 'Лето в кадре',
      name: 'Сотрудники',
      kind: 'staff',
      state: 'preparing',
      closesAt: null,
      teacherId: null,
      galleryToken: 'staff-r10',
      revision: 1
    },
    {
      id: 'school-1a',
      institutionId: 'school',
      shootId: 'school-summer-2026',
      shootName: 'Другая съёмка',
      name: 'Чужой класс',
      kind: 'regular',
      state: 'preparing',
      closesAt: null,
      teacherId: null,
      galleryToken: 'other-r10',
      revision: 1
    }
  ],
  operations: []
};
function page(w: CustomWorld) {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
}
async function login(p: Page, base: string, role = 'organizer') {
  await p.evaluate(() => {
    for (const key of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + key);
  });
  await p.goto(base + '/login', { waitUntil: 'networkidle' });
  await p.getByLabel('Email', { exact: true }).fill(role + '@morefoto.test');
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function go(p: Page, base: string, path = linkPath) {
  await p.goto(base + path, { waitUntil: 'networkidle' });
}
async function org(p: Page): Promise<OrganizationState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:organization:v1')!));
}
async function photos(p: Page): Promise<PhotoState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:photos:v1')!));
}
async function save(p: Page, label: string) {
  await p.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
}
async function ready(p: Page, base: string) {
  await go(p, base);
  await p.getByRole('button', { name: 'Проверить ссылку', exact: true }).click();
  for (const name of [
    'Фотографии и коды проверены',
    'Продукция, цены и условия группы проверены',
    'Списки сотрудников и ответственные проверены'
  ])
    await p.getByLabel(name, { exact: true }).check();
  await save(p, 'Проверить ссылку');
  await expect(p.getByRole('button', { name: 'Отметить передачу', exact: true })).toBeVisible();
}
async function transmit(p: Page) {
  await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
  await p.getByLabel('Дата и время передачи (МСК)', { exact: true }).fill('2026-09-07T10:15');
  await p.getByLabel('Подтверждаю факт передачи и указанные сроки', { exact: true }).check();
  await save(p, 'Отметить передачу ссылки');
}
async function newRequest(p: Page, base: string, code = 'A001-01') {
  await go(p, base, '/cabinet/staff-requests');
  await p.getByRole('button', { name: 'Новый список', exact: true }).click();
  await p.getByLabel('Код ребёнка или снимка 1', { exact: true }).fill(code);
}
async function submit(p: Page, base: string) {
  await newRequest(p, base);
  await save(p, 'Передать список куратору');
  await p.getByRole('link', { name: 'Открыть список', exact: true }).click();
  await expect(p.getByTestId('request-detail')).toBeVisible();
  return p.url().replace(base, '');
}
async function review(p: Page) {
  await p.getByRole('button', { name: 'Проверить и перенести', exact: true }).click();
  await expect(p.getByRole('dialog').locator('.handoff-previews img')).toHaveCount(sourcePhotos.length);
}
async function confirm(p: Page) {
  await p.getByLabel('Проверены все кадры, подтверждаю перенос наборов', { exact: true }).check();
  await save(p, 'Подтвердить перенос');
  await expect(p.getByTestId('request-detail')).toContainText('Проверен и перенесён');
}
async function addCart(p: Page, base: string) {
  await go(p, base, '/g/' + token);
  await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await p.getByRole('option', { name: 'Электронный кадр', exact: true }).click();
  await p.getByTestId('add-to-cart').click();
  await expect(p.locator('.product-notice')).toBeVisible();
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
}
Given('подготовлена рабочая область R10', async function (this: CustomWorld) {
  const p = page(this);
  await p.evaluate(
    ({ organization, sourcePhotos }) => {
      localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(organization));
      localStorage.setItem(
        'morefoto:demo:photos:v1',
        JSON.stringify({ photos: sourcePhotos, covers: { 'sun-stars': sourcePhotos[0]!.id } })
      );
    },
    { organization, sourcePhotos }
  );
  await login(p, this.baseUrl);
  await go(p, this.baseUrl);
});
Then('R10 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'передача открывает семь дней') {
    await ready(p, base);
    await login(p, base, 'teacher');
    await go(p, base);
    await transmit(p);
    const group = (await org(p)).groups[0]!;
    expect(group.sentAt).toBe('2026-09-07T07:15:00.000Z');
    expect(group.closesAt).toBe('2026-09-14T07:15:00.000Z');
    await go(p, base, '/g/' + token);
    await expect(p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true })).toBeVisible();
    await p.getByRole('button', { name: 'Получение и помощь', exact: true }).click();
    await expect(p.getByRole('dialog')).toContainText('21 сентября');
  } else if (name === 'копирование не запускает срок') {
    await this.context!.grantPermissions(['clipboard-read', 'clipboard-write']);
    const before = await org(p);
    await p.getByRole('button', { name: 'Копировать ссылку', exact: true }).click();
    await expect(p.getByRole('status')).toContainText('Дата передачи не изменена');
    expect(await org(p)).toEqual(before);
    expect(await p.evaluate(() => navigator.clipboard.readText())).toBe(base + '/g/' + token);
  } else if (name === 'исправление сохраняет историю') {
    await ready(p, base);
    await transmit(p);
    await login(p, base, 'curator');
    await go(p, base);
    await p.getByRole('button', { name: 'Исправить дату', exact: true }).click();
    await p.getByLabel('Дата и время передачи (МСК)').fill('2026-08-30T09:00');
    await p.getByLabel('Причина исправления').fill('Уточнено время передачи родителям');
    await p.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    await expect(p.getByRole('dialog')).toContainText('До исправления');
    await save(p, 'Исправить дату передачи');
    await expect(p.getByTestId('link-sun-stars')).toContainText('Приём завершён');
    const g = (await org(p)).groups[0]!;
    expect(g.linkHistory![g.linkHistory!.length - 1]!.previousClosesAt).toBe('2026-09-14T07:15:00.000Z');
    expect(g.closesAt).toBe('2026-09-06T06:00:00.000Z');
    await go(p, base, '/g/' + token);
    await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
    await expect(p.getByTestId('add-to-cart')).toHaveCount(0);
  } else if (name === 'будущая дата и подтверждение') {
    await ready(p, base);
    await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    await p.getByLabel('Дата и время передачи (МСК)').fill('2026-09-08T12:00');
    await p.getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    await expect(p.getByLabel('Дата и время передачи (МСК)')).toBeFocused();
    await expect(p.getByText('Подтвердите факт передачи и показанные сроки.', { exact: true })).toBeVisible();
    expect((await org(p)).groups[0]!.sentAt).toBeUndefined();
  } else if (name === 'черновик и повтор после ошибки') {
    await ready(p, base);
    const opener = p.getByRole('button', { name: 'Отметить передачу', exact: true });
    await opener.click();
    await p.getByLabel('Дата и время передачи (МСК)').fill('2026-09-06T08:00');
    await p.getByRole('button', { name: 'Отмена', exact: true }).click();
    await expect(opener).toBeFocused();
    await opener.click();
    await expect(p.getByRole('dialog')).toContainText('Восстановлен несохранённый черновик');
    await p.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    await p.evaluate(() =>
      (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest: () => void } }).__MOREFOTO_MOCKS__!.failNextRequest()
    );
    await p.getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Проверьте соединение');
    await expect(p.getByLabel('Дата и время передачи (МСК)')).toHaveValue('2026-09-06T08:00');
    await save(p, 'Отметить передачу ссылки');
    expect((await org(p)).groups[0]!.linkHistory!.filter((e) => e.kind === 'transmitted')).toHaveLength(1);
  } else if (name === 'изменение условий требует проверки') {
    await ready(p, base);
    await go(p, base, '/cabinet/catalog');
    await p.getByRole('button', { name: 'Редактировать Электронный кадр', exact: true }).filter({ visible: true }).click();
    await p.getByLabel('Цена, ₽', { exact: true }).fill('275');
    await save(p, 'Сохранить');
    await go(p, base);
    await expect(p.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);
    await expect(p.getByTestId('link-sun-stars')).toContainText('Требует проверки');
  } else if (name === 'устаревшая передача не перезапускает срок') {
    await ready(p, base);
    const other = await this.context!.newPage();
    await go(other, base);
    await other.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    await other.getByLabel('Дата и время передачи (МСК)').fill('2026-09-06T08:00');
    await other.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    await transmit(p);
    const before = await org(p);
    await save(other, 'Отметить передачу ссылки');
    expect(await org(p)).toEqual(before);
    await other.close();
  } else if (name === 'руководитель только читает') {
    await login(p, base, 'head');
    await go(p, base, '/cabinet/links');
    await expect(p.locator('[data-testid^="link-"]')).toHaveCount(2);
    await expect(p.getByRole('button', { name: 'Проверить ссылку', exact: true })).toHaveCount(0);
    await expect(p.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);
    await go(p, base, '/cabinet/staff-requests');
    await expect(p.getByRole('button', { name: 'Новый список', exact: true })).toHaveCount(0);
  } else if (name === 'ответственный видит свои группы') {
    await login(p, base, 'teacher');
    await go(p, base, '/cabinet/links');
    await expect(p.locator('[data-testid^="link-"]')).toHaveCount(1);
    await expect(p.getByTestId('link-sun-stars')).toBeVisible();
    await newRequest(p, base);
    await expect(p.getByRole('dialog')).not.toContainText('Чужой класс');
  } else if (name === 'куратор видит своё учреждение') {
    await login(p, base, 'curator');
    await go(p, base, '/cabinet/links');
    await expect(p.locator('[data-testid^="link-"]')).toHaveCount(2);
    await expect(p.getByTestId('link-school-1a')).toHaveCount(0);
    await go(p, base, '/cabinet/staff-requests');
    await expect(p.getByRole('button', { name: 'Новый список', exact: true })).toHaveCount(0);
  } else if (name === 'пустой куратор и чужой список') {
    const path = await submit(p, base);
    await login(p, base, 'empty');
    await go(p, base, '/cabinet/links');
    await expect(p.locator('[data-testid^="link-"]')).toHaveCount(0);
    await go(p, base, path);
    await expect(p.getByText('Список не найден или недоступен в вашей области.', { exact: true })).toBeVisible();
    await expect(p.getByRole('button', { name: 'Проверить и перенести', exact: true })).toHaveCount(0);
  } else if (name === 'неизвестный код и дубликат') {
    await newRequest(p, base, 'Z999');
    await p.getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    await expect(p.getByLabel('Код ребёнка или снимка 1')).toBeFocused();
    await p.getByLabel('Код ребёнка или снимка 1').fill('A001-01');
    await p.getByRole('button', { name: 'Добавить ребёнка', exact: true }).click();
    await p.getByLabel('Код ребёнка или снимка 2').fill('A001-02');
    await p.getByRole('button', { name: 'Передать список куратору', exact: true }).click();
    await expect(p.getByText('Этот ребёнок уже включён в список.', { exact: true })).toBeVisible();
    expect((await photos(p)).staffRequests ?? []).toHaveLength(0);
  } else if (name === 'полный набор переносится один раз') {
    await login(p, base, 'teacher');
    const path = await submit(p, base);
    await expect(p.getByRole('button', { name: 'Проверить и перенести', exact: true })).toHaveCount(0);
    await login(p, base, 'curator');
    await go(p, base, path);
    await review(p);
    const other = await this.context!.newPage();
    await go(other, base, path);
    await review(other);
    await other.getByLabel('Проверены все кадры, подтверждаю перенос наборов').check();
    await confirm(p);
    const after = await photos(p);
    await save(other, 'Подтвердить перенос');
    expect(await photos(p)).toEqual(after);
    expect(after.photos).toHaveLength(sourcePhotos.length);
    expect(after.photos.every((v) => v.groupId === 'sun-staff' && v.originalGroupId === 'sun-stars' && v.childCode === 'A')).toBe(true);
    expect(after.photos.map((v) => v.id)).toEqual(sourcePhotos.map((v) => v.id));
    await other.close();
  } else if (name === 'уточнение и повторная подача') {
    await login(p, base, 'teacher');
    const path = await submit(p, base);
    await login(p, base, 'curator');
    await go(p, base, path);
    await p.getByRole('button', { name: 'Запросить уточнение', exact: true }).click();
    await p.getByLabel('Что нужно уточнить').fill('Проверьте ребёнка по кадру A001-02');
    await save(p, 'Запросить уточнение');
    await expect(p.getByTestId('request-detail')).toContainText('Нужно уточнение');
    await login(p, base, 'teacher');
    await go(p, base, path);
    await p.getByRole('button', { name: 'Уточнить список', exact: true }).click();
    await p.getByLabel('Код ребёнка или снимка 1').fill('A001-02');
    await save(p, 'Передать список куратору');
    await expect(p.getByTestId('request-detail')).toContainText('На проверке');
    expect((await photos(p)).staffRequests![0]!.history).toHaveLength(3);
  } else if (name === 'нет папки в этой съёмке') {
    const state = await org(p);
    state.groups.find((g) => g.id === 'sun-staff')!.shootId = 'different-shoot';
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(s)), state);
    await submit(p, base);
    await expect(p.getByRole('button', { name: 'Проверить и перенести', exact: true })).toBeDisabled();
    await expect(p.getByRole('alert')).toContainText('нет папки сотрудников');
  } else if (name === 'новый кадр требует повторной проверки') {
    await submit(p, base);
    await review(p);
    const state = await photos(p);
    state.photos.push({
      ...sourcePhotos[0]!,
      id: 'added-r10',
      code: 'A001-99',
      sequence: 99,
      assignments: [{ childId: 'sun-stars:A001', childCode: 'A001', sequence: 99, code: 'A001-99' }]
    });
    await p.evaluate((s) => {
      localStorage.setItem('morefoto:demo:photos:v1', JSON.stringify(s));
      window.dispatchEvent(new Event('morefoto:photos:changed'));
    }, state);
    await p.getByLabel('Проверены все кадры, подтверждаю перенос наборов').check();
    await p.getByRole('button', { name: 'Подтвердить перенос', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Данные изменились');
    expect((await photos(p)).photos.every((v) => v.groupId === 'sun-stars')).toBe(true);
    await p.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await expect(p.getByRole('dialog').locator('.handoff-previews img')).toHaveCount(sourcePhotos.length + 1);
    await confirm(p);
  } else if (name === 'оплаченный заказ сохраняется' || name === 'старая корзина требует проверки') {
    await ready(p, base);
    await transmit(p);
    await addCart(p, base);
    let orderUrl = '',
      before: string | null = null;
    if (name === 'оплаченный заказ сохраняется') {
      await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
      await p.getByLabel('Имя покупателя', { exact: true }).fill('Покупатель R10');
      await p.getByLabel('Телефон', { exact: true }).fill('+7 (900) 123-45-67');
      await p.getByLabel('Email', { exact: true }).fill('r10@example.test');
      await p.getByLabel('Состав и демонстрационные условия проверены', { exact: true }).check();
      await p.getByTestId('create-order').click();
      await expect(p).toHaveURL(/orders\/access\/[a-f0-9]{32}$/);
      orderUrl = p.url();
      await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
      await p.getByTestId('pay-demo').click();
      await expect
        .poll(() => p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') ?? '[]')[0]?.paymentStatus))
        .toBe('paid');
      before = await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'));
    }
    await submit(p, base);
    await review(p);
    await confirm(p);
    if (orderUrl) {
      expect(await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'))).toBe(before);
      await p.goto(orderUrl, { waitUntil: 'networkidle' });
      await expect(p.getByTestId('order-payment-status')).toContainText('Оплачено');
      await expect(p.getByTestId('order-total')).toContainText('250');
    } else {
      await go(p, base, '/g/' + token + '/cart');
      await expect(p.getByText(/Часть выбранных товаров больше недоступна/)).toBeVisible();
      await expect(p.getByRole('link', { name: 'Оформить заказ', exact: true })).not.toBeEnabled();
    }
  } else if (name === 'незавершённый список блокирует подготовку') {
    await submit(p, base);
    await go(p, base);
    await expect(p.getByRole('button', { name: 'Проверить ссылку', exact: true })).toBeDisabled();
    await expect(p.getByTestId('link-sun-stars')).toContainText('завершите проверку списков');
  } else if (name === 'смена ответственного отбирает действие') {
    await ready(p, base);
    await login(p, base, 'teacher');
    await go(p, base);
    await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    await p.getByLabel('Подтверждаю факт передачи и указанные сроки').check();
    const state = await org(p);
    state.groups[0]!.teacherId = null;
    await p.evaluate((s) => localStorage.setItem('morefoto:demo:organization:v1', JSON.stringify(s)), state);
    await p.getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    await expect(p.locator('.management-error')).toContainText('Группа недоступна');
    expect((await org(p)).groups[0]!.sentAt).toBeUndefined();
  } else if (name === 'редактирование группы сохраняет историю') {
    await ready(p, base);
    await transmit(p);
    const before = (await org(p)).groups[0]!;
    await go(p, base, '/cabinet/institutions/sun/shoots/sun-summer-2026');
    await p.getByRole('button', { name: 'Редактировать Звёздочки', exact: true }).filter({ visible: true }).click();
    await p.getByLabel('Название группы', { exact: true }).fill('Звёздочки — новая подпись');
    await save(p, 'Сохранить');
    const after = (await org(p)).groups[0]!;
    expect(after.sentAt).toBe(before.sentAt);
    expect(after.closesAt).toBe(before.closesAt);
    expect(after.linkHistory).toEqual(before.linkHistory);
    expect(after.linkOperations).toEqual(before.linkOperations);
  } else throw new Error('Неизвестная проверка ' + name);
});
Then('R10 проверяет экран {string} шириной {int}', async function (this: CustomWorld, screen: string, width: number) {
  const p = page(this),
    base = this.baseUrl;
  await p.setViewportSize({ width, height: 900 });
  if (screen === 'передача') {
    await ready(p, base);
    await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
  }
  if (screen === 'список') await newRequest(p, base);
  if (screen === 'проверка' || screen === 'масштаб') {
    await submit(p, base);
    await review(p);
  }
  if (screen === 'масштаб') await p.addStyleTag({ content: 'html{font-size:200% !important}' });
  if (screen === 'масштаб') {
    expect(await p.evaluate(() => getComputedStyle(document.documentElement).fontSize)).toBe('32px');
    expect(await p.locator('.admin-body').evaluate((el) => el.clientHeight)).toBeGreaterThanOrEqual(200);
  }
  await expect(p.locator('body')).toBeVisible();
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.screenshot({
    path: '/app/reports/e2e/r10-' + screen + '-' + width + '.png',
    fullPage: !(await p.getByRole('dialog').count()),
    animations: 'disabled'
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
  if (await p.getByRole('dialog').count()) {
    const footer = p.locator('.admin-footer');
    await expect(footer).toBeInViewport();
    expect(await p.getByRole('dialog').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  }
  await mkdir('/app/reports/e2e', { recursive: true });
});
