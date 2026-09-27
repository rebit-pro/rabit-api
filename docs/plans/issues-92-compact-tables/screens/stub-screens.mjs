import { chromium } from '/app/node_modules/playwright/index.mjs';
const OUT = '/shots/' + (process.env.TAG || 'after');
import fs from 'node:fs';
fs.mkdirSync(OUT, { recursive: true });
const NOW = '2026-09-26T12:00:00Z';
const user = { id: 12, email: 'organizer@morefoto36.ru', name: 'Тарасов Александр', role: 'organizer', active: true, accessRevision: 1,
  permissions: ['organization.manage', 'media.manage', 'staff.manage', 'order.read', 'catalog.manage'] };
const P = (id, name, kind, price, format, unit, desc, active = true, staffDiscount = true) =>
  ({ id, name, kind, price, printCount: kind === 'physical' ? 1 : 0, format, unit, description: desc, staffDiscount, active });
const products = [
  P('p1', 'Все электронные кадры ребёнка', 'digital', 100000, '', 'комплект', 'Полный комплект только этой серии в текущей съёмке. Отдельные файлы не оплачиваются повторно.'),
  P('p2', 'Два отпечатка 10 × 15', 'physical', 28000, '10 × 15', 'комплект', 'Один комплект — два одинаковых отпечатка выбранного кадра.'),
  P('p3', 'Календарь 30 × 45', 'physical', 120000, '30 × 45', 'шт.', 'Один календарь с выбранным кадром. Макет согласуется перед печатью.'),
  P('p4', 'Магнит 10 × 15', 'physical', 35000, '10 × 15', 'шт.', 'Один фотомагнит с выбранным кадром.'),
  P('p5', 'Отпечаток 15 × 21', 'physical', 25000, '15 × 21', 'шт.', 'Печать на матовой бумаге.'),
  P('p6', 'Отпечаток 20 × 30', 'physical', 40000, '20 × 30', 'шт.', 'Печать на матовой бумаге.'),
  P('p7', 'Электронный кадр', 'digital', 15000, '', 'шт.', 'Один файл в полном разрешении.'),
  P('p8', 'Комплект «Выпускник»', 'bundle', 250000, 'альбом + файлы', 'комплект', 'Альбом 20 × 30 и все электронные кадры ребёнка.'),
  P('p9', 'Фотокнига 20 × 20', 'physical', 180000, '20 × 20', 'шт.', 'Твёрдая обложка, 20 разворотов.', false),
  P('p10', 'Кружка с фото', 'physical', 60000, '330 мл', 'шт.', 'Белая керамическая кружка с выбранным кадром.', true, false)
];
const inst = { id: '64cb74a9-7816-483b-b6ac-e843a746cd41', name: 'Детский Сад "МОЗАИКА-СИНТЕЗ"', address: 'г. Москва, ул. Маршала Жукова, д. 74/2', revision: 3,
  curatorId: 21, headId: null, curatorName: 'Попова Елена', headName: null };
const shoots = [
  { id: 's1', institutionId: inst.id, name: 'Съемка проектная в детском саду №5', date: '2026-09-18', revision: 2 },
  { id: 's2', institutionId: inst.id, name: 'Тест', date: null, revision: 1 },
  { id: 's3', institutionId: inst.id, name: 'Осенний портрет', date: '2026-10-05', revision: 1 }
];
const G = (id, shootId, name, status, sentAt = null, closesAt = null, deliveryDueAt = null, groupKind = 'regular') =>
  ({ id, shootId, name, groupKind, revision: 1, teacherId: null, status, timezone: 'Europe/Moscow', sentAt, closesAt, deliveryDueAt });
const groups = [
  G('g1', 's2', 'Тест', 'open', '2026-09-26T09:00:00Z', '2026-10-03T09:00:00Z', '2026-10-10T09:00:00Z'),
  G('g2', 's1', 'Средняя группа', 'preparing'),
  G('g3', 's1', 'Старшая группа «Солнышко»', 'open', '2026-09-22T09:00:00Z', '2026-09-29T09:00:00Z', '2026-10-06T09:00:00Z'),
  G('g4', 's1', 'Подготовительная группа', 'closed', '2026-09-12T09:00:00Z', '2026-09-19T09:00:00Z', '2026-09-26T09:00:00Z'),
  G('g5', 's1', 'Сотрудники', 'preparing', null, null, null, 'staff'),
  G('g6', 's3', 'Младшая группа «Капельки»', 'preparing')
];
const shootName = Object.fromEntries(shoots.map((s) => [s.id, s.name]));
const links = groups.map((g, i) => ({ groupId: g.id, name: g.name, kind: g.groupKind, institutionId: inst.id, institutionName: inst.name, shootId: g.shootId,
  shootName: shootName[g.shootId], revision: 1, signature: 'sig', prepared: g.status !== 'preparing' || g.id === 'g6', state: g.status, timezone: 'Europe/Moscow',
  problems: g.id === 'g2' ? ['unassignedPhotos'] : g.id === 'g5' ? ['noPhotos'] : [],
  sentAt: g.sentAt, closesAt: g.closesAt, deliveryAt: g.deliveryDueAt, photoCount: 40 + i * 13, childCount: 12 + i, curatorName: 'Попова Елена' }));
