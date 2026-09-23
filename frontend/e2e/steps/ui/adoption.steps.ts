import { Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function checkLayout(p: Page) {
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  for (const button of await p.locator('.v-btn:not(.v-btn--icon):visible').all()) {
    await expect
      .poll(() =>
        button.evaluate((el) => {
          const content = el.querySelector('.v-btn__content') as HTMLElement;
          const style = getComputedStyle(el);
          return el.clientHeight >= content.scrollHeight + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
        })
      )
      .toBe(true);
  }
}
async function checkBothSizes(p: Page) {
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '16px';
  });
  await checkLayout(p);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '32px';
  });
  await checkLayout(p);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '16px';
  });
}
Then('форма входа поддерживает очистку пароль и фокус ошибок', async function (this: CustomWorld) {
  const p = page(this);
  const email = p.getByTestId('login-email').locator('input');
  const password = p.getByTestId('login-password').locator('input');
  await p.getByTestId('login-submit').click();
  await expect(p.getByText('Введите email', { exact: true })).toBeVisible();
  await expect(email).toBeFocused();
  await email.fill('organizer@morefoto.test');
  await password.fill('morefoto-demo');
  await email.focus();
  await p.keyboard.press('Tab');
  await expect(p.getByRole('button', { name: 'Очистить email для входа', exact: true })).toBeFocused();
  await p.keyboard.press('Enter');
  await expect(email).toHaveValue('');
  await expect(email).toBeFocused();
  await expect(password).toHaveValue('morefoto-demo');
  await password.focus();
  await p.keyboard.press('Tab');
  await expect(p.getByRole('button', { name: 'Показать пароль', exact: true })).toBeFocused();
  await p.keyboard.press('Enter');
  await expect(password).toHaveAttribute('type', 'text');
  await p.getByRole('button', { name: 'Скрыть пароль', exact: true }).click();
  await expect(password).toHaveAttribute('type', 'password');
  await email.fill('none@example.test');
  await p.getByTestId('login-submit').click();
  await expect(p.getByTestId('login-api-error')).toBeVisible();
  await expect(p.getByTestId('login-api-error')).toBeFocused();
  await expect(email).toHaveValue('none@example.test');
  await expect(password).toHaveValue('morefoto-demo');
});
Then('поиск фотографий очищается и возвращает все кадры', async function (this: CustomWorld) {
  const p = page(this);
  const input = p.getByRole('textbox', { name: 'Код ребёнка или кадра', exact: true });
  await input.fill('A001-04');
  await expect(p.getByRole('button', { name: /^Открыть кадр / })).toHaveCount(1);
  await input.focus();
  await p.keyboard.press('Tab');
  await expect(p.getByRole('button', { name: 'Очистить поиск фотографий', exact: true })).toBeFocused();
  await p.keyboard.press('Enter');
  await expect(input).toHaveValue('');
  await expect(input).toBeFocused();
  await expect(p.getByRole('button', { name: /^Открыть кадр / })).toHaveCount(16);
});
Then('выпущенные экраны на ширине {int} соблюдают общие правила форм', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 1000 });
  await expect.poll(async () => Math.round((await p.getByTestId('pay-demo').boundingBox())?.height ?? 0)).toBe(48);
  await checkBothSizes(p);
  await p.getByTestId('pay-demo').click();
  await expect(p.getByTestId('payment-status')).toContainText('Тестовая оплата подтверждена');
  await checkBothSizes(p);
  await p.getByRole('link', { name: 'Вернуться к заказу', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Заказ MF-000001', exact: true })).toBeVisible();
  await checkBothSizes(p);
  await p.goto(this.baseUrl + '/login', { waitUntil: 'networkidle' });
  await expect.poll(async () => Math.round((await p.getByTestId('login-email').locator('.v-field').boundingBox())?.height ?? 0)).toBe(48);
  await checkBothSizes(p);
  await p.getByTestId('login-email').locator('input').fill('organizer@morefoto.test');
  await p.getByTestId('login-password').locator('input').fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p.getByRole('heading', { name: 'Кабинет организатора', exact: true })).toBeVisible();
  await checkBothSizes(p);
  const menu = p.getByRole('button', { name: 'Открыть меню', exact: true });
  if (await menu.isVisible()) await menu.click();
  await p.getByRole('link', { name: /^Профиль: / }).click();
  await expect(p.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  await checkBothSizes(p);
  await p.getByRole('button', { name: 'Выйти из кабинета', exact: true }).click();
  await expect(p).toHaveURL(/\/login$/);
});
