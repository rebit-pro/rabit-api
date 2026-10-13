// Live-mode cabinet screenshots with stubbed API (port 4174). Usage: node live-cabinet.mjs <tag> [screens...]
import { chromium } from '/app/node_modules/playwright/index.mjs';
import * as fx from './fixtures.mjs';
const base = 'http://127.0.0.1:4174';
const tag = process.argv[2] ?? 'before';
const only = process.argv.slice(3);
const json = (route, body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
function page(items, url, size = 25) {
  const p = Number(url.searchParams.get('page') ?? 1), ps = Number(url.searchParams.get('pageSize') ?? size);
  return { data: { items: items.slice((p - 1) * ps, p * ps) }, meta: { page: p, pageSize: ps, total: items.length, totalPages: Math.max(1, Math.ceil(items.length / ps)) } };
}
async function api(route) {
  const url = new URL(route.request().url());
  const p = url.pathname;
  if (route.request().method() !== 'GET') return json(route, { error: { code: 'READ_ONLY_STUB' } }, 409);
  if (p === '/api/v1/me') return json(route, { data: fx.me });
  if (p === '/api/v1/institutions') {
    const q = (url.searchParams.get('q') ?? '').toLowerCase();
    return json(route, page(fx.institutions.filter((i) => i.name.toLowerCase().includes(q)), url));
  }
  let m;
  if ((m = p.match(/^\/api\/v1\/institutions\/([^/]+)\/shoots$/))) return json(route, page(fx.shoots.filter((s) => s.institutionId === m[1]), url));
  if ((m = p.match(/^\/api\/v1\/institutions\/([^/]+)$/))) return json(route, { data: fx.institutionDetail(m[1]) });
  if (p === '/api/v1/shoots') return json(route, page(fx.shoots, url));
  if ((m = p.match(/^\/api\/v1\/shoots\/([^/]+)$/))) return json(route, { data: fx.shootDetail(m[1]) });
  if (p === '/api/v1/orders') return json(route, { ...page(fx.orders, url), meta: { ...page(fx.orders, url).meta, summary: { total: 10, byProductionStatus: { 'not-started': 5, queued: 1, printing: 1, ready: 2, delivered: 1 } } } });
  return json(route, { error: { code: 'NOT_FOUND', message: 'stub' } }, 404);
}
const screens = {
  institutions: '/cabinet/institutions',
  shoot: '/cabinet/institutions/inst-1/shoots/shoot-1-1',
  institution: '/cabinet/institutions/inst-1',
  shoots: '/cabinet/shoots',
  orders: '/cabinet/orders'
};
const browser = await chromium.launch();
for (const vp of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
  const context = await browser.newContext({ viewport: vp, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  await context.addInitScript((user) => {
    localStorage.setItem('morefoto:cookie-notice:v1', 'accepted');
    localStorage.setItem('morefoto:live:auth:token', 'stub-token');
    localStorage.setItem('morefoto:live:auth:user', JSON.stringify(user));
    localStorage.setItem('morefoto:live:auth:expires_at', new Date(Date.now() + 3600e3).toISOString());
  }, fx.me);
  await context.route((url) => url.pathname.startsWith('/api/'), api);
  const pg = await context.newPage();
  pg.on('pageerror', (e) => console.log('pageerror', vp.name, e.message.slice(0, 160)));
  for (const [name, path] of Object.entries(screens)) {
    if (only.length && !only.includes(name)) continue;
    await pg.goto(base + path);
    await pg.waitForLoadState('networkidle');
    await pg.waitForTimeout(700);
    await pg.screenshot({ path: `/shots/out/${tag}-${vp.name}-cab-${name}.png`, fullPage: true, animations: 'disabled' });
    const wide = await pg.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    console.log(tag, vp.name, name, wide ? 'H-SCROLL' : 'ok');
  }
  await context.close();
}
await browser.close();