links.push({ ...links[2], groupId: 'g7', name: 'Группа «Звёздочки»', institutionId: 'i2', institutionName: 'Школа № 12', shootId: 's9', shootName: 'Первоклассники',
  state: 'open', sentAt: '2026-09-25T09:00:00Z', closesAt: '2026-10-02T09:00:00Z', deliveryAt: '2026-10-09T09:00:00Z' });
const page = (items) => ({ items, meta: { page: 1, pageSize: 25, total: items.length, totalPages: 1 } });
function reply(path) {
  if (path === '/api/v1/me') return { data: user };
  if (path === '/api/v1/catalog/products') return { data: { items: products, revision: 5 }, meta: { page: 1, pageSize: 25, total: products.length } };
  if (path === '/api/v1/institutions/' + inst.id) return { data: { ...inst, shoots: page(shoots), groups: { items: groups, meta: { ...page(groups).meta, summary: { byState: { preparing: 3, open: 2, closed: 1 } } } },
    summary: { availability: 'unavailable', reason: 'dependenciesNotReady' } } };
  if (path === '/api/v1/group-links') return { data: { items: links }, meta: { page: 1, pageSize: 100, total: links.length, totalPages: 1,
    summary: { referenceNow: NOW, byState: { preparing: 3, open: 3, closed: 1 }, closingSoon: 1, prepared: 1 } } };
  return null;
}
const browser = await chromium.launch();
const views = [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]];
const screens = [['catalog', '/cabinet/catalog'], ['institution', '/cabinet/institutions/' + inst.id], ['links', '/cabinet/links']];
for (const [view, viewport] of views) {
  const ctx = await browser.newContext({ viewport, locale: 'ru-RU', timezoneId: 'Europe/Moscow', isMobile: view === 'mobile', hasTouch: view === 'mobile' });
  await ctx.addInitScript((u) => {
    localStorage.setItem('morefoto:live:auth:token', 'stub'); localStorage.setItem('morefoto:cookie-notice:v1', '2026-09-26T00:00:00Z');
    localStorage.setItem('morefoto:live:auth:user', JSON.stringify(u));
    localStorage.setItem('morefoto:live:auth:expires_at', new Date(Date.now() + 7200000).toISOString());
  }, user);
  await ctx.route((url) => url.pathname.startsWith('/api/'), async (route) => {
    const url = new URL(route.request().url());
    const body = reply(url.pathname); if (process.env.DEBUG) console.log('api', url.pathname, !!body);
    if (!body) console.log('unstubbed', url.pathname);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body ?? { data: { items: [] }, meta: { page: 1, pageSize: 25, total: 0, totalPages: 1 } }) });
  });
  const pg = await ctx.newPage();
  pg.on('pageerror', (e) => console.log('pageerror', e.message)); if (process.env.DEBUG) pg.on('console', (m) => console.log('console', m.type(), m.text().slice(0, 300)));
  for (const [name, path] of screens) {
    await pg.goto('http://127.0.0.1:4173' + path);
    await pg.mouse.move(0, 0);
    await pg.waitForTimeout(2500); if (process.env.DEBUG) console.log('at', pg.url());
    await pg.screenshot({ path: `${OUT}/${name}-${view}.png`, fullPage: true });
    if (process.env.TAG.startsWith('after') && view === 'desktop' && name !== 'institution') {
      const boxes = pg.locator('.ui-table-desktop tbody tr .v-selection-control input');
      await boxes.nth(1).check(); await boxes.nth(2).check();
      await pg.waitForTimeout(400);
      await pg.screenshot({ path: `${OUT}/${name}-selected-${view}.png`, fullPage: false });
      await boxes.nth(1).uncheck(); await boxes.nth(2).uncheck();
    }
    if (process.env.TAG.startsWith('after') && view === 'desktop' && name === 'institution') {
      const groupsWidget = pg.locator('section[aria-label="Группы"]');
      const boxes = groupsWidget.locator('.ui-table-desktop tbody tr .v-selection-control input');
      await boxes.nth(0).check(); await boxes.nth(2).check();
      await pg.waitForTimeout(400);
      await groupsWidget.screenshot({ path: `${OUT}/institution-groups-selected-${view}.png` });
      await groupsWidget.getByRole('button', { name: 'Удалить', exact: true }).click();
      await pg.waitForTimeout(600);
      await pg.screenshot({ path: `${OUT}/institution-remove-dialog-${view}.png` });
      await pg.keyboard.press('Escape');
    }
    if (process.env.TAG.startsWith('after') && view === 'desktop' && name === 'catalog') {
      await pg.getByRole('button', { name: 'Удалить Магнит 10 × 15' }).click();
      await pg.waitForTimeout(600);
      await pg.screenshot({ path: `${OUT}/catalog-remove-dialog-${view}.png` });
      await pg.keyboard.press('Escape');
    }
  }
  await ctx.close();
}
await browser.close();
