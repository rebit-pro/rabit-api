// Baseline/after screenshots of the public purchase flow in demo mode (same components as live for gallery, cart, checkout).
import { chromium } from '/app/node_modules/playwright/index.mjs';
const base = 'http://127.0.0.1:4173';
const token = '158-group-7bc93615c4e94fd18a207d560b3e1f82';
const tag = process.argv[2] ?? 'before';
const browser = await chromium.launch();
for (const vp of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
  const context = await browser.newContext({ viewport: vp, locale: 'ru-RU', timezoneId: 'Europe/Moscow' });
  await context.addInitScript(() => { Object.defineProperty(Navigator.prototype, 'onLine', { get: () => true }); try { localStorage.setItem('morefoto:cookie-notice:v1', 'accepted'); } catch {} });
  const page = await context.newPage();
  const shot = (name) => page.screenshot({ path: `/shots/out/${tag}-${vp.name}-${name}.png`, fullPage: true, animations: 'disabled' });
  await page.goto(base + '/login');
  await page.waitForLoadState('networkidle');
  await shot('login');
  await page.goto(base + '/g/' + token);
  await page.getByRole('button', { name: /^Открыть кадр/ }).first().waitFor({ timeout: 20000 });
  await shot('gallery');
  await page.getByRole('button', { name: /^Открыть кадр/ }).first().click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: `/shots/out/${tag}-${vp.name}-photo.png`, animations: 'disabled' });
  await page.getByRole('dialog').getByRole('button', { name: 'Добавить в корзину' }).click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: `/shots/out/${tag}-${vp.name}-added.png`, animations: 'disabled' });
  await page.keyboard.press('Escape');
  await page.waitForTimeout(600);
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 520); });
  await page.waitForTimeout(300);
  await page.screenshot({ path: `/shots/out/${tag}-${vp.name}-gallery-with-cart.png`, animations: 'disabled' });
  await page.goto(base + '/g/' + token + '/cart');
  await page.waitForTimeout(2500);
  await shot('cart');
  console.log(vp.name, 'cart buttons:', (await page.locator('main').getByRole('button').allInnerTexts()).join(' | ').slice(0, 300), (await page.locator('main').getByRole('link').allInnerTexts()).join(' | ').slice(0, 300));
  await page.goto(base + '/g/' + token + '/checkout');
  await page.waitForTimeout(2500);
  await shot('checkout');
  await context.close();
}
await browser.close();
