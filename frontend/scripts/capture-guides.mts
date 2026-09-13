import { chromium, expect, type Locator } from '@playwright/test';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import * as h from '../e2e/steps/review/helpers.ts';

// Documentation capture only: local demo, isolated browser storage.
// Every external request and every network mutation is blocked.
const base = 'http://127.0.0.1:8086';
const out = '/workspace/docs/user-guides/screens';
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 780 },
  locale: 'ru-RU',
  timezoneId: 'Europe/Moscow',
  deviceScaleFactor: 1
});
const blocked: string[] = [];
await context.route('**/*', async (route) => {
  const r = route.request(),
    u = new URL(r.url());
  if (u.origin !== base || !['GET', 'HEAD'].includes(r.method())) {
    blocked.push(r.method() + ' ' + u.origin + u.pathname);
    await route.abort('blockedbyclient');
    return;
  }
  await route.continue();
});
const p = await context.newPage();
p.setDefaultTimeout(12000);
const manifest: Array<{ id: string; width: number; file: string; role: string; form: boolean; section: string; url: string }> = [];
let role = 'public';
async function snap(id: string, anchor?: Locator, end = false, menu = false) {
  for (const width of [1440, 390]) {
    await p.setViewportSize({ width, height: 780 });
    await p.evaluate(() => document.fonts.ready);
    if (menu && width === 390) await p.getByRole('button', { name: 'Открыть меню', exact: true }).click();
    const dialog = p.getByRole('dialog');
    if (await dialog.count()) {
      const body = dialog.locator('.admin-body');
      if (await body.count()) await body.evaluate((el: HTMLElement, end: boolean) => (el.scrollTop = end ? el.scrollHeight : 0), end);
    } else if (anchor) {
      await anchor.scrollIntoViewIfNeeded();
      await anchor.evaluate((el: HTMLElement) => window.scrollTo(0, window.scrollY + el.getBoundingClientRect().top - 96));
    } else await p.evaluate(() => window.scrollTo(0, 0));
    await p.evaluate(() => {
      if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    });
    await p.waitForTimeout(220);
    const file = id + '-' + width + '.png';
    if (await dialog.count()) await dialog.screenshot({ path: out + '/' + file });
    else await p.screenshot({ path: out + '/' + file });
    manifest.push({
      id,
      width,
      file,
      role,
      form: !!(await dialog.count()),
      section: end ? 'end' : 'start',
      url: p.url().replace(/\/orders\/access\/[^/]+/, '/orders/access/[private]')
    });
    if (menu && width === 390) await p.keyboard.press('Escape');
  }
  await p.setViewportSize({ width: 1440, height: 780 });
  await writeFile(out + '/manifest.json', JSON.stringify({ base, isolated: true, blocked, shots: manifest }, null, 2));
  console.log('Captured ' + id);
}
async function go(path: string) {
  await h.go(p, base, path);
}
async function login(r: string) {
  await h.login(p, base, r);
  role = r;
}
async function button(name: string) {
  await p.getByRole('button', { name, exact: true }).click();
}
async function fill(label: string, value: string) {
  await p.getByLabel(label, { exact: true }).fill(value);
}
async function cancel() {
  const d = p.getByRole('dialog');
  const b = d.getByRole('button', { name: 'Отмена', exact: true });
  if (await b.count()) await b.click();
  else await d.getByRole('button', { name: 'Закрыть', exact: true }).click();
  await expect(d).toHaveCount(0);
}
const shoot = '/cabinet/institutions/sun/shoots/sun-summer-2026';
try {
  await go('/demo/review');
  await snap('training-start');
  await h.setup(p, base);
  await go('/login');
  await snap('login');
  await login('organizer');
  await snap('organizer-menu', undefined, false, true);
  await go('/cabinet/institutions');
  await snap('institutions');
  await button('Новое учреждение');
  await fill('Название учреждения', 'Детский сад «Радуга»');
  await fill('Адрес учреждения', 'Учебный адрес, дом 1');
  await snap('institution-form');
  await cancel();
  await go('/cabinet/institutions/sun');
  await snap('institution');
  await button('Новая съёмка');
  await fill('Название съёмки', 'Зимняя сказка');
  await fill('Дата съёмки', '2026-12-10');
  await snap('shoot-form');
  await cancel();
  await go(shoot);
  await snap('shoot');
  await button('Новая группа');
  await fill('Название группы', 'Ромашки');
  await snap('group-form');
  await h.save(p, 'Сохранить');
  await go(shoot + '/photos');
  await snap('photos');
  const photo = '/workspace/frontend/public/demo/gallery-v1/sun-stars/36f404fa7e3bfac17914-preview.webp';
  await p.locator('input[type=file]').setInputFiles({ name: 'Учебный-кадр.webp', mimeType: 'image/webp', buffer: await readFile(photo) });
  await snap('upload', p.getByRole('heading', { name: 'Подготовить фотографии', exact: true }));
  await button('Начать подготовку');
  await expect(p.getByTestId('upload-counts')).toContainText('Подготовлено: 1');
  await p.getByTestId('photo-filter').getByRole('combobox').first().click();
  await p.getByRole('option', { name: 'Без ребёнка', exact: true }).click();
  await p
    .getByRole('checkbox', { name: /Выбрать кадр/ })
    .first()
    .check();
  await p.getByTestId('child-code').locator('input').fill('B');
  await snap('assign', p.getByRole('heading', { name: 'Кадры группы', exact: true }));
  await button('Назначить ребёнку');
  await expect(p.getByRole('status').filter({ hasText: 'Кадры назначены' })).toBeVisible();
  await p.getByTestId('photo-filter').getByRole('combobox').first().click();
  await p.getByRole('option', { name: 'Ребёнок A002', exact: true }).click();
  await p
    .getByRole('checkbox', { name: /Выбрать кадр/ })
    .first()
    .check();
  await snap('cover', p.getByRole('heading', { name: 'Кадры группы', exact: true }));
  await button('Перенести весь набор');
  await p.getByTestId('move-code').locator('input').fill('C');
  await snap('move-form');
  await cancel();
  await go('/cabinet/catalog');
  await snap('catalog');
  await button('Новая продукция');
  await fill('Название продукции', 'Портрет 15 × 21');
  await fill('Цена, ₽', '350');
  await snap('product-form');
  await snap('product-form-end', undefined, true);
  await cancel();
  await button('Прайс и предложения');
  await snap('prices-form');
  await snap('prices-form-end', undefined, true);
  await cancel();
  await go(shoot + '/conditions');
  await snap('conditions');
  await button('Изменить условия группы');
  await snap('conditions-form');
  await cancel();
  await go('/cabinet/users');
  await snap('users');
  await button('Новый пользователь');
  await fill('Имя пользователя', 'Мария Проверочная');
  await fill('Email пользователя', 'maria@example.test');
  await h.choose(p, 'Роль пользователя', 'Куратор');
  await snap('user-form');
  await snap('user-form-end', undefined, true);
  await cancel();
  await go('/cabinet/staff-requests');
  await snap('staff-create');
  await button('Новый список');
  await fill('Код ребёнка или снимка 1', 'A001-01');
  await snap('staff-create-form');
  await h.save(p, 'Передать список куратору');
  await login('curator');
  await snap('curator-menu', undefined, false, true);
  await go('/cabinet/staff-requests');
  await snap('curator-staff');
  await p.getByRole('link', { name: 'Открыть список', exact: true }).first().click();
  await snap('staff-detail');
  await button('Запросить уточнение');
  await snap('staff-clarify');
  await cancel();
  await button('Проверить и перенести');
  await snap('staff-transfer');
  await snap('staff-transfer-end', undefined, true);
  await p.getByLabel('Проверены все кадры, подтверждаю перенос наборов', { exact: true }).check();
  await h.save(p, 'Подтвердить перенос');
  await login('organizer');
  await go('/cabinet/links?group=sun-stars');
  await snap('organizer-links');
  await button('Проверить ссылку');
  for (const s of [
    'Фотографии и коды проверены',
    'Продукция, цены и условия группы проверены',
    'Списки сотрудников и ответственные проверены'
  ])
    await p.getByLabel(s, { exact: true }).check();
  await snap('link-check');
  await h.save(p, 'Проверить ссылку');
  await button('Отметить передачу');
  await fill('Дата и время передачи (МСК)', '2026-09-08T12:00');
  await p.getByLabel('Подтверждаю факт передачи и указанные сроки', { exact: true }).check();
  await snap('link-transmit');
  await h.save(p, 'Отметить передачу ссылки');
  await login('curator');
  await go('/cabinet/links?group=sun-stars');
  await snap('curator-links');
  await button('Исправить дату');
  await snap('link-correct');
  await snap('link-correct-end', undefined, true);
  await cancel();
  await h.add(p, base, 'A002-01', h.physical, 3);
  await h.add(p, base, 'A002-02', h.digital);
  await h.checkout(p);
  await fill('Имя покупателя', 'Анна Проверочная');
  await fill('Телефон', '+7 (900) 123-45-67');
  await fill('Email', 'anna@example.test');
  await p.getByLabel('Состав и демонстрационные условия проверены', { exact: true }).check();
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/orders\/access/);
  const order = (await h.orders(p)).at(-1)!;
  await h.pay(p);
  const support = await h.appeal(p, base, order);
  for (const r of ['curator', 'organizer']) {
    await login(r);
    await go('/cabinet/orders');
    await snap(r + '-orders');
    await snap(r + '-orders-results', p.locator('.work-orders'));
    await h.card(p, base, order);
    await snap(r + '-order');
    await snap(r + '-settlement', p.getByTestId('settlement-panel'));
    await go('/cabinet/support');
    await snap(r + '-support');
    await go(support);
    await snap(r + '-case');
  }
  await login('curator');
  await go(support);
  await button('Ответ и состояние');
  await h.choose(p, 'Состояние обращения', 'В работе');
  await fill('Ответ родителю', 'Здравствуйте! Проверяем возможность продления. Сообщим решение в этом обращении.');
  await snap('reply-form');
  await h.save(p, 'Сохранить ответ');
  await button('Продлить приём');
  await fill('Новый срок приёма (МСК)', '2026-09-20T18:00');
  await fill('Причина продления', 'По согласованной просьбе группы');
  await snap('extension-form');
  await snap('extension-form-end', undefined, true);
  await cancel();
  await h.card(p, base, order);
  await h.operation(p, 'Исправить контакты');
  await fill('Проверенный email', 'anna.correct@example.test');
  await snap('contacts-form');
  await snap('contacts-form-end', undefined, true);
  await cancel();
  await h.operation(p, 'Исправить позицию');
  await snap('line-form');
  await snap('line-form-end', undefined, true);
  await cancel();
  await h.operation(p, 'Повторное получение');
  await snap('recovery-form');
  await snap('recovery-form-end', undefined, true);
  await cancel();
  await h.operation(p, 'Оформить возврат');
  await fill('Сумма возврата, ₽', '100');
  await snap('refund-form');
  await snap('refund-form-end', undefined, true);
  await h.confirm(p, 'Учебный пример частичного возврата');
  await snap('refund-pending', p.getByTestId('settlement-panel'));
  await h.operation(p, 'Результат возврата');
  await snap('refund-result');
  await cancel();
  await h.operation(p, 'Результат возврата');
  await h.choose(p, 'Результат возврата (демо)', 'Ошибка возврата');
  await h.confirm(p, 'Учебный пример ошибки возврата');
  await h.operation(p, 'Повторить возврат');
  await snap('refund-retry');
  await cancel();
  await h.operation(p, 'Исправить позицию');
  await h.choose(p, 'Новый кадр', 'A002-03');
  await h.confirm(p, 'Учебный пример замены кадра до печати');
  await h.operation(p, 'Согласовать исполнение');
  await snap('fulfilment-form');
  await snap('fulfilment-form-end', undefined, true);
  await h.confirm(p, 'Учебное согласование печати исправленного состава');
  await login('organizer');
  await h.controls(p, base, '2026-09-16T12:00');
  await go('/cabinet/production/sun-stars');
  await snap('production-before');
  await button('Сформировать задание');
  await snap('production-form');
  await h.save(p, 'Сохранить версию');
  await button('Учесть запуск печати');
  await snap('print-form');
  await h.save(p, 'Подтвердить запуск');
  await snap('production-version');
  await button('Скомплектовать ' + order.number);
  await snap('pack-form');
  await h.save(p, 'Сохранить комплектацию');
  await go('/cabinet/delivery');
  await snap('delivery');
  await p.getByTestId('delivery-group-sun-stars').getByRole('button', { name: 'Отметить готовность', exact: true }).click();
  await fill('Комментарий к действию', 'Состав учебного комплекта проверен');
  await p.getByLabel('Состав и фактическое событие проверены', { exact: true }).check();
  await snap('ready-form');
  await h.save(p, 'Подтвердить готовность');
  await snap('ready-batch', p.getByTestId('transfer-batch'));
  await p.getByTestId('delivery-group-sun-stars').getByRole('button', { name: 'Снять готовность', exact: true }).click();
  await snap('unready-form');
  await cancel();
  await p.getByTestId('transfer-batch').getByRole('button', { name: 'Записать передачу', exact: true }).click();
  await fill('Принял в учреждении', 'Мария Проверочная');
  await fill('Комментарий к действию', 'Передан учебный комплект фотографий');
  await p.getByLabel('Состав и фактическое событие проверены', { exact: true }).check();
  await snap('transfer-form');
  await snap('transfer-form-end', undefined, true);
  await h.save(p, 'Подтвердить передачу');
  await p.getByTestId('transfer-history').locator('summary').click();
  await snap('delivery-history', p.getByRole('heading', { name: 'Передано в учреждение', exact: true }));
  await go('/cabinet/profile');
  await snap('organizer-profile');
  await login('curator');
  await go('/cabinet/production/sun-stars');
  await snap('curator-production', p.getByRole('button', { name: 'Скачать CSV версии 1', exact: true }));
  await go('/cabinet/delivery');
  await p.getByTestId('transfer-history').locator('summary').click();
  await snap('curator-delivery', p.getByRole('heading', { name: 'Передано в учреждение', exact: true }));
  await go('/cabinet/profile');
  await snap('profile');
  console.log('SUCCESS ' + manifest.length + ' screenshots. Blocked network requests: ' + blocked.length);
} catch (e) {
  await context.storageState({ path: '/workspace/.deploy/guides-debug/capture-state.json', indexedDB: true });
  await writeFile('/workspace/.deploy/guides-debug/capture-url.txt', p.url());
  await p.screenshot({ path: '/workspace/.deploy/guides-debug/failure.png', fullPage: true });
  await writeFile('/workspace/.deploy/guides-debug/failure.txt', String(e) + '\n' + (await p.locator('body').innerText()));
  throw e;
} finally {
  await browser.close();
}
