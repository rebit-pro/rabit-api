import { Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import * as h from './helpers.js';
async function shot(p: Page, name: string, width: number) {
  await p.evaluate(async () => {
    await document.fonts.ready;
    if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    window.scrollTo(0, 0);
  });
  await h.noOverflow(p);
  await expect
    .poll(
      async () =>
        p.locator('.v-field:not(.v-field--disabled) .v-label.v-field-label').evaluateAll((nodes) => {
          const rgb = (v: string) => v.match(/[\d.]+/g)!.map(Number);
          const luminance = (c: number[]) =>
            c
              .slice(0, 3)
              .map((v) => {
                const x = v / 255;
                return x <= 0.04045 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
              })
              .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i]!, 0);
          return nodes
            .filter((n) => getComputedStyle(n).visibility === 'visible' && n.getBoundingClientRect().width > 0)
            .map((n) => {
              const css = getComputedStyle(n);
              let ancestor: Element | null = n;
              let bg = [255, 255, 255];
              while (ancestor) {
                const color = rgb(getComputedStyle(ancestor).backgroundColor);
                if (color.length === 3 || color[3] === 1) {
                  bg = color;
                  break;
                }
                ancestor = ancestor.parentElement;
              }
              const fg = rgb(css.color),
                opacity = Number(css.opacity) * (fg[3] ?? 1);
              const displayed = fg.slice(0, 3).map((v, i) => v * opacity + bg[i]! * (1 - opacity));
              const a = luminance(displayed),
                b = luminance(bg),
                ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
              return { label: n.textContent, ratio };
            })
            .filter((r) => r.ratio < 4.5);
        }),
      { message: 'Visible enabled field labels meet 4.5:1 contrast' }
    )
    .toEqual([]);

  await mkdir('reports/e2e/r16', { recursive: true });
  await p.screenshot({
    path: `reports/e2e/r16/${name}-${width}.png`,
    fullPage: !(await p.getByRole('dialog').count()),
    animations: 'disabled'
  });
}
async function doubleText(p: Page) {
  await p.evaluate(() => {
    const nodes = [...document.querySelectorAll('main *, [role="dialog"] *')];
    const sizes = nodes.map((el) => parseFloat(getComputedStyle(el).fontSize));
    nodes.forEach((el, i) => {
      if (el instanceof HTMLElement) el.style.fontSize = sizes[i]! * 2 + 'px';
    });
  });
}
Then('R16 проверяет экраны на ширине {int}', { timeout: 300000 }, async function (this: CustomWorld, width: number) {
  const p = this.page!;
  const base = this.baseUrl;
  await p.setViewportSize({ width, height: 1000 });
  await shot(p, 'review', width);
  await h.openGroup(p, base);
  await h.add(p, base, 'A002-01', h.physical, 3);
  await h.add(p, base, 'A002-01', h.digital);
  await shot(p, 'photo', width);
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
  await shot(p, 'cart', width);
  await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await h.fillBuyer(p);
  await shot(p, 'checkout', width);
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/orders\/access/);
  const o = (await h.orders(p))[0]!;
  await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  await shot(p, 'payment', width);
  await p.getByTestId('pay-demo').click();
  await expect(p.getByTestId('payment-status')).toContainText('подтверждена');
  await h.parent(p, base, o);
  await shot(p, 'order', width);
  const appeal = await h.appeal(p, base, o);
  await h.login(p, base);
  const routes = [
    ['overview', '/cabinet/overview'],
    ['institutions', '/cabinet/institutions'],
    ['institution', '/cabinet/institutions/sun'],
    ['shoot', '/cabinet/institutions/sun/shoots/sun-summer-2026'],
    ['photos', '/cabinet/institutions/sun/shoots/sun-summer-2026/photos'],
    ['catalog', '/cabinet/catalog'],
    ['conditions', '/cabinet/institutions/sun/shoots/sun-summer-2026/conditions'],
    ['users', '/cabinet/users'],
    ['links', '/cabinet/links?group=sun-stars'],
    ['staff', '/cabinet/staff-requests'],
    ['orders', '/cabinet/orders'],
    ['staff-order', '/cabinet/orders/' + o.id],
    ['support', '/cabinet/support'],
    ['case', appeal],
    ['group', '/cabinet/groups/sun-stars'],
    ['profile', '/cabinet/profile']
  ];
  for (const [name, path] of routes) {
    await h.go(p, base, path!);
    await expect(p.locator('h1').first()).toBeVisible();
    await expect(p.locator('#cabinet-main')).not.toContainText('Страница не найдена');
    await expect(p.locator('#cabinet-main')).not.toContainText('Проверьте соединение');
    await shot(p, name!, width);
  }
  await h.controls(p, base, '2026-09-16T12:00');
  await h.produce(p, base, o);
  await shot(p, 'production', width);
  await h.ship(p, base, o);
  await shot(p, 'delivery', width);
  await h.parent(p, base, o);
  await expect(p.getByTestId('physical-delivery')).toContainText('Заказ передан в учреждение');
  await shot(p, 'delivered-order', width);
  await h.login(p, base, 'head');
  await h.go(p, base, '/cabinet/institutions/sun');
  await shot(p, 'head-summary', width);
});
Then(
  'R16 проверяет увеличение текста и клавиатуру на ширине {int}',
  { timeout: 180000 },
  async function (this: CustomWorld, width: number) {
    const p = this.page!,
      base = this.baseUrl;
    await p.setViewportSize({ width, height: 1000 });
    await doubleText(p);
    await shot(p, 'review-text200', width);
    await h.go(p, base, '/demo/review');
    const reset = p.getByRole('button', { name: 'Начать проверку заново', exact: true });
    await reset.focus();
    await p.keyboard.press('Enter');
    await expect(p.getByRole('dialog')).toBeVisible();
    await expect(p.getByRole('dialog').locator('h2')).toBeFocused();
    await expect(p.getByRole('dialog')).toContainText('Отмена сохранит текущий набор без изменений.');
    await expect(p.getByRole('dialog')).not.toContainText('остаются в черновике');
    await doubleText(p);
    await shot(p, 'reset-text200', width);
    await p.keyboard.press('Tab');
    expect(await p.evaluate(() => !!document.activeElement?.closest('[role="dialog"]'))).toBe(true);
    await p.keyboard.press('Escape');
    await expect(p.getByRole('dialog')).toHaveCount(0);
    await expect(reset).toBeFocused();
    await h.openGroup(p, base);
    await h.go(p, base, h.regular);
    const photo = p.getByRole('button', { name: 'Открыть кадр A002-01', exact: true });
    await photo.focus();
    await p.keyboard.press('Enter');
    await expect(p.getByRole('dialog')).toBeVisible();
    await p.keyboard.press('ArrowRight');
    await doubleText(p);
    await shot(p, 'photo-text200', width);
    await p.keyboard.press('Escape');
    await expect(p.getByRole('dialog')).toHaveCount(0);
    await h.add(p, base);
    await h.checkout(p);
    await p.getByTestId('create-order').click();
    await expect(p.getByLabel('Имя покупателя', { exact: true })).toBeFocused();
    await doubleText(p);
    await shot(p, 'checkout-errors-text200', width);
    await h.fillBuyer(p);
    await p.getByTestId('create-order').click();
    await expect(p).toHaveURL(/orders\/access/);
    await h.pay(p);
    await doubleText(p);
    await shot(p, 'order-text200', width);
  }
);
