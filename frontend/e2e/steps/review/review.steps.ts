import { Given, Then } from '@cucumber/cucumber';
import { expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { CustomWorld } from '../../support/world.js';
import type { PhotoState } from '../../../src/modules/morefoto/photos/types.js';
import type { ProductionState } from '../../../src/modules/morefoto/production/types.js';
import * as h from './helpers.js';
import { signOut } from '../../support/shell.js';
const page = (w: CustomWorld) => {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
};
Given('открыт единый набор R16', async function (this: CustomWorld) {
  await h.setup(page(this), this.baseUrl);
});
Then('R16 проходит путь {string}', { timeout: 240000 }, async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'подготовка и фактическая передача') {
    await h.go(p, base, h.regular);
    await expect(p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true })).toHaveCount(0);
    await h.checkGroup(p, base);
    expect((await h.org(p)).groups.find((g) => g.id === 'sun-stars')!.closesAt).toBeNull();
    await h.transmit(p, base, 'sun-stars', true);
    const g = (await h.org(p)).groups.find((g) => g.id === 'sun-stars')!;
    expect(g.sentAt).toBe('2026-09-08T09:00:00.000Z');
    expect(g.closesAt).toBe('2026-09-15T09:00:00.000Z');
    await h.go(p, base, h.regular);
    await expect(p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true })).toBeVisible();
    expect((await h.org(p)).groups.find((g) => g.id === 'sun-bees')!.state).toBe('preparing');
  } else if (name === 'полный льготный набор') {
    const before = await h.read<PhotoState>(p, 'photos:v1');
    const source = before.photos.filter((x) => x.groupId === 'sun-stars' && x.childCode === 'A001');
    await h.login(p, base, 'teacher');
    await h.go(p, base, '/cabinet/staff-requests');
    await p.getByRole('button', { name: 'Новый список', exact: true }).click();
    await p.getByLabel('Код ребёнка или снимка 1', { exact: true }).fill('A001-01');
    await h.save(p, 'Передать список куратору');
    await p.getByRole('link', { name: 'Открыть список', exact: true }).click();
    await expect(p).toHaveURL(/\/cabinet\/staff-requests\/[^/?#]+$/);
    const path = new URL(p.url()).pathname;
    await h.login(p, base, 'curator');
    await h.go(p, base, path);
    await p.getByRole('button', { name: 'Проверить и перенести', exact: true }).click();
    await expect(p.locator('.handoff-previews img')).toHaveCount(source.length);
    await p.getByLabel('Проверены все кадры, подтверждаю перенос наборов', { exact: true }).check();
    await h.save(p, 'Подтвердить перенос');
    const after = await h.read<PhotoState>(p, 'photos:v1');
    const moved = after.photos.filter((x) => source.some((s) => s.id === x.id));
    expect(moved).toHaveLength(source.length);
    expect(moved.every((x) => x.groupId === 'sun-staff')).toBe(true);
    expect(after.photos).toHaveLength(before.photos.length);
    await p.reload({ waitUntil: 'networkidle' });
    await expect(p.getByRole('button', { name: 'Проверить и перенести', exact: true })).toHaveCount(0);
    await h.openGroup(p, base, 'sun-staff');
    const code = moved[0]!.code;
    await h.add(p, base, code, h.physical, 1, '/g/review-staff');
    await h.add(p, base, code, 'Холст 30 × 45', 1, '/g/review-staff');
    const o = await h.create(p);
    await h.pay(p);
    expect(o.quote.lines.find((l) => l.productId === 'pair-10x15')!.discount).toBeGreaterThan(0);
    expect(o.quote.lines.find((l) => l.productId === 'canvas-30x45')!.discount).toBe(0);
    expect(o.quote.gifts).toHaveLength(0);
  } else if (name === 'покупка и сводка учреждения') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base);
    expect(await h.orders(p)).toHaveLength(1);
    expect(o.paymentAttempts).toHaveLength(1);
    expect(o.digitalPhotos).toHaveLength(1);
    await expect(p.getByTestId('download-archive')).toBeVisible();
    await h.login(p, base, 'curator');
    await h.card(p, base, o);
    await expect(p.getByTestId('staff-order-detail')).toContainText(h.longEmail);
    await h.login(p, base, 'head');
    await h.go(p, base, '/cabinet/institutions/sun');
    await expect(p.getByTestId('settlement-totals')).toContainText('Оплаченных заказов1');
    await expect(p.getByTestId('settlement-totals')).toContainText('Итого после возвратов250 ₽');
    await expect(p.locator('#cabinet-main')).not.toContainText(h.longEmail);
    expect((await h.orders(p))[0]).toEqual(o);
  } else if (name === 'дети подарок и перерасчёт') {
    await h.openGroup(p, base);
    await h.add(p, base, 'A001-01', 'Отпечаток 30 × 45');
    await h.add(p, base, 'A002-01', 'Отпечаток 30 × 45');
    await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
    await expect(p.getByTestId('cart-gift')).toHaveCount(0);
    await h.add(p, base, 'A002-01', 'Отпечаток 30 × 45');
    await h.add(p, base, 'A002-01', h.digital);
    await h.add(p, base, 'A002-02', 'Все электронные кадры ребёнка');
    await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
    await expect(p.getByTestId('cart-gift')).toHaveCount(1);
    const quantity = p.getByRole('spinbutton').last();
    await quantity.fill('1');
    await quantity.blur();
    await expect(p.getByTestId('cart-gift')).toHaveCount(0);
    await quantity.fill('2');
    await quantity.blur();
    await expect(p.getByTestId('cart-gift')).toHaveCount(1);
    await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
    await h.fillBuyer(p);
    await p.getByTestId('create-order').click();
    await expect(p).toHaveURL(/orders\/access/);
    const o = (await h.orders(p))[0]!;
    expect(o.quote.gifts).toEqual(['A002']);
    expect(o.quote.total).toBe(330000);
    expect(new Set(o.digitalPhotos.map((x) => x.id)).size).toBe(o.digitalPhotos.length);
    await h.pay(p);
  } else if (name === 'разные группы и повторная покупка') {
    await h.openGroup(p, base);
    const first = await h.purchase(p, base);
    await h.openGroup(p, base, 'school-1a');
    const photos = await h.read<PhotoState>(p, 'photos:v1');
    const code = photos.photos.find((x) => x.groupId === 'school-1a')!.code;
    await h.purchase(p, base, h.digital, 1, code, '/g/review-school');
    await h.purchase(p, base, h.digital, 1, 'A002-02');
    const all = await h.orders(p);
    expect(all).toHaveLength(3);
    expect(new Set(all.map((x) => x.accessKey)).size).toBe(3);
    expect(all[0]).toEqual(first);
    expect(all.map((x) => x.groupId)).toEqual(['sun-stars', 'school-1a', 'sun-stars']);
  } else if (name === 'отказ и ожидание без дубля') {
    await h.openGroup(p, base);
    await h.add(p, base);
    const o = await h.create(p);
    await h.pay(p, 'Отказ в оплате');
    await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
    await p.getByRole('radio', { name: 'Ожидание подтверждения', exact: true }).check();
    await p.getByTestId('pay-demo').dblclick();
    await expect(p.getByTestId('payment-status')).toContainText('Ожидаем подтверждение');
    await p.reload({ waitUntil: 'networkidle' });
    await expect(p.getByTestId('pay-demo')).toHaveCount(0);
    await p.getByRole('button', { name: 'Имитировать подтверждение', exact: true }).click();
    await expect(p.getByTestId('payment-status')).toContainText('подтверждена');
    const all = await h.orders(p);
    expect(all).toHaveLength(1);
    expect(all[0]!.paymentAttempts!.map((a) => a.status)).toEqual(['declined', 'paid']);
    await h.login(p, base, 'curator');
    await h.card(p, base, o);
    await expect(p.getByTestId('staff-order-detail')).toContainText('Оплачен');
  } else if (name === 'закрытие и поздняя оплата') {
    await h.openGroup(p, base);
    await h.add(p, base);
    const o = await h.create(p);
    await h.pay(p, 'Ожидание подтверждения');
    await h.controls(p, base, '2026-09-16T12:00');
    await h.go(p, base, '/orders/access/' + o.accessKey + '/payment');
    await expect(p.getByTestId('pay-demo')).toHaveCount(0);
    await p.getByRole('button', { name: 'Имитировать подтверждение', exact: true }).click();
    await expect(p.getByTestId('late-payment')).toBeVisible();
    const paid = (await h.orders(p))[0]!;
    expect(paid.latePayment).toBe(true);
    expect(paid.paymentStatus).toBe('paid');
    await h.login(p, base, 'curator');
    await h.card(p, base, o);
    await h.operation(p, 'Согласовать исполнение');
    await h.choose(p, 'Решение по исполнению', 'Исполнить заказ');
    await h.confirm(p);
    await h.parent(p, base, o);
    await expect(p.getByTestId('download-archive')).toBeVisible();
    expect((await h.orders(p))[0]!.paymentAttempts).toEqual(paid.paymentAttempts);
    await h.go(p, base, h.regular);
    await p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true }).click();
    await expect(p.getByTestId('add-to-cart')).toHaveCount(0);
  } else if (name === 'продление во всех ролях') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base);
    const path = await h.appeal(p, base, o);
    await h.login(p, base, 'curator');
    await h.go(p, base, path);
    await p.getByRole('button', { name: 'Продлить приём', exact: true }).click();
    await p.getByLabel('Новый срок приёма (МСК)', { exact: true }).fill('2026-09-20T18:00');
    await p.getByLabel('Причина продления', { exact: true }).fill('Согласовано по просьбе родителей R16');
    await p.getByLabel('Подтверждаю новые сроки приёма и доставки для всей группы', { exact: true }).check();
    await h.save(p, 'Подтвердить продление');
    await h.parent(p, base, o);
    await expect(p.getByTestId('order-period')).toContainText('20 сентября');
    await expect(p.getByTestId('order-period')).toContainText('27 сентября');
    for (const role of ['teacher', 'head', 'organizer']) {
      await h.login(p, base, role);
      await h.go(p, base, '/cabinet/links?group=sun-stars');
      await expect(p.getByTestId('link-sun-stars')).toContainText('20 сентября');
      await expect(p.getByTestId('link-sun-stars')).toContainText('27 сентября');
    }
  } else if (name === 'исправление возврат и учтённая печать') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base, h.physical, 2);
    const path = await h.appeal(p, base, o);
    await h.controls(p, base, '2026-09-16T12:00');
    await h.produce(p, base, o, false);
    const printed = await h.read<ProductionState>(p, 'production:v1');
    await h.login(p, base, 'curator');
    await h.go(p, base, path);
    await expect(p.getByTestId('case-detail')).toBeVisible();
    await h.card(p, base, o);
    await h.operation(p, 'Исправить позицию');
    await p.getByLabel('Новое количество', { exact: true }).fill('1');
    await h.confirm(p);
    await expect(p.getByTestId('settlement-history')).toContainText('перепечатки');
    await h.operation(p, 'Оформить возврат');
    await p.getByLabel('Сумма возврата, ₽', { exact: true }).fill('280');
    await h.confirm(p);
    await h.operation(p, 'Результат возврата');
    await h.choose(p, 'Результат возврата (демо)', 'Подтверждён');
    await h.confirm(p);
    await h.operation(p, 'Согласовать исполнение');
    await h.confirm(p);
    await h.parent(p, base, o);
    await expect(p.getByTestId('settlement-totals')).toContainText('280 ₽');
    expect((await h.orders(p))[0]!.quote).toEqual(o.quote);
    expect(await h.read(p, 'production:v1')).toEqual(printed);
    await h.login(p, base, 'head');
    await h.go(p, base, '/cabinet/institutions/sun');
    await expect(p.locator('#cabinet-main')).toContainText('280');
    await h.login(p, base);
    await h.go(p, base, '/cabinet/production/sun-stars');
    await expect(p.locator('#cabinet-main')).toContainText('4');
  } else if (name === 'файлы восстановление и календарный месяц') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base, 'Все электронные кадры ребёнка');
    const single = p.waitForEvent('download');
    await p.getByRole('button', { name: 'Скачать A002-01', exact: true }).click();
    const d = await single;
    const bytes = await readFile((await d.path())!);
    expect(bytes.subarray(0, 4).toString()).toBe('RIFF');
    expect(bytes.subarray(8, 12).toString()).toBe('WEBP');
    const zipped = p.waitForEvent('download');
    await p.getByTestId('download-archive').click();
    const zip = await zipped;
    const details = JSON.parse(
      execFileSync(
        'python3',
        [
          '-c',
          'import zipfile,json,sys; z=zipfile.ZipFile(sys.argv[1]); assert z.testzip() is None; print(json.dumps({n:len(z.read(n)) for n in z.namelist()}))',
          (await zip.path())!
        ],
        { encoding: 'utf8' }
      )
    );
    expect(Object.keys(details)).toHaveLength(o.digitalPhotos.length);
    expect(Object.values(details).every((n) => Number(n) > 1000)).toBe(true);
    await h.login(p, base, 'curator');
    await h.card(p, base, o);
    await h.operation(p, 'Повторное получение');
    await p.getByLabel('Проверенный email', { exact: true }).fill('restored-r16@example.test');
    await p.getByLabel('Заказ и контакт проверены по обращению родителя', { exact: true }).check();
    await h.confirm(p);
    await h.parent(p, base, o);
    await expect(p.getByTestId('sale-recovery')).toContainText('Отправка имитирована');
    expect((await h.orders(p))[0]!.paidAt).toBe(o.paidAt);
    await h.controls(p, base, '2026-10-08T11:59');
    await h.parent(p, base, o);
    await expect(p.getByTestId('download-archive')).toBeVisible();
    await h.controls(p, base, '2026-10-08T12:00');
    await h.parent(p, base, o);
    await expect(p.getByTestId('order-downloads')).toContainText('Срок доступа истёк');
    await expect(p.getByTestId('download-archive')).toHaveCount(0);
    await h.go(p, base, '/orders/access/' + o.number);
    await expect(p.locator('body')).not.toContainText(h.longEmail);
  } else if (name === 'печать комплектация и доставка') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base, h.physical, 3);
    await h.purchase(p, base);
    await h.add(p, base, 'A002-02', h.physical);
    await h.create(p);
    await h.controls(p, base, '2026-09-16T12:00');
    await h.produce(p, base, o);
    const before = await h.read<ProductionState>(p, 'production:v1');
    expect(before.jobs).toHaveLength(1);
    expect(before.jobs[0]!.versions[0]!.plan.rows.map((r) => ({ order: r.orderId, prints: r.prints }))).toEqual([
      { order: o.id, prints: 6 }
    ]);
    await p.reload({ waitUntil: 'networkidle' });
    await expect(p.getByRole('button', { name: 'Учесть запуск печати', exact: true })).toHaveCount(0);
    expect(await h.read(p, 'production:v1')).toEqual(before);
    await h.ship(p, base, o);
    await h.parent(p, base, o);
    await expect(p.getByTestId('physical-delivery')).toContainText('Заказ передан в учреждение');
    await expect(p.locator('.order-contacts')).not.toContainText('будут переданы');
  } else if (name === 'права и повторный вход') {
    await h.openGroup(p, base, 'school-1a');
    const photos = await h.read<PhotoState>(p, 'photos:v1');
    const code = photos.photos.find((x) => x.groupId === 'school-1a')!.code;
    const o = await h.purchase(p, base, h.digital, 1, code, '/g/review-school');
    for (const role of ['curator', 'teacher', 'head']) {
      await h.login(p, base, role);
      await h.go(p, base, '/cabinet/orders/' + o.id);
      await expect(p.locator('body')).not.toContainText(h.longEmail);
      await expect(p.getByTestId('staff-order-detail')).toHaveCount(0);
      await h.go(p, base, '/cabinet/production/school-1a');
      await expect(p.getByRole('button', { name: 'Сформировать задание', exact: true })).toHaveCount(0);
    }
    await h.login(p, base);
    await h.card(p, base, o);
    await signOut(p);
    await h.go(p, base, '/cabinet/orders/' + o.id);
    await expect(p).toHaveURL(/login/);
    await p.getByLabel('Email', { exact: true }).fill('teacher@morefoto.test');
    await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
    await p.getByTestId('login-submit').click();
    await expect(p).toHaveURL(/cabinet/);
    await expect(p.getByTestId('staff-order-detail')).toHaveCount(0);
    await expect(p.locator('body')).not.toContainText(h.longEmail);
  } else throw new Error(name);
});
Then('R16 проверяет состояние {string}', { timeout: 180000 }, async function (this: CustomWorld, name: string) {
  const p = page(this),
    base = this.baseUrl;
  if (name === 'исходный набор и явный сброс') {
    expect(await h.orders(p)).toEqual([]);
    await h.openGroup(p, base);
    await h.purchase(p, base);
    await h.go(p, base, '/demo/review');
    const old = await h.read<{ generation: string }>(p, 'review:session');
    await p.getByRole('button', { name: 'Начать проверку заново', exact: true }).click();
    await p.getByRole('button', { name: 'Закрыть редактор', exact: true }).click();
    expect(await h.orders(p)).toHaveLength(1);
    await p.getByRole('button', { name: 'Начать проверку заново', exact: true }).click();
    await h.save(p, 'Восстановить исходный набор');
    expect(await h.orders(p)).toEqual([]);
    expect((await h.org(p)).groups.every((g) => g.state === 'preparing')).toBe(true);
    expect((await h.read<{ generation: string }>(p, 'review:session')).generation).not.toBe(old.generation);
    await h.go(p, base, '/cabinet/overview');
    await expect(p).toHaveURL(/login/);
  } else if (name === 'защита существующих данных') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base);
    await p.evaluate(() => localStorage.removeItem('morefoto:demo:review:session'));
    await h.go(p, base, '/demo/review');
    await expect(p.getByRole('heading', { name: 'В браузере уже есть данные' })).toBeVisible();
    await expect(p.getByRole('button', { name: 'Подготовить набор проверки', exact: true })).toHaveCount(0);
    await expect(p.getByRole('button', { name: 'Начать проверку заново', exact: true })).toHaveCount(0);
    expect((await h.orders(p))[0]).toEqual(o);
  } else if (name === 'защита локальных фотографий') {
    await p.evaluate(async () => {
      localStorage.clear();
      await new Promise<void>((resolve, reject) => {
        const open = indexedDB.open('morefoto-demo-photos-v1', 1);
        open.onsuccess = () => {
          const db = open.result;
          const tx = db.transaction('previews', 'readwrite');
          tx.objectStore('previews').put(new Blob(['existing-photo']), 'existing:preview');
          tx.oncomplete = () => {
            db.close();
            resolve();
          };
          tx.onerror = () => reject(tx.error);
        };
        open.onerror = () => reject(open.error);
      });
    });
    await h.go(p, base, '/demo/review');
    await expect(p.getByRole('heading', { name: 'В браузере уже есть данные' })).toBeVisible();
    await expect(p.getByRole('button', { name: 'Подготовить набор проверки', exact: true })).toHaveCount(0);
    const stored = await p.evaluate(
      () =>
        new Promise<number>((resolve, reject) => {
          const req = indexedDB.open('morefoto-demo-photos-v1', 1);
          req.onsuccess = () => {
            const db = req.result;
            const get = db.transaction('previews').objectStore('previews').count();
            get.onsuccess = () => {
              db.close();
              resolve(get.result);
            };
            get.onerror = () => reject(get.error);
          };
          req.onerror = () => reject(req.error);
        })
    );
    expect(stored).toBe(1);
  } else if (name === 'устаревший возврат требует обновления') {
    await h.openGroup(p, base);
    const o = await h.purchase(p, base);
    await h.login(p, base, 'curator');
    await h.card(p, base, o);
    const other = await p.context().newPage();
    try {
      await h.card(other, base, o);
      await h.operation(other, 'Оформить возврат');
      await other.getByLabel('Причина операции', { exact: true }).fill('Первое решение до обновления заказа');
      await other.getByLabel('Подтверждаю изменения и последствия для оплаты, файлов и исполнения', { exact: true }).check();
      await h.operation(p, 'Оформить возврат');
      await p.getByLabel('Сумма возврата, ₽', { exact: true }).fill('100');
      await h.confirm(p);
      await other.getByRole('button', { name: 'Подтвердить операцию', exact: true }).click();
      await expect(other.locator('.management-error')).toContainText('измен');
      expect((await h.orders(p))[0]!.settlement!.refunds).toHaveLength(1);
      await other.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
      await expect(other.getByLabel('Сумма возврата, ₽', { exact: true })).toHaveValue('150.00');
    } finally {
      await other.close();
    }
  } else if (name === 'настройки переживают обновление') {
    await h.controls(p, base, '2026-09-20T18:00', 'Медленно · 1,5 с', true);
    await p.reload({ waitUntil: 'networkidle' });
    await expect(p.getByLabel('Время сценария (МСК)', { exact: true })).toHaveValue('2026-09-20T18:00');
    await expect(p.getByLabel('Сеть недоступна', { exact: true })).toBeChecked();
    await expect(p.getByRole('button', { name: 'Ошибка следующего запроса' })).toBeDisabled();
    expect(await h.read(p, 'runtime:delay')).toBe(1500);
  } else if (name === 'ошибка следующего запроса и повтор') {
    await h.openGroup(p, base);
    await h.go(p, base, '/demo/review');
    await p.getByRole('button', { name: 'Ошибка следующего запроса' }).click();
    await h.go(p, base, h.regular);
    await expect(p.getByText(/Проверьте соединение/)).toBeVisible();
    await p.getByRole('button', { name: /Повторить/ }).click();
    await expect(p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true })).toBeVisible();
    expect(await h.read(p, 'runtime:fail-next')).toBeNull();
    await p.evaluate(() => localStorage.clear());
    await p.addInitScript(() => {
      const original = indexedDB.open.bind(indexedDB);
      (window as Window & { restoreReviewStorage?: () => void }).restoreReviewStorage = () => {
        indexedDB.open = original;
      };
      indexedDB.open = () => {
        throw new Error('Локальное хранилище временно недоступно.');
      };
    });
    await h.go(p, base, '/demo/review');
    await expect(p.getByTestId('review-error')).toContainText('Локальное хранилище временно недоступно.');
    await p.evaluate(() => (window as Window & { restoreReviewStorage?: () => void }).restoreReviewStorage!());
    await p.getByRole('button', { name: 'Повторить', exact: true }).click();
    await expect(p.getByRole('button', { name: 'Подготовить набор проверки', exact: true })).toBeVisible();
    await expect(p.getByTestId('review-error')).toHaveCount(0);
  } else if (name === 'медленный запрос и реальная потеря сети') {
    await h.openGroup(p, base);
    await h.add(p, base);
    await h.checkout(p);
    await h.fillBuyer(p);
    await p.context().setOffline(true);
    try {
      await p.getByTestId('create-order').click();
      await expect(p.getByText(/Проверьте соединение/)).toBeVisible();
      expect(await h.orders(p)).toEqual([]);
    } finally {
      await p.context().setOffline(false);
    }
    await p.getByTestId('create-order').click();
    await expect(p).toHaveURL(/orders\/access/);
    await h.controls(p, base, '2026-09-08T12:00', 'Медленно · 3 с');
    await p.goto(base + h.regular, { waitUntil: 'domcontentloaded' });
    await expect(p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true })).toBeVisible({ timeout: 10000 });
  } else if (name === 'оформление черновик и двойное действие') {
    await h.openGroup(p, base);
    await h.add(p, base);
    await h.checkout(p);
    await h.fillBuyer(p);
    await p.reload({ waitUntil: 'networkidle' });
    await expect(p.getByLabel('Имя покупателя', { exact: true })).toHaveValue(h.longName);
    await p.getByLabel('Состав и условия проверены', { exact: true }).check();
    const second = await p.context().newPage();
    try {
      await second.goto(p.url(), { waitUntil: 'networkidle' });
      await h.fillBuyer(second);
      await Promise.all([p.getByTestId('create-order').click(), second.getByTestId('create-order').click()]);
      await expect(p).toHaveURL(/orders\/access/);
      await expect(second).toHaveURL(p.url());
      expect(await h.orders(p)).toHaveLength(1);
    } finally {
      await second.close();
    }
  } else if (name === 'сброс прерывает старую команду') {
    await h.openGroup(p, base);
    await h.controls(p, base, '2026-09-08T12:00', 'Медленно · 3 с');
    await h.add(p, base);
    await h.checkout(p);
    await h.fillBuyer(p);
    const control = await p.context().newPage();
    try {
      await h.go(control, base, '/demo/review');
      await control.getByRole('button', { name: 'Начать проверку заново', exact: true }).click();
      await p.clock.install();
      await p.getByTestId('create-order').click();
      await h.save(control, 'Восстановить исходный набор');
      await p.clock.fastForward(3500);
      await expect(p.getByRole('heading', { name: 'Сначала выберите фотографии', exact: true })).toBeVisible();
      await expect(p.getByTestId('create-order')).toHaveCount(0);
      expect(await h.orders(p)).toEqual([]);
    } finally {
      await control.close();
    }
  } else if (name === 'пустая съёмка и недоступное действие') {
    await h.login(p, base);
    await h.go(p, base, '/cabinet/links?group=sun-bees');
    await expect(p.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);
    await h.go(p, base, '/g/review-bees');
    await expect(p.getByRole('button', { name: /Открыть кадр/ })).toHaveCount(0);
    await h.go(p, base, '/cabinet/orders');
    await expect(p.locator('#cabinet-main')).toContainText('Заказ');
    expect(await h.orders(p)).toEqual([]);
  } else throw new Error(name);
});
