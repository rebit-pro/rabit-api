import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import { fixture } from '../production/fixtures.js';
import { buildPlan, composeVersion } from '../../../src/modules/morefoto/production/rules.js';
import { settlement } from '../../../src/modules/morefoto/settlement/rules.js';
import type { ProductionState } from '../../../src/modules/morefoto/production/types.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { OrderSnapshot } from '../../../src/modules/morefoto/orders/types.js';
import type { PhotoState } from '../../../src/modules/morefoto/photos/types.js';
type DemoWindow = Window & { __MOREFOTO_MOCKS__?: { setNow(v: string): void; failNextRequest(): void; setDelay(v: number): void } };
const main = (p: Page) => p.getByTestId('delivery-workspace'),
  dialog = (p: Page) => p.getByRole('dialog'),
  group = (p: Page, id = 'sun-stars') => p.getByTestId('delivery-group-' + id);
const now = '2026-09-08T09:00:00Z';
async function go(p: Page, base: string, target = '/cabinet/delivery') {
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
async function screen(p: Page, base: string, role = 'organizer') {
  await login(p, base, role);
  await go(p, base);
  await expect(main(p).getByRole('heading', { name: 'Готовность и доставка', exact: true })).toBeVisible();
  await expect(group(p)).toBeVisible();
}
async function fill(p: Page, kind = 'ready') {
  await dialog(p)
    .getByLabel(kind === 'unready' ? 'Причина снятия готовности' : 'Комментарий к действию', { exact: true })
    .fill('Пакеты и фактическая передача проверены');
  if (kind === 'transfer') await dialog(p).getByLabel('Принял в учреждении', { exact: true }).fill('Елена Соколова');
  await dialog(p).getByLabel('Состав и фактическое событие проверены', { exact: true }).check();
}
async function submit(p: Page, kind = 'ready') {
  await dialog(p)
    .getByRole('button', {
      name: kind === 'transfer' ? 'Подтвердить передачу' : kind === 'unready' ? 'Снять готовность' : 'Подтвердить готовность',
      exact: true
    })
    .click();
  await expect(dialog(p)).toBeHidden();
}
async function ready(p: Page, id = 'sun-stars') {
  await group(p, id).getByRole('button', { name: 'Отметить готовность', exact: true }).click();
  await fill(p);
  await submit(p);
  await expect(group(p, id)).toContainText('Готово к передаче');
}
async function openTransfer(p: Page) {
  await p.getByTestId('transfer-batch').getByRole('button', { name: 'Записать передачу', exact: true }).click();
  await fill(p, 'transfer');
}
async function transfer(p: Page) {
  await openTransfer(p);
  await submit(p, 'transfer');
  await expect(p.getByTestId('transfer-history').first()).toContainText('Получено учреждением');
}
async function seedVersion(p: Page) {
  const state = await read<ProductionState>(p, 'production:v1'),
    org = await read<OrganizationState>(p, 'organization:v1'),
    orders = await read<OrderSnapshot[]>(p, 'orders:v1'),
    photos = await read<PhotoState>(p, 'photos:v1');
  const job = state.jobs[0]!,
    g = org.groups.find((g) => g.id === job.groupId)!;
  const plan = buildPlan({ group: g, organization: org, photos, orders, state, now }),
    v = composeVersion(plan, job, now, 'Анна', 'Уточнено');
  v.startedAt = now;
  for (const r of v.plan.rows) v.packages[r.orderId] = { at: now, actor: 'Анна' };
  job.versions.push(v);
  job.revision++;
  await put(p, 'production:v1', state);
}
Given('подготовлена доставка R15', async function (this: CustomWorld) {
  const f = fixture(),
    p = this.page!,
    state: ProductionState = { jobs: [], operations: [] };
  f.org.groups.find((g) => g.id === 'sun-staff')!.closesAt = '2026-09-06T09:00:00Z';
  for (const id of ['sun-stars', 'sun-staff']) {
    const g = f.org.groups.find((g) => g.id === id)!,
      plan = buildPlan({
        group: g,
        organization: f.org as OrganizationState,
        photos: f.photos as PhotoState,
        orders: f.orders,
        state,
        now
      });
    const v = composeVersion(plan, null, now, 'Анна', 'Проверено');
    v.startedAt = now;
    v.startedBy = 'Анна';
    for (const row of v.plan.rows) v.packages[row.orderId] = { at: now, actor: 'Анна' };
    state.jobs.push({
      id: 'job-' + id,
      number: 'ПЗ-000' + (state.jobs.length + 1),
      groupId: id,
      institutionId: g.institutionId,
      shootId: g.shootId,
      revision: 1,
      versions: [v],
      history: []
    });
  }
  await put(p, 'organization:v1', f.org);
  await put(p, 'orders:v1', f.orders);
  await put(p, 'photos:v1', f.photos);
  await put(p, 'production:v1', state);
  await put(p, 'clock:now', now);
});
Then('R15 проверяет {string}', async function (this: CustomWorld, name: string) {
  const p = this.page!,
    base = this.baseUrl;
  if (name === 'готовность и родитель в другой вкладке') {
    await screen(p, base);
    const parent = await this.context!.newPage();
    await go(parent, base, '/orders/access/' + '1'.repeat(32));
    await ready(p);
    await expect(parent.getByTestId('physical-delivery')).toContainText('Заказ готов к передаче');
    await transfer(p);
    await expect(parent.getByTestId('physical-delivery')).toContainText('Заказ передан в учреждение');
    await expect(parent.getByTestId('physical-delivery')).not.toContainText('MF-R14-002');
    await parent.close();
  } else if (name === 'сотрудники вместе и отдельные сроки') {
    await screen(p, base);
    await ready(p);
    await ready(p, 'sun-staff');
    const b = p.getByTestId('transfer-batch');
    await expect(b).toContainText('3 пакета · 12 отпечатков');
    await expect(b).toContainText('Сотрудники');
    await expect(b).toContainText('13 сентября 2026');
    await expect(b).toContainText('14 сентября 2026');
    await transfer(p);
    const s = await read<ProductionState>(p, 'production:v1');
    expect(s.transfers).toHaveLength(1);
    expect(s.transfers![0]!.groups).toHaveLength(2);
    expect(s.transfers![0]!.groups[1]!.rows[0]!.sourceGroupName).toBe('Звёздочки');
    await expect(b).toHaveCount(0);
  } else if (name === 'предыдущие этапы обязательны') {
    const s = await read<ProductionState>(p, 'production:v1');
    delete s.jobs[0]!.versions[0]!.packages['r14-paid'];
    delete s.jobs[1]!.versions[0]!.startedAt;
    await put(p, 'production:v1', s);
    await screen(p, base);
    await expect(group(p)).toContainText('Сверьте все пакеты');
    await expect(group(p, 'sun-staff')).toContainText('Сначала учтите запуск');
    await expect(p.getByRole('button', { name: 'Отметить готовность', exact: true })).toHaveCount(0);
  } else if (name === 'неверная и будущая дата') {
    await screen(p, base);
    await group(p).getByRole('button', { name: 'Отметить готовность' }).click();
    await fill(p);
    for (const date of ['2026-09-08T12:01', '2026-09-08T11:59']) {
      await p.getByLabel('Дата и время (МСК)', { exact: true }).fill(date);
      await dialog(p).getByRole('button', { name: 'Подтвердить готовность' }).click();
      await expect(dialog(p)).toContainText('не раньше завершения предыдущего этапа');
    }
    await p.getByLabel('Дата и время (МСК)', { exact: true }).fill('2026-09-08T12:00');
    await submit(p);
  } else if (name === 'получатель и подтверждение обязательны') {
    await screen(p, base);
    await ready(p);
    await p.getByRole('button', { name: 'Записать передачу' }).click();
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(p)).toContainText('кто принял продукцию');
    await expect(dialog(p)).toContainText('Подтвердите проверку');
    await expect(dialog(p)).toContainText('от 3 до 500');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toBeUndefined();
    await fill(p, 'transfer');
    await submit(p, 'transfer');
  } else if (name === 'снятие готовности возвращает комплектацию') {
    await screen(p, base);
    await ready(p);
    await go(p, base, '/cabinet/production/sun-stars');
    await expect(p.getByRole('button', { name: 'Снять комплектацию MF-R14-001', exact: true })).toHaveCount(0);
    await p.getByRole('link', { name: 'Перейти к доставке' }).click();
    await group(p).getByRole('button', { name: 'Снять готовность', exact: true }).click();
    await fill(p, 'unready');
    await submit(p, 'unready');
    await go(p, base, '/cabinet/production/sun-stars');
    await expect(p.getByRole('button', { name: 'Снять комплектацию MF-R14-001', exact: true })).toBeVisible();
  } else if (name === 'черновик после перезагрузки') {
    await screen(p, base);
    await group(p).getByRole('button', { name: 'Отметить готовность' }).click();
    await fill(p);
    const original = await p.evaluate(() => Object.entries(localStorage).find(([k]) => k.includes('delivery-draft:'))?.[1]);
    await go(p, base);
    await group(p).getByRole('button', { name: 'Отметить готовность' }).click();
    await expect(dialog(p)).toContainText('Восстановлен');
    expect(await p.evaluate(() => Object.entries(localStorage).find(([k]) => k.includes('delivery-draft:'))?.[1])).toBe(original);
    await submit(p);
  } else if (name === 'потерянный ответ не дублирует передачу') {
    await screen(p, base);
    await ready(p);
    await openTransfer(p);
    await put(p, 'delivery:lose-response-once', true);
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(p)).toContainText('Ответ потерян');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toHaveLength(1);
    await submit(p, 'transfer');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toHaveLength(1);
  } else if (name === 'двойное нажатие сохраняет один факт') {
    await screen(p, base);
    await ready(p);
    await openTransfer(p);
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setDelay(700));
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).dblclick();
    await expect(dialog(p)).toBeHidden();
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toHaveLength(1);
  } else if (name === 'конкурирующие вкладки не дублируют передачу') {
    await screen(p, base);
    await ready(p);
    const other = await this.context!.newPage();
    await go(other, base);
    await openTransfer(p);
    await openTransfer(other);
    await dialog(other).getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await fill(other, 'transfer');
    await submit(p, 'transfer');
    await other.getByRole('dialog').getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(other)).toContainText('Готовые пакеты изменились');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toHaveLength(1);
    await other.close();
  } else if (name === 'старая версия блокирует сохранение') {
    await screen(p, base);
    await group(p).getByRole('button', { name: 'Отметить готовность' }).click();
    await fill(p);
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.quote.lines[1]!.quantity = 1;
    await put(p, 'orders:v1', orders);
    await dialog(p).getByRole('button', { name: 'Подтвердить готовность' }).click();
    await expect(dialog(p)).toContainText('Версия или состав изменились');
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.ready).toBeUndefined();
  } else if (name === 'новая готовая группа требует повторной сверки') {
    await screen(p, base);
    await ready(p);
    await openTransfer(p);
    const s = await read<ProductionState>(p, 'production:v1');
    s.jobs[1]!.versions[0]!.ready = { at: now, actor: 'Анна', responsible: 'Анна', comment: 'Проверено' };
    s.jobs[1]!.revision++;
    await put(p, 'production:v1', s);
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(p)).toContainText('Готовые пакеты изменились');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toBeUndefined();
  } else if (name === 'изменение адреса блокирует старую передачу') {
    await screen(p, base);
    await ready(p);
    await openTransfer(p);
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.institutions[0]!.address = 'Новая улица, 20';
    await put(p, 'organization:v1', org);
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(p)).toContainText('Готовые пакеты изменились');
  } else if (name === 'продление обновляет доставку родителю') {
    await screen(p, base);
    await ready(p);
    const parent = await this.context!.newPage();
    await go(parent, base, '/orders/access/' + '1'.repeat(32));
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.groups[0]!.closesAt = '2026-09-12T09:00:00Z';
    org.groups[0]!.state = 'open';
    await put(p, 'organization:v1', org);
    await expect(group(p)).toContainText('19 сентября 2026');
    await expect(group(p)).toContainText('Приём открыт');
    await expect(p.getByTestId('transfer-batch')).toHaveCount(0);
    await expect(parent.locator('body')).toContainText('19 сентября 2026');
    await parent.close();
  } else if (name === 'исправленный пакет передаётся повторно отдельно') {
    await screen(p, base);
    await ready(p);
    await transfer(p);
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.quote.lines[1]!.quantity = 1;
    await put(p, 'orders:v1', orders);
    await expect(group(p)).toContainText('Состав изменился');
    await seedVersion(p);
    await ready(p);
    await expect(p.getByTestId('transfer-batch')).toContainText('1 пакет · 2 отпечатка');
    await transfer(p);
    const s = await read<ProductionState>(p, 'production:v1');
    expect(s.transfers).toHaveLength(2);
    expect(s.transfers![1]!.groups[0]!.rows.map((r) => r.orderId)).toEqual(['r14-paid']);
    expect(s.transfers![0]!.groups[0]!.rows).toHaveLength(2);
  } else if (name === 'возврат до передачи блокирует старый состав') {
    await screen(p, base);
    await ready(p);
    await openTransfer(p);
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.settlement = { ...settlement(orders[0]!), hold: true };
    await put(p, 'orders:v1', orders);
    await dialog(p).getByRole('button', { name: 'Подтвердить передачу' }).click();
    await expect(dialog(p)).toContainText('Готовые пакеты изменились');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toBeUndefined();
  } else if (name === 'куратор только просматривает своё учреждение') {
    await screen(p, base);
    await ready(p);
    await screen(p, base, 'curator');
    await expect(main(p)).not.toContainText('Чужое учреждение');
    await expect(p.getByRole('button', { name: /Отметить готовность|Снять готовность|Записать передачу/ })).toHaveCount(0);
    await expect(p.getByTestId('transfer-batch')).toBeVisible();
  } else if (name === 'руководитель видит безопасную сводку') {
    await screen(p, base);
    await ready(p);
    await ready(p, 'sun-staff');
    await transfer(p);
    await screen(p, base, 'head');
    await p.getByText('Группы и сроки передачи', { exact: true }).click();
    for (const text of ['MF-R14', 'buyer-r13', '900', 'A001', 'Пакеты и фактическая передача проверены', 'Чужое учреждение'])
      await expect(main(p)).not.toContainText(text);
    await expect(main(p)).toContainText('Пакетов: 3');
    await expect(p.getByRole('link', { name: 'Открыть комплектацию' })).toHaveCount(0);
  } else if (name === 'воспитатель видит только назначенную группу') {
    await screen(p, base);
    await ready(p);
    await ready(p, 'sun-staff');
    await transfer(p);
    await screen(p, base, 'teacher');
    await expect(group(p, 'sun-staff')).toHaveCount(0);
    await expect(main(p)).not.toContainText('Чужое учреждение');
    await expect(main(p)).toContainText('Пакетов: 2');
    await expect(p.getByRole('button', { name: 'Отметить готовность' })).toHaveCount(0);
  } else if (name === 'отзыв полномочий во время формы') {
    await screen(p, base);
    await group(p).getByRole('button', { name: 'Отметить готовность' }).click();
    await fill(p);
    const org = await read<OrganizationState>(p, 'organization:v1');
    org.users = [
      { id: 101, name: 'Анна', email: 'organizer@morefoto.test', role: 'curator', active: true, revision: 2, accessRevision: 1 }
    ];
    await put(p, 'organization:v1', org);
    await dialog(p).getByRole('button', { name: 'Подтвердить готовность' }).click();
    await expect(dialog(p)).toContainText('отмечает организатор');
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.ready).toBeUndefined();
  } else if (name === 'ошибка загрузки и повтор') {
    await login(p, base);
    await expect(p.locator('section[aria-label="Доступные учреждения"]')).toBeVisible();
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.failNextRequest());
    await p.getByRole('link', { name: 'Доставка', exact: true }).click();
    await expect(main(p).getByRole('alert')).toBeVisible();
    await main(p).getByRole('button', { name: 'Повторить загрузку' }).click();
    await expect(group(p)).toBeVisible();
  } else if (name === 'фильтры и пустой результат сохраняются в ссылке') {
    await screen(p, base);
    await go(p, base, '/cabinet/delivery?institution=foreign');
    await expect(main(p)).toContainText('По выбранным условиям групп нет');
    await p.reload({ waitUntil: 'networkidle' });
    await expect(main(p)).toContainText('По выбранным условиям групп нет');
    await go(p, base, '/cabinet/delivery?institution=sun&shoot=sun-summer-2026');
    await expect(group(p)).toBeVisible();
    await expect(main(p)).not.toContainText('Чужое учреждение');
  } else if (name === 'история остаётся после возврата') {
    await screen(p, base);
    await ready(p);
    await transfer(p);
    const before = (await read<ProductionState>(p, 'production:v1')).transfers;
    const orders = await read<OrderSnapshot[]>(p, 'orders:v1');
    orders[0]!.settlement = {
      ...settlement(orders[0]!),
      refunds: [
        {
          id: 'r15-refund',
          at: now,
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
    await go(p, base);
    await expect(p.getByTestId('transfer-history')).toContainText('Елена Соколова');
    expect((await read<ProductionState>(p, 'production:v1')).transfers).toEqual(before);
  } else if (name === 'просроченная передача сохраняет фактическую дату') {
    await screen(p, base);
    await ready(p);
    await p.evaluate(() => (window as DemoWindow).__MOREFOTO_MOCKS__?.setNow('2026-09-16T09:00:00Z'));
    await expect(group(p)).toContainText('Срок доставки прошёл');
    await transfer(p);
    await p.getByText('Группы и сроки передачи', { exact: true }).click();
    await expect(p.getByTestId('transfer-history')).toContainText('Передано после срока');
    expect((await read<ProductionState>(p, 'production:v1')).transfers![0]!.at).toBe('2026-09-16T09:00:00.000Z');
  } else if (name === 'клавиатура и отмена без записи') {
    await screen(p, base);
    const button = group(p).getByRole('button', { name: 'Отметить готовность' });
    await button.focus();
    await p.keyboard.press('Enter');
    await expect(dialog(p).getByRole('heading')).toBeFocused();
    await fill(p);
    await p.keyboard.press('Escape');
    await expect(dialog(p)).toBeHidden();
    expect((await read<ProductionState>(p, 'production:v1')).jobs[0]!.versions[0]!.ready).toBeUndefined();
    await button.click();
    await expect(dialog(p)).toContainText('Восстановлен');
    await submit(p);
  } else throw new Error('Неизвестная проверка R15: ' + name);
});
Then('R15 показывает {string} шириной {int}', async function (this: CustomWorld, screenName: string, width: number) {
  const p = this.page!;
  await p.setViewportSize({ width, height: 900 });
  await screen(p, this.baseUrl);
  if (screenName === 'пусто') {
    await go(p, this.baseUrl, '/cabinet/delivery?institution=foreign');
    await expect(main(p)).toContainText('По выбранным условиям групп нет');
  } else {
    await ready(p);
    await ready(p, 'sun-staff');
    if (screenName.startsWith('редактор')) {
      await openTransfer(p);
      await expect(dialog(p).getByRole('heading', { name: 'Передача в учреждение' })).toBeVisible();
    } else if (screenName === 'история' || screenName === 'руководитель' || screenName === 'родитель') {
      await transfer(p);
      if (screenName === 'руководитель') await screen(p, this.baseUrl, 'head');
      else if (screenName === 'родитель') {
        await go(p, this.baseUrl, '/orders/access/' + '1'.repeat(32));
        await expect(p.getByTestId('physical-delivery')).toBeVisible();
      } else await p.getByText('Группы и сроки передачи', { exact: true }).click();
    }
  }
  if (screenName.includes('200'))
    await p.evaluate(() => {
      const sizes = [...document.querySelectorAll('#cabinet-main *,[role="dialog"] *')].map(
        (e) => [e, getComputedStyle(e).fontSize] as const
      );
      for (const [e, size] of sizes) (e as HTMLElement).style.fontSize = parseFloat(size) * 2 + 'px';
    });
  await p.evaluate(() => {
    (document.activeElement as HTMLElement)?.blur();
    window.scrollTo(0, 0);
    document.querySelector('.admin-body')?.scrollTo(0, 0);
    return document.fonts.ready;
  });
  if (!screenName.startsWith('редактор')) await expect.poll(() => p.evaluate(() => window.scrollY)).toBe(0);
  expect(await p.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
  await mkdir('reports/e2e/r15-visual', { recursive: true });
  await p.screenshot({
    path: 'reports/e2e/r15-visual/' + screenName + '-' + width + '.png',
    fullPage: !screenName.startsWith('редактор'),
    animations: 'disabled'
  });
});
