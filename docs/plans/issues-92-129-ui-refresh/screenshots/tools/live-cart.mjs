// Live-mode cart and checkout screenshot with stubbed public API (port 4174). Usage: node live-cart.mjs <tag>
import { chromium } from '/app/node_modules/playwright/index.mjs';
const base = 'http://127.0.0.1:4174';
const tag = process.argv[2] ?? 'after';
const token = 'c'.repeat(64);
const thumbs = ['edd672846965dd04813c', 'bf51ef8b2f92c315179a', '0f21215dfee629dee643', '36f404fa7e3bfac17914'];
const img = (id, kind) => `/demo/gallery-v1/sun-stars/${id}-${kind}.webp`;
const children = ['A001', 'A002'].map((code, c) => ({
  code,
  photos: thumbs.slice(0, 2).map((t, i) => ({ id: `${code}-p${i + 1}`, assignmentId: `${code}-a${i + 1}`, code: `${code}-0${i + 1}`, thumbSrc: img(thumbs[(i + c) % 4], 'thumb'), previewSrc: img(thumbs[(i + c) % 4], 'preview'), width: 800, height: 1200 }))
}));
const gallery = { groupId: 'grp-1', institutionName: 'Детский сад «Солнышко»', groupName: 'Звёздочки', shootName: 'Осенняя съёмка', audience: 'regular', state: 'open',
  sentAt: '2026-10-11T09:00:00+00:00', closesAt: '2026-10-18T09:00:00+00:00', digitalSalesUntil: '2026-10-25T09:00:00+00:00', referenceNow: new Date().toISOString(),
  delivery: 'Фотографии передадим в учреждение до 25 октября.', curator: 'Маргарита Попова', children, token, updating: false };
const products = [
  ['print-10x15', 'Отпечаток 10 × 15', 'physical', 18000, '10 × 15 см'], ['print-15x23', 'Отпечаток 15 × 23', 'physical', 30000, '15 × 23 см'],
  ['print-20x30', 'Отпечаток 20 × 30', 'physical', 60000, '20 × 30 см'], ['magnet', 'Магнит 10 × 15', 'physical', 35000, '10 × 15 см'],
  ['digital', 'Электронный кадр', 'digital', 25000, ''], ['bundle', 'Все электронные кадры ребёнка', 'bundle', 100000, '']
].map(([id, name, kind, price, format]) => ({ id, name, description: kind === 'physical' ? 'Печать выбранного кадра на фотобумаге.' : 'Файл в полном разрешении без водяного знака.', kind, price, printCount: 1, format, staffDiscount: false, active: true }));
const catalog = { products, giftThreshold: 200000, giftForStaff: false, revision: 1, conditionsRevision: 1, capabilities: { receiptChannels: ['email'], purchaseEnabled: true } };
const legal = { documents: [
  { code: 'privacy', version: '2026-09-25', title: 'Политика обработки персональных данных', effectiveFrom: '2026-09-25' },
  { code: 'offer', version: '2026-10-12', title: 'Публичная оферта', effectiveFrom: '2026-10-12' },
  { code: 'buyer-consent', version: '2026-09-25', title: 'Согласие покупателя', effectiveFrom: '2026-09-25' }
], seller: { published: false, name: null, inn: null, ogrnip: null, address: null, email: null, phone: null } };
const photos = new Map(children.flatMap((c) => c.photos.map((p) => [p.assignmentId, { ...p, childCode: c.code }])));
function quote(lines) {
  const out = lines.map((l, i) => {
    const product = products.find((p) => p.id === l.productId), photo = photos.get(l.assignmentId);
    const total = product.price * (product.kind === 'physical' ? l.quantity : 1);
    return { id: 'line-' + i, assignmentId: l.assignmentId, childCode: photo.childCode, photoId: photo.id, productId: l.productId, quantity: l.quantity, product, photo, unitPrice: product.price, total, discount: 0, coveredByGift: false };
  });
  const total = out.reduce((s, l) => s + l.total, 0);
  return { quote: { lines: out, total, subtotal: total, discount: 0, giftSaving: 0, gifts: [], count: out.length, invalid: [], revision: 1, conditionsRevision: 1 }, quoteToken: 'quote-token' };
}
const json = (route, body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
async function api(route) {
  const url = new URL(route.request().url()), p = url.pathname, m = route.request().method();
  if (p === '/api/v1/public/legal/documents') return json(route, { data: legal });
  if (p === `/api/v1/public/galleries/${token}`) return json(route, { data: gallery });
  if (p === `/api/v1/public/galleries/${token}/catalog`) return json(route, { data: catalog });
  if (p === `/api/v1/public/galleries/${token}/quotes` && m === 'POST') return json(route, { data: quote(route.request().postDataJSON().lines) });
  return json(route, { error: { code: 'NOT_FOUND' } }, 404);
}
const browser = await chromium.launch();
for (const vp of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
  const context = await browser.newContext({ viewport: vp, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  await context.addInitScript(() => {
    localStorage.setItem('morefoto:cookie-notice:v1', 'accepted');
    localStorage.setItem('morefoto:cart:v1:grp-1', JSON.stringify([
      { assignmentId: 'A001-a1', productId: 'print-10x15', quantity: 2 },
      { assignmentId: 'A001-a2', productId: 'digital', quantity: 1 },
      { assignmentId: 'A002-a1', productId: 'print-20x30', quantity: 1 }
    ]));
  });
  await context.route((url) => url.pathname.startsWith('/api/'), api);
  const page = await context.newPage();
  page.on('pageerror', (e) => console.log('pageerror', e.message.slice(0, 160)));
  await page.goto(base + `/g/${token}/checkout`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1200);
  console.log(vp.name, 'url', new URL(page.url()).pathname.slice(-10), 'h-scroll', await page.evaluate(() => document.documentElement.scrollWidth > innerWidth));
  await page.screenshot({ path: `/shots/out/${tag}-${vp.name}-live-cart.png`, fullPage: true, animations: 'disabled' });
  await page.goto(base + `/g/${token}`);
  await page.getByRole('button', { name: /^Открыть кадр/ }).first().click();
  await page.waitForTimeout(900);
  await page.screenshot({ path: `/shots/out/${tag}-${vp.name}-live-photo.png`, animations: 'disabled' });
  await context.close();
}
await browser.close();
