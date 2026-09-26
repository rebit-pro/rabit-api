import { expect, type Page } from '@playwright/test';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
export const regular = '/g/158-group-7bc93615c4e94fd18a207d560b3e1f82';
export const physical = 'Два отпечатка 10 × 15';
export const digital = 'Электронный кадр';
export const longName = 'Александра Константиновна Волкова — проверка длинного имени покупателя';
export const longEmail = 'alexandra.konstantinovna.volkova.review@example.test';
export async function go(p: Page, base: string, path: string) {
  await p.goto(base + path, { waitUntil: 'networkidle' });
}
export async function read<T>(p: Page, key: string): Promise<T> {
  return p.evaluate((key) => JSON.parse(localStorage.getItem('morefoto:demo:' + key) || 'null'), key);
}
export const orders = (p: Page) => read<OrderSnapshot[]>(p, 'orders:v1');
export const org = (p: Page) => read<OrganizationState>(p, 'organization:v1');
export async function login(p: Page, base: string, role = 'organizer') {
  await p.evaluate(() => {
    for (const k of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + k);
  });
  await go(p, base, '/login');
  await p.getByLabel('Email', { exact: true }).fill(role + '@morefoto.test');
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
export async function choose(p: Page, label: string, value: string) {
  await p.getByLabel(label, { exact: true }).locator('..').click();
  await p.getByRole('option', { name: value, exact: true }).click();
  if (await p.getByRole('dialog').count()) await p.getByRole('dialog').locator('h2').click();
  else await p.locator('h1').click();
  await expect(p.getByRole('listbox')).toHaveCount(0);
}
export async function save(p: Page, label: string) {
  await p.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
}
export async function setup(p: Page, base: string) {
  p.setDefaultTimeout(15000);
  await go(p, base, '/demo/review');
  await p.getByRole('button', { name: 'Подготовить набор проверки', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Набор готов к работе' })).toBeVisible();
  await choose(p, 'Задержка ответа', 'Без задержки');
  await p.getByRole('button', { name: 'Применить условия' }).click();
  await expect(p.getByRole('status')).toContainText('Условия');
}
export async function controls(p: Page, base: string, date = '2026-09-08T12:00', delay = 'Без задержки', offline = false) {
  await go(p, base, '/demo/review');
  await p.getByLabel('Время сценария (МСК)', { exact: true }).fill(date);
  await choose(p, 'Задержка ответа', delay);
  await p.getByLabel('Сеть недоступна', { exact: true }).setChecked(offline);
  await p.getByRole('button', { name: 'Применить условия' }).click();
  await expect(p.getByRole('status')).toContainText('Условия');
}
export async function checkGroup(p: Page, base: string, id = 'sun-stars') {
  await login(p, base);
  await go(p, base, '/cabinet/links?group=' + id);
  await p.getByRole('button', { name: 'Проверить ссылку', exact: true }).first().click();
  for (const label of [
    'Фотографии и коды проверены',
    'Продукция, цены и условия группы проверены',
    'Списки сотрудников и ответственные проверены'
  ])
    await p.getByLabel(label, { exact: true }).check();
  await save(p, 'Проверить ссылку');
}
export async function transmit(p: Page, base: string, id = 'sun-stars', teacher = false) {
  if (teacher) await login(p, base, 'teacher');
  await go(p, base, '/cabinet/links?group=' + id);
  await p.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
  await p.getByLabel('Дата и время передачи (МСК)', { exact: true }).fill('2026-09-08T12:00');
  await p.getByLabel('Подтверждаю факт передачи и указанные сроки', { exact: true }).check();
  await save(p, 'Отметить передачу ссылки');
}
export async function openGroup(p: Page, base: string, id = 'sun-stars', teacher = false) {
  await checkGroup(p, base, id);
  await transmit(p, base, id, teacher);
}
export async function add(p: Page, base: string, photo = 'A002-01', product = digital, quantity = 1, path = regular) {
  await go(p, base, path);
  await p.getByRole('button', { name: 'Открыть кадр ' + photo, exact: true }).click();
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await p.getByRole('option', { name: product, exact: true }).click();
  if (quantity !== 1) await p.getByRole('spinbutton').fill(String(quantity));
  await p.getByTestId('add-to-cart').click();
  await expect(p.locator('.product-notice')).toBeVisible();
}
export async function checkout(p: Page) {
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
  await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Оформление заказа', exact: true })).toBeVisible();
}
export async function fillBuyer(p: Page) {
  await p.getByLabel('Имя покупателя', { exact: true }).fill(longName);
  await p.getByLabel('Телефон', { exact: true }).fill('+7 (900) 123-45-67');
  await p.getByLabel('Email', { exact: true }).fill(longEmail);
  await p
    .getByLabel('Комментарий — необязательно', { exact: true })
    .fill('Проверка общего пути: от выбора фотографий до передачи в учреждение.');
  await p.getByLabel('Состав и условия проверены', { exact: true }).check();
}
export async function create(p: Page) {
  await checkout(p);
  await fillBuyer(p);
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/orders\/access\/[a-f0-9]{32}$/);
  const all = await orders(p);
  return all[all.length - 1]!;
}
export async function parent(p: Page, base: string, o: OrderSnapshot) {
  await go(p, base, '/orders/access/' + o.accessKey);
  await expect(p.getByRole('heading', { name: 'Заказ ' + o.number, exact: true })).toBeVisible();
}
export async function pay(p: Page, mode = 'Успешная оплата') {
  await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  if (mode !== 'Успешная оплата') await p.getByRole('radio', { name: mode, exact: true }).check();
  await p.getByTestId('pay-demo').click();
  await expect(p.getByTestId('payment-status')).toContainText(
    mode === 'Ожидание подтверждения' ? 'Ожидаем подтверждение' : mode === 'Отказ в оплате' ? 'отклон' : 'Тестовая оплата подтверждена'
  );
  await p.getByRole('link', { name: 'Вернуться к заказу', exact: true }).click();
}
export async function purchase(p: Page, base: string, product = digital, quantity = 1, photo = 'A002-01', path = regular) {
  await add(p, base, photo, product, quantity, path);
  const o = await create(p);
  await pay(p);
  return (await orders(p)).find((x) => x.id === o.id)!;
}
export async function card(p: Page, base: string, o: OrderSnapshot) {
  await go(p, base, '/cabinet/orders/' + o.id);
  await expect(p.getByTestId('settlement-panel')).toBeVisible();
}
export async function operation(p: Page, name: string) {
  await p.getByTestId('settlement-panel').getByRole('button', { name, exact: true }).click();
  await expect(p.getByRole('dialog')).toBeVisible();
}
export async function confirm(p: Page, reason = 'Согласовано по обращению родителя в сквозной проверке R16') {
  await p.getByLabel('Причина операции', { exact: true }).fill(reason);
  await p.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
  await save(p, 'Подтвердить операцию');
}
export async function appeal(p: Page, base: string, o: OrderSnapshot, topic = 'Продлить приём заказов') {
  await parent(p, base, o);
  await p.getByLabel('Ваш вопрос', { exact: true }).fill('Просим проверить заказ и согласовать изменение по обращению родителя.');
  await choose(p, 'Тема обращения', topic);
  await p.getByTestId('send-support').click();
  await expect(p.getByTestId('support-history').locator('article')).toHaveCount(1);
  return '/cabinet/support/' + (await orders(p)).find((x) => x.id === o.id)!.supportRequests![0]!.id;
}
export async function produce(p: Page, base: string, o: OrderSnapshot, pack = true) {
  await login(p, base);
  await go(p, base, '/cabinet/production/' + o.groupId);
  await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
  await save(p, 'Сохранить версию');
  await p.getByRole('button', { name: 'Учесть запуск печати', exact: true }).click();
  await save(p, 'Подтвердить запуск');
  if (pack) {
    await p.getByRole('button', { name: 'Скомплектовать ' + o.number, exact: true }).click();
    await save(p, 'Сохранить комплектацию');
  }
}
export async function ship(p: Page, base: string, o: OrderSnapshot) {
  await go(p, base, '/cabinet/delivery');
  await p
    .getByTestId('delivery-group-' + o.groupId)
    .getByRole('button', { name: 'Отметить готовность', exact: true })
    .click();
  await p.getByLabel('Комментарий к действию', { exact: true }).fill('Проверены состав и готовность комплекта R16');
  await p.getByLabel('Состав и фактическое событие проверены', { exact: true }).check();
  await save(p, 'Подтвердить готовность');
  await p.getByTestId('transfer-batch').getByRole('button', { name: 'Записать передачу', exact: true }).click();
  await p.getByLabel('Принял в учреждении', { exact: true }).fill('Мария Александровна — ответственная в учреждении');
  await p.getByLabel('Комментарий к действию', { exact: true }).fill('Переданы проверенные комплекты фотографий R16');
  await p.getByLabel('Состав и фактическое событие проверены', { exact: true }).check();
  await save(p, 'Подтвердить передачу');
}
export async function noOverflow(p: Page) {
  await expect.poll(() => p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1);
}
