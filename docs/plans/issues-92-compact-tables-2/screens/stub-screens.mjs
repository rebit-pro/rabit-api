// Mockup stand for #92 slice 2: live cabinet screens in Chromium with Playwright API stubs (no backend).
// Run inside mcr.microsoft.com/playwright with Vite on 127.0.0.1:4173; TAG=before|after, output in /shots/<TAG>.
import { chromium } from '/app/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const TAG = process.env.TAG || 'after';
const OUT = '/shots/' + TAG;
fs.mkdirSync(OUT, { recursive: true });
const user = { id: 12, email: 'organizer@morefoto36.ru', name: 'Тарасов Александр', role: 'organizer', active: true, accessRevision: 1,
  permissions: ['organization.manage', 'media.manage', 'staff.manage', 'order.read', 'catalog.manage'] };
const inst = [
  { id: 'i1', name: 'Детский Сад "МОЗАИКА-СИНТЕЗ"' },
  { id: 'i2', name: 'Школа № 12' }
];
const shoots = [
  { id: 's1', institutionId: 'i1', name: 'Съемка проектная в детском саду №5' },
  { id: 's2', institutionId: 'i1', name: 'Осенний портрет' },
  { id: 's3', institutionId: 'i2', name: 'Первоклассники' }
];
const groups = [
  { id: 'g1', institutionId: 'i1', shootId: 's1', shootName: shoots[0].name, name: 'Старшая группа «Солнышко»', kind: 'regular', state: 'open' },
  { id: 'g2', institutionId: 'i1', shootId: 's1', shootName: shoots[0].name, name: 'Средняя группа', kind: 'regular', state: 'open' },
  { id: 'g3', institutionId: 'i1', shootId: 's2', shootName: shoots[1].name, name: 'Младшая группа «Капельки»', kind: 'regular', state: 'open' },
  { id: 'g4', institutionId: 'i2', shootId: 's3', shootName: shoots[2].name, name: '1 «А»', kind: 'regular', state: 'open' }
];
const at = (day, hour) => `2026-09-${String(day).padStart(2, '0')}T${String(hour).padStart(2, '0')}:15:00Z`;
const R = (id, shootId, by, byName, status, n, created, updated) => ({
  id, institutionId: shoots.find((s) => s.id === shootId).institutionId, shootId, createdBy: by, createdByName: byName, createdAt: created,
  revision: 1, status, comment: '', results: [],
  rows: Array.from({ length: n }, (_, i) => ({ id: id + 'r' + i, groupId: 'g1', code: 'A', childCode: 'A', photoIds: ['p1', 'p2'] })),
  history: [{ kind: 'submitted', actorId: by, actorName: byName, at: created, comment: '' },
    ...(updated ? [{ kind: status === 'transferred' ? 'transferred' : 'clarification', actorId: 21, actorName: 'Попова Елена', at: updated, comment: '' }] : [])]
});
const requests = [
  R('r1', 's1', 31, 'Иванова Мария', 'submitted', 3, at(26, 9)),
  R('r2', 's1', 32, 'Смирнова Ольга', 'clarification', 2, at(24, 11), at(25, 8)),
  R('r3', 's2', 12, 'Тарасов Александр', 'submitted', 1, at(25, 14)),
  R('r4', 's3', 33, 'Кузнецова Анна', 'transferred', 4, at(20, 10), at(22, 12)),
  R('r5', 's1', 34, 'Волкова Татьяна', 'transferred', 2, at(18, 9), at(19, 15)),
  R('r6', 's2', 35, 'Лебедева Ирина', 'submitted', 5, at(26, 16))
];
const buyers = [['Петрова Анна', '+79031234567', 'anna.petrova@mail.ru'], ['Соколов Дмитрий', '+79157654321', 'd.sokolov@yandex.ru'],
  ['Морозова Екатерина', '+79261112233', 'katya.m@gmail.com'], ['Новиков Сергей', '+79052223344', 'novikov.s@mail.ru'],
  ['Козлова Юлия', '+79163334455', 'yulia.k@yandex.ru'], ['Павлов Игорь', '+79264445566', 'ipavlov@mail.ru'],
  ['Фёдорова Наталья', '+79035556677', 'n.fedorova@gmail.com'], ['Михайлов Артём', '+79156667788', 'artem.m@yandex.ru'],
  ['Алексеева Ольга', '+79267778899', 'olga.a@mail.ru'], ['Егоров Максим', '+79038889900', 'egorov.max@gmail.com'],
  ['Орлова Светлана', '+79159990011', 's.orlova@yandex.ru'], ['Никитин Роман', '+79260001122', 'r.nikitin@mail.ru']];
