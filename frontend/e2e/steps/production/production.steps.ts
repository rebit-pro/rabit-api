import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import { fixture } from './fixtures.js';
import { settlement } from '../../../src/modules/morefoto/settlement/rules.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { ProductionState } from '../../../src/modules/morefoto/production/types.js';
type DemoWindow = Window & { __MOREFOTO_MOCKS__?: { setNow(value: string): void; failNextRequest(): void; setDelay(value: number): void } };
const path = '/cabinet/production/sun-stars';
const page = (w: CustomWorld) => w.page!;
const main = (p: Page) => p.locator('#cabinet-main');
const dialog = (p: Page) => p.getByRole('dialog');
async function go(p: Page, base: string, target = path) {
  await p.goto(base + target, { waitUntil: 'networkidle' });
}
async function read<T>(p: Page, key: string): Promise<T> {
  return p.evaluate((k) => JSON.parse(localStorage.getItem('morefoto:demo:' + k) ?? 'null'), key);
}
async function put(p: Page, key: string, value: unknown) {
  await p.evaluate(
    ({ key, value }) => {
      localStorage.setItem('morefoto:demo:' + key, JSON.stringify(value));
      window.dispatchEvent(new Event('morefoto:demo:changed'));
    },
    { key, value }
  );
}
async function login(p: Page, base: string, role = 'organizer') {
  await p.evaluate(() => {
    for (const k of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + k);
  });
  await go(p, base, '/login');
  await p.getByLabel('Email', { exact: true }).fill(role + '@morefoto.test');
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function detail(p: Page, base: string) {
  await login(p, base);
  await go(p, base);
  await expect(p.getByRole('heading', { level: 1, name: 'Звёздочки · производство' })).toBeVisible();
}
async function submit(p: Page, name: string) {
  await dialog(p).getByRole('button', { name, exact: true }).click();
  await expect(dialog(p)).toBeHidden();
}
async function create(p: Page) {
  await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
  await submit(p, 'Сохранить версию');
  await expect(main(p)).toContainText('ПЗ-0001');
}
async function start(p: Page) {
  await p.getByRole('button', { name: 'Учесть запуск печати', exact: true }).click();
  await submit(p, 'Подтвердить запуск');
  await expect(main(p)).toContainText('Запуск учтён');
}
async function pack(p: Page, id = 'MF-R14-001') {
  await p.getByRole('button', { name: 'Скомплектовать ' + id, exact: true }).click();
  await submit(p, 'Сохранить комплектацию');
}
async function nextVersion(p: Page) {
  await p.getByRole('button', { name: 'Сформировать новую версию', exact: true }).click();
  await p.getByLabel('Комментарий к действию', { exact: true }).fill('Уточнён состав оплаченных заказов');
  await submit(p, 'Сохранить версию');
}
async function changeQuantity(p: Page, quantity: number) {
  const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
  orders[0]!.quote.lines[1]!.quantity = quantity;
  await put(p, 'orders:v1', orders);
}
async function csv(p: Page) {
  const waiting = p.waitForEvent('download');
  await p.getByRole('button', { name: /Скачать CSV версии/ }).click();
  const download = await waiting,
    stream = await download.createReadStream();
  const chunks = [];
  for await (const chunk of stream!) chunks.push(Buffer.from(chunk));
  return { name: download.suggestedFilename(), text: Buffer.concat(chunks).toString('utf8') };
}
async function choose(p: Page, label: string, text: string) {
  await p.getByLabel(label, { exact: true }).locator('..').click();
  await p.getByRole('option', { name: text, exact: true }).click();
  await p.locator('h1').click();
}
Given('подготовлено производство R14', async function (this: CustomWorld) {
  const f = fixture(),
    p = page(this);
  await put(p, 'organization:v1', f.org);
  await put(p, 'orders:v1', f.orders);
  await put(p, 'photos:v1', f.photos);
  await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-08T09:00:00Z'));
});
Then('R14 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'состав пары и исключения') {
    await detail(p, base);
    await expect(main(p)).toContainText('8отпечатков');
    await expect(main(p)).toContainText('2пакетов');
    await expect(main(p)).toContainText('Оплата не подтверждена');
    await expect(main(p)).toContainText('Поздняя оплата требует решения');
    await expect(main(p)).toContainText('Исполнение приостановлено');
    await create(p);
    const state = await read<ProductionState>(p, 'production:v1');
    expect(state.jobs[0]!.versions[0]!.runRows.map((r) => r.next)).toEqual([4, 4]);
    expect(state.jobs[0]!.versions[0]!.plan.rows.every((r) => r.perUnit === 2)).toBe(true);
  } else if (name === 'открытая группа и новый срок закрытия') {
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.groups[0]!.state = 'preparing';
    org.groups[0]!.closesAt = '2026-09-10T09:00:00Z';
    await put(p, 'organization:v1', org);
    await detail(p, base);
    await expect(main(p)).toContainText('Подготовка группы');
    await expect(p.getByRole('button', { name: 'Сформировать задание', exact: true })).toBeDisabled();
    org.groups[0]!.state = 'open';
    await put(p, 'organization:v1', org);
    await expect(main(p)).toContainText('Приём ещё не закрыт');
    await expect(p.getByRole('button', { name: 'Сформировать задание', exact: true })).toBeDisabled();
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-11T09:00:00Z'));
    await expect(p.getByRole('button', { name: 'Сформировать задание', exact: true })).toBeEnabled();
    await expect(main(p)).toContainText('17 сентября 2026');
    await create(p);
  } else if (name === 'пустой физический состав') {
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    for (const o of orders) o.quote.lines = o.quote.lines.filter((l) => l.product.kind !== 'physical');
    await put(p, 'orders:v1', orders);
    await detail(p, base);
    await expect(main(p)).toContainText('Физических позиций нет');
    await expect(p.getByRole('button', { name: 'Сформировать задание', exact: true })).toBeDisabled();
    expect(await read(p, 'production:v1')).toBeNull();
  } else if (name === 'повторное скачивание и открытие не запускают печать') {
    await detail(p, base);
    await create(p);
    const before = await read(p, 'production:v1'),
      first = await csv(p),
      second = await csv(p);
    expect(first).toEqual(second);
    expect(first.name).toBe('ПЗ-0001-v1.csv');
    expect(first.text).toContain('К новой печати этой версии');
    expect(first.text).not.toContain('buyer-r13');
    await go(p, base);
    expect(await read(p, 'production:v1')).toEqual(before);
    await start(p);
    const third = await csv(p);
    expect(third).toEqual(first);
    await go(p, base);
    await expect(p.getByRole('button', { name: 'Учесть запуск печати', exact: true })).toHaveCount(0);
  } else if (name === 'запуск обновляет заказ в другой вкладке') {
    await detail(p, base);
    const parent = await this.context!.newPage();
    await go(parent, base, '/orders/access/' + '1'.repeat(32));
    await create(p);
    await expect(parent.locator('body')).toContainText('В очереди');
    await start(p);
    await expect(parent.locator('body')).toContainText('В печати');
    expect((await read<OrderSnapshot[]>(p, 'orders:v1'))[0]!.quote).toEqual(fixture().orders[0]!.quote);
    await parent.close();
  } else if (name === 'пакеты по заказам и снятие отметки') {
    await detail(p, base);
    await create(p);
    await start(p);
    await pack(p);
    await expect(p.getByTestId('package-r14-paid')).toContainText('Сверено: Анна');
    await pack(p, 'MF-R14-002');
    await expect(main(p)).toContainText('Скомплектовано 2 из 2');
    await p.getByRole('button', { name: 'Снять комплектацию MF-R14-001', exact: true }).click();
    await p.getByLabel('Комментарий к действию', { exact: true }).fill('Повторная сверка пакета');
    await submit(p, 'Сохранить комплектацию');
    await expect(main(p)).toContainText('Скомплектовано 1 из 2');
  } else if (name === 'новая версия до печати заменяет состав') {
    await detail(p, base);
    await create(p);
    await changeQuantity(p, 1);
    await expect(main(p)).toContainText('Состав или срок закрытия изменились');
    await expect(p.getByRole('button', { name: 'Учесть запуск печати', exact: true })).toHaveCount(0);
    await nextVersion(p);
    const state = await read<ProductionState>(p, 'production:v1');
    expect(state.jobs).toHaveLength(1);
    expect(state.jobs[0]!.versions).toHaveLength(2);
    expect(state.jobs[0]!.versions[1]!.runRows.reduce((n, r) => n + r.next, 0)).toBe(6);
    await start(p);
  } else if (name === 'коррекция после печати учитывает только допечатку') {
    await detail(p, base);
    await create(p);
    await start(p);
    await pack(p);
    await pack(p, 'MF-R14-002');
    await go(p, base, '/cabinet/orders/r14-paid');
    await expect(main(p)).toContainText('В печати');
    await p.getByRole('button', { name: 'Исправить позицию', exact: true }).click();
    for (const [label, value] of [
      ['Позиция заказа', 'Пара 10×15 · A001-01 · 2 шт.'],
      ['Новый кадр', 'A001-02']
    ]) {
      await p.getByLabel(label!, { exact: true }).locator('..').click();
      await p.getByRole('option', { name: value!, exact: true }).click();
      await dialog(p).getByRole('heading').click();
    }
    await p.getByLabel('Причина операции', { exact: true }).fill('Замена кадра после запуска');
    await p.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
    await submit(p, 'Подтвердить операцию');
    await expect(p.getByTestId('settlement-history')).toContainText('Требуется согласование перепечатки');
    await go(p, base);
    await expect(main(p)).toContainText('Изменение после печати требует согласования');
    await go(p, base, '/cabinet/orders/r14-paid');
    await p.getByRole('button', { name: 'Согласовать исполнение', exact: true }).click();
    await p.getByLabel('Причина операции', { exact: true }).fill('Перепечатка согласована');
    await p.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
    await submit(p, 'Подтвердить операцию');
    expect((await read<OrderSnapshot[]>(p, 'orders:v1'))[0]!.quote).toEqual(fixture().orders[0]!.quote);
    await go(p, base);
    await expect(main(p)).not.toContainText('Изменение после печати требует согласования');
    await nextVersion(p);
    const v = (await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[1]!;
    expect(v.runRows.reduce((n, r) => n + r.next, 0)).toBe(4);
    expect(v.surplus[0]!.next).toBe(4);
    expect(Object.keys(v.packages)).toEqual(['r14-second']);
    await expect(main(p)).toContainText('Ранее запущено сверх текущего состава');
    await start(p);
    await pack(p);
  } else if (name === 'уменьшение количества сохраняет лишние отпечатки') {
    await detail(p, base);
    await create(p);
    await start(p);
    await changeQuantity(p, 1);
    await nextVersion(p);
    const v = (await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[1]!;
    expect(v.runRows.reduce((n, r) => n + r.next, 0)).toBe(0);
    expect(v.surplus[0]!.next).toBe(2);
    await start(p);
    await pack(p);
  } else if (name === 'архивная версия доступна только для сверки') {
    await detail(p, base);
    await create(p);
    await changeQuantity(p, 1);
    await nextVersion(p);
    await choose(p, 'Версия задания', 'Версия 1');
    await expect(main(p)).toContainText('Архивная версия');
    await expect(p.getByRole('button', { name: 'Учесть запуск печати', exact: true })).toHaveCount(0);
    const file = await csv(p);
    expect(file.name).toBe('ПЗ-0001-v1.csv');
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.runRows[0]!.prints).toBe(4);
  } else if (name === 'потерянный ответ и повтор без дубля') {
    await detail(p, base);
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await put(p, 'production:lose-response-once', true);
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).click();
    await expect(dialog(p)).toContainText('Ответ потерян');
    await submit(p, 'Сохранить версию');
    const s = await read<ProductionState>(p, 'production:v1');
    expect(s.jobs).toHaveLength(1);
    expect(s.jobs[0]!.versions).toHaveLength(1);
    expect(s.operations).toHaveLength(1);
  } else if (name === 'конфликт состава и актуализация редактора') {
    await detail(p, base);
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await changeQuantity(p, 1);
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).click();
    await expect(dialog(p)).toContainText('Состав или версия изменились');
    expect(await read(p, 'production:v1')).toBeNull();
    await dialog(p).getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await submit(p, 'Сохранить версию');
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.runRows.reduce((n, r) => n + r.next, 0)).toBe(6);
  } else if (name === 'две вкладки запускают версию только один раз') {
    await detail(p, base);
    await create(p);
    const other = await this.context!.newPage();
    await go(other, base);
    await p.getByRole('button', { name: 'Учесть запуск печати', exact: true }).click();
    await other.getByRole('button', { name: 'Учесть запуск печати', exact: true }).click();
    await Promise.all([
      dialog(p).getByRole('button', { name: 'Подтвердить запуск', exact: true }).click(),
      dialog(other).getByRole('button', { name: 'Подтвердить запуск', exact: true }).click()
    ]);
    await expect
      .poll(async () => {
        const s = await read<ProductionState>(p, 'production:v1');
        return s.jobs[0]!.history.filter((e) => e.text.startsWith('Запуск версии')).length;
      })
      .toBe(1);
    await other.close();
  } else if (name === 'восстановление черновика после ошибки') {
    await detail(p, base);
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await p.getByLabel('Комментарий к действию', { exact: true }).fill('Проверено по контрольному списку');
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.failNextRequest());
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).click();
    await expect(dialog(p)).toContainText('Не удалось загрузить данные');
    await dialog(p).getByRole('button', { name: 'Закрыть редактор' }).click();
    await go(p, base);
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await expect(dialog(p)).toContainText('Восстановлен несохранённый черновик');
    await expect(p.getByLabel('Комментарий к действию', { exact: true })).toHaveValue('Проверено по контрольному списку');
    await submit(p, 'Сохранить версию');
  } else if (name === 'валидация и клавиатура') {
    await detail(p, base);
    const button = p.getByRole('button', { name: 'Сформировать задание', exact: true });
    await button.focus();
    await p.keyboard.press('Enter');
    await p.getByLabel('Комментарий к действию', { exact: true }).fill('');
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).click();
    await expect(dialog(p)).toContainText('Укажите причину');
    await p.getByLabel('Комментарий к действию', { exact: true }).fill('Сверено клавиатурой');
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).focus();
    await p.keyboard.press('Enter');
    await expect(dialog(p)).toBeHidden();
  } else if (name === 'ошибка загрузки и повтор') {
    await login(p, base);
    await expect(p.locator('section[aria-label="Доступные учреждения"]')).toBeVisible();
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.failNextRequest());
    await p.getByRole('link', { name: 'Производство', exact: true }).click();
    await expect(p.getByTestId('production-workspace').getByRole('alert')).toContainText('Не удалось загрузить данные');
    await p.getByRole('button', { name: 'Повторить загрузку', exact: true }).click();
    await expect(p.getByRole('heading', { level: 1, name: 'Производство и комплектация' })).toBeVisible();
  } else if (name === 'ошибка экспорта и повтор') {
    await detail(p, base);
    await create(p);
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.failNextRequest());
    await p.getByRole('button', { name: /Скачать CSV версии/ }).click();
    await expect(p.getByRole('alert')).toContainText('Не удалось загрузить данные');
    await csv(p);
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.startedAt).toBeUndefined();
  } else if (name === 'куратор видит только своё учреждение') {
    await detail(p, base);
    await create(p);
    await login(p, base, 'curator');
    await go(p, base, '/cabinet/production');
    await expect(main(p)).not.toContainText('Чужой класс');
    await expect(p.getByTestId('production-group-sun-stars')).toBeVisible();
    await go(p, base);
    await expect(main(p)).toContainText('ПЗ-0001');
    await expect(p.getByRole('button', { name: 'Учесть запуск печати', exact: true })).toHaveCount(0);
    await csv(p);
    await go(p, base, '/cabinet/production/school-1a');
    await expect(main(p)).toContainText('Группа недоступна');
    await expect(main(p)).not.toContainText('PRIVATE-FOREIGN');
  } else if (name === 'руководитель и воспитатель без доступа') {
    for (const role of ['head', 'teacher']) {
      await login(p, base, role);
      await go(p, base);
      await expect(p).not.toHaveURL(/production/);
      await expect(p.getByRole('link', { name: 'Производство', exact: true })).toHaveCount(0);
    }
  } else if (name === 'отзыв полномочий во время действия') {
    await detail(p, base);
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setDelay(1200));
    await dialog(p).getByRole('button', { name: 'Сохранить версию', exact: true }).click();
    const org = await read<OrganizationState>(p, 'organization:v1');
    const user = {
      id: 101,
      name: 'Анна',
      email: 'organizer@morefoto.test',
      role: 'curator' as const,
      active: true,
      revision: 2,
      accessRevision: 1
    };
    org.users = [user];
    await put(p, 'organization:v1', org);
    await expect.poll(() => read(p, 'production:v1')).toBeNull();
    await expect(main(p)).not.toContainText('ПЗ-0001');
    await p.waitForTimeout(1500);
    expect(await read(p, 'production:v1')).toBeNull();
  } else if (name === 'сотрудник сохраняет исходную группу и код') {
    await detail(p, base);
    await go(p, base, '/cabinet/production/sun-staff');
    await create(p);
    const rows = (await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.plan.rows;
    expect(rows[0]!.audience).toBe('staff');
    expect(rows[0]!.sourceGroupName).toBe('Звёздочки');
    expect(rows[0]!.sourceChildCode).toBe('A001');
    expect(rows[0]!.childCode).toBe('A099');
    await start(p);
    await expect(p.getByTestId('package-r14-staff')).toContainText('Звёздочки · A001');
  } else if (name === 'фильтры ссылки и возвращение к списку') {
    await detail(p, base);
    await go(p, base, '/cabinet/production');
    await choose(p, 'Учреждение', 'Детский сад «Солнечный»');
    await choose(p, 'Состояние производства', 'Можно сформировать');
    await p.getByRole('link', { name: 'Открыть производство: Звёздочки', exact: true }).click();
    await p.getByRole('link', { name: '← К производству' }).click();
    await expect(p).toHaveURL(/institution=sun/);
    await expect(p.getByTestId('production-group-school-1a')).toHaveCount(0);
    await go(p, base, '/cabinet/production?institution=foreign');
    await expect(main(p)).toContainText('Группы не найдены');
  } else if (name === 'повторное открытие группы сохраняет печать') {
    await detail(p, base);
    await create(p);
    await start(p);
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.groups[0]!.state = 'open';
    org.groups[0]!.closesAt = '2026-09-10T09:00:00Z';
    await put(p, 'organization:v1', org);
    await expect(p.getByRole('button', { name: 'Сформировать новую версию', exact: true })).toBeDisabled();
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-11T09:00:00Z'));
    await nextVersion(p);
    const s = await read<ProductionState>(p, 'production:v1');
    expect(s.jobs[0]!.versions[1]!.runRows.reduce((n, r) => n + r.next, 0)).toBe(0);
    expect(s.jobs[0]!.versions[1]!.plan.deliveryAt).toBe('2026-09-17T09:00:00.000Z');
  } else if (name === 'согласованная поздняя оплата добавляется без повторной печати') {
    await detail(p, base);
    await create(p);
    await start(p);
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1'),
      late = orders.find((o) => o.id === 'r14-late')!;
    late.settlement = {
      ...settlement(late),
      decisions: [
        { id: 'fulfil-r14', decision: 'fulfil', at: '2026-09-08T09:00:00Z', actorId: 101, actorName: 'Анна', reason: 'Оплату приняли' }
      ]
    };
    await put(p, 'orders:v1', orders);
    await nextVersion(p);
    const v = (await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[1]!;
    expect(v.runRows.reduce((n, r) => n + r.next, 0)).toBe(4);
    expect(v.plan.rows).toHaveLength(3);
  } else if (name === 'подтверждённый полный возврат снимает заказ из новой версии') {
    await detail(p, base);
    await create(p);
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.settlement = {
      ...settlement(orders[0]!),
      refunds: [
        {
          id: 'refund',
          at: '2026-09-08T09:00:00Z',
          actorId: 101,
          actorName: 'Анна',
          reason: 'Возврат',
          status: 'confirmed',
          amount: 45000,
          allocations: {},
          fileIds: null,
          hold: false,
          history: []
        }
      ]
    };
    await put(p, 'orders:v1', orders);
    await nextVersion(p);
    const v = (await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[1]!;
    expect(v.plan.rows).toHaveLength(1);
    await expect(main(p)).toContainText('Оплата полностью возвращена');
  } else throw new Error('Неизвестный сценарий R14: ' + name);
});
Then('R14 показывает {string} шириной {int}', async function (this: CustomWorld, screen: string, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 900 });
  await detail(p, this.baseUrl);
  if (screen.startsWith('очередь')) await go(p, this.baseUrl, '/cabinet/production');
  else if (screen === 'пусто') {
    await go(p, this.baseUrl, '/cabinet/production?institution=foreign');
    await expect(main(p)).toContainText('Группы не найдены');
  } else if (screen.startsWith('редактор')) {
    await p.getByRole('button', { name: 'Сформировать задание', exact: true }).click();
    await expect(dialog(p).getByRole('heading', { name: 'Проверка печатного задания' })).toBeVisible();
  } else {
    await create(p);
    await start(p);
    await pack(p);
    if (screen === 'версия') {
      await changeQuantity(p, 1);
      await nextVersion(p);
    }
  }
  if (screen.includes('200'))
    await p.evaluate(() => {
      const values = [...document.querySelectorAll('#cabinet-main *,[role="dialog"] *')].map(
        (e) => [e, getComputedStyle(e).fontSize] as const
      );
      for (const [e, size] of values) (e as HTMLElement).style.fontSize = parseFloat(size) * 2 + 'px';
    });
  await p.evaluate(() => {
    (document.activeElement as HTMLElement)?.blur();
    window.scrollTo(0, 0);
    return document.fonts.ready;
  });
  if (!screen.startsWith('редактор')) await expect.poll(() => p.evaluate(() => window.scrollY)).toBe(0);
  expect(await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  await mkdir('reports/e2e/r14-visual', { recursive: true });
  await p.screenshot({
    path: 'reports/e2e/r14-visual/' + screen + '-' + width + '.png',
    fullPage: !screen.startsWith('редактор'),
    animations: 'disabled'
  });
});