const pay = ['paid', 'paid', 'unpaid', 'paid', 'declined', 'pending', 'paid', 'unpaid', 'paid', 'paid', 'unpaid', 'paid'];
const prod = ['printing', 'queued', 'not-started', 'ready', 'not-started', 'not-started', 'delivered', 'not-started', 'queued', 'printing', 'not-started', 'ready'];
const totals = [185000, 100000, 28000, 245000, 63000, 120000, 350000, 40000, 128000, 100000, 15000, 215000];
const orders = buyers.map(([name, phone, email], i) => {
  const g = groups[i % 4];
  return { id: 'o' + i, number: 'MF-' + String(260926 - i * 3).padStart(6, '0'), institutionId: g.institutionId,
    institutionName: inst.find((x) => x.id === g.institutionId).name, shootId: g.shootId, shootName: g.shootName, groupId: g.id, groupName: g.name,
    audience: 'regular', createdAt: at(26 - Math.floor(i / 2), 8 + i), buyer: { name, phone, email, comment: '', receiptChannel: 'email' },
    quote: { lines: [], total: totals[i], subtotal: totals[i], discount: 0, giftSaving: 0, gifts: [], count: 2, invalid: false, revision: 1, conditionsRevision: 1 },
    paymentStatus: pay[i], paidAt: pay[i] === 'paid' ? at(26 - Math.floor(i / 2), 9 + i) : null, latePayment: i === 9, productionStatus: prod[i], version: '1' };
});
const astatus = { paid: 'succeeded', unpaid: 'canceled', declined: 'canceled', pending: 'pending' };
const payments = orders.flatMap((o, i) => [
  ...(i % 3 === 0 ? [{ id: 'a' + i + 'x', orderId: o.id, orderNumber: o.number, institutionName: o.institutionName, groupName: o.groupName,
    createdAt: at(26 - Math.floor(i / 2), 7 + i), amount: o.quote.total, currency: 'RUB', paymentMethod: 'bank_card', status: 'canceled',
    paidAt: null, latePayment: false, incomeAmount: null, cancelReason: 'Покупатель закрыл страницу оплаты' }] : []),
  { id: 'a' + i, orderId: o.id, orderNumber: o.number, institutionName: o.institutionName, groupName: o.groupName, createdAt: at(26 - Math.floor(i / 2), 8 + i),
    amount: o.quote.total, currency: 'RUB', paymentMethod: i % 2 ? 'bank_card' : 'sbp', status: astatus[o.paymentStatus], paidAt: o.paidAt,
    latePayment: o.latePayment, incomeAmount: o.paymentStatus === 'paid' ? Math.round(o.quote.total * 0.965) : null, cancelReason: null }
]);
const collator = new Intl.Collator('ru');
const fields = {
  createdAt: (x) => x.createdAt, updatedAt: (x) => x.history.at(-1).at, createdByName: (x) => x.createdByName, status: (x) => x.status,
  buyerName: (x) => x.buyer.name, institutionName: (x) => x.institutionName, total: (x) => x.quote.total, paymentStatus: (x) => x.paymentStatus,
  productionStatus: (x) => x.productionStatus, orderNumber: (x) => x.orderNumber, amount: (x) => x.amount, paymentMethod: (x) => x.paymentMethod
};
function listed(items, params, fallback) {
  const key = params.get('sort') || fallback, dir = params.get('direction') === 'asc' ? 1 : -1, get = fields[key];
  const sorted = [...items].sort((a, b) => { const x = get(a), y = get(b); return dir * (typeof x === 'number' ? x - y : collator.compare(x, y)); });
  const page = Number(params.get('page') || 1), size = Number(params.get('pageSize') || 25);
  return { items: sorted.slice((page - 1) * size, page * size), meta: { page, pageSize: size, total: items.length, totalPages: Math.max(1, Math.ceil(items.length / size)) } };
}
function reply(url) {
  const path = url.pathname, q = url.searchParams;
  if (path === '/api/v1/me') return { data: user };
  if (path === '/api/v1/staff-requests') {
    const status = q.get('status');
    const { items, meta } = listed(requests.filter((r) => !status || r.status === status), q, 'updatedAt');
    const byStatus = { submitted: 0, clarification: 0, transferred: 0 };
    for (const r of requests) byStatus[r.status]++;
    return { data: { items, scope: { role: 'organizer', institutions: inst, shoots, groups } }, meta: { ...meta, summary: { byStatus } } };
  }
  if (path === '/api/v1/orders') {
    const { items, meta } = listed(orders, q, 'createdAt');
    const byProductionStatus = { 'not-started': 0, queued: 0, printing: 0, ready: 0, delivered: 0 };
    for (const o of orders) byProductionStatus[o.productionStatus]++;
    return { data: { items }, meta: { ...meta, summary: { total: orders.length, byProductionStatus } } };
  }
  if (path === '/api/v1/payments') { const { items, meta } = listed(payments, q, 'createdAt'); return { data: { items }, meta }; }
  if (path === '/api/v1/institutions') return { data: { items: inst.map((i) => ({ ...i, kind: 'institution', address: '', revision: 1 })) },
    meta: { page: 1, pageSize: 100, total: inst.length, totalPages: 1 } };
  return null;
}
const browser = await chromium.launch();
const views = [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]];
const screens = [['requests', '/cabinet/staff-requests'], ['orders', '/cabinet/orders'], ['payments', '/cabinet/payments']];
async function selectRows(pg, rows) {
  const boxes = pg.locator('.ui-table-desktop tbody tr .v-selection-control input');
  for (const i of rows) await boxes.nth(i).check();
  await pg.waitForTimeout(400);
}
for (const [view, viewport] of views) {
  if (TAG === 'before' && view === 'mobile') continue;
  const ctx = await browser.newContext({ viewport, locale: 'ru-RU', timezoneId: 'Europe/Moscow', isMobile: view === 'mobile', hasTouch: view === 'mobile' });
  await ctx.addInitScript((u) => {
    localStorage.setItem('morefoto:live:auth:token', 'stub'); localStorage.setItem('morefoto:cookie-notice:v1', '2026-09-27T00:00:00Z');
    localStorage.setItem('morefoto:live:auth:user', JSON.stringify(u));
    localStorage.setItem('morefoto:live:auth:expires_at', new Date(Date.now() + 7200000).toISOString());
  }, user);
  await ctx.route((url) => url.pathname.startsWith('/api/'), async (route) => {
    const url = new URL(route.request().url());
    const body = reply(url);
    if (!body && process.env.DEBUG) console.log('unstubbed', url.pathname);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body ?? { data: { items: [] }, meta: { page: 1, pageSize: 25, total: 0, totalPages: 1 } }) });
  });
  const pg = await ctx.newPage();
  pg.on('pageerror', (e) => console.log('pageerror', e.message));
  for (const [name, path] of screens) {
    await pg.goto('http://127.0.0.1:4173' + path);
    await pg.mouse.move(0, 0);
    await pg.waitForTimeout(2500);
    await pg.screenshot({ path: `${OUT}/${name}-${view}.png`, fullPage: true });
    if (TAG !== 'after' || view !== 'desktop') continue;
    await selectRows(pg, name === 'requests' ? [0, 3] : name === 'orders' ? [2, 3, 5] : [0, 1, 2]);
    await pg.screenshot({ path: `${OUT}/${name}-selected-${view}.png`, fullPage: true });
    if (name === 'payments') continue;
    await pg.getByRole('button', { name: name === 'orders' ? 'Удалить неоплаченные' : 'Удалить', exact: true }).click();
    await pg.waitForTimeout(600);
    await pg.screenshot({ path: `${OUT}/${name}-remove-dialog-${view}.png` });
    await pg.keyboard.press('Escape');
  }
  if (TAG === 'after' && view === 'desktop') {
    await pg.goto('http://127.0.0.1:4173/cabinet/orders?sort=total&direction=asc');
    await pg.waitForTimeout(2000);
    await pg.screenshot({ path: `${OUT}/orders-sorted-total-${view}.png`, fullPage: true });
  }
  await ctx.close();
}
await browser.close();
