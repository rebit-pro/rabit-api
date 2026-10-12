import { Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function total(p: Page, rubles: number) {
  await expect
    .poll(async () => Number((await p.getByTestId('cart-total').innerText()).replace(/[^0-9,]/g, '').replace(',', '.')))
    .toBe(rubles);
}
async function fail(p: Page) {
  await p.evaluate(() => {
    (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void; setDelay(ms: number): void } }).__MOREFOTO_MOCKS__?.setDelay(600);
    (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void } }).__MOREFOTO_MOCKS__?.failNextRequest();
  });
}
Then('составные поля проверяют значения и выбор файла', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Проверить поля', exact: true }).click();
  await expect(p.getByTestId('ui-date')).toContainText('Выберите существующую дату');
  await expect(p.getByTestId('ui-amount')).toContainText('Укажите сумму');
  await p.getByTestId('ui-phone').locator('input').fill('8 (900) 123-45-67');
  await p.getByTestId('ui-date').locator('input').fill('2028-02-29');
  await expect(p.getByTestId('ui-phone').locator('input')).toHaveValue('+7 (900) 123-45-67');
  await p.getByTestId('ui-amount').locator('input').fill('1 234,56');
  await p.getByTestId('ui-quantity').getByRole('spinbutton').fill('3');
  await p
    .getByTestId('ui-file')
    .locator('input[type=file]')
    .setInputFiles({ name: 'пример.txt', mimeType: 'text/plain', buffer: Buffer.from('Тестовый файл') });
  await expect(p.getByTestId('ui-file')).toContainText('пример.txt');
  await p.getByRole('button', { name: 'Проверить поля', exact: true }).click();
  await expect(p.getByTestId('ui-composite-result')).toContainText('Количество: 3; сумма: 123456 коп.; дата: 2028-02-29');
  await p
    .getByTestId('ui-file')
    .locator('input[type=file]')
    .setInputFiles({ name: 'большой.txt', mimeType: 'text/plain', buffer: Buffer.alloc(5 * 1024 * 1024 + 1) });
  await expect(p.getByTestId('ui-file')).toContainText('не больше 5 МБ');
  await p.getByRole('button', { name: 'Проверить поля', exact: true }).click();
  await expect(p.getByTestId('ui-composite-result')).toHaveCount(0);
  await p.getByRole('button', { name: 'Убрать выбранный файл', exact: true }).click();
  await expect(p.getByTestId('ui-file')).not.toContainText('большой.txt');
  await p.getByRole('button', { name: 'Очистить сумму', exact: true }).click();
  await expect(p.getByTestId('ui-amount').locator('input')).toBeFocused();
  await expect(p.getByTestId('ui-amount').locator('input')).toHaveValue('');
});
Then('количество кадра проверяет границы до добавления', async function (this: CustomWorld) {
  const p = page(this);
  const input = p.getByRole('spinbutton');
  for (const invalid of ['', '0', '1.5', '100']) {
    await input.fill(invalid);
    await p.getByTestId('add-to-cart').click();
    await expect(input).toHaveAttribute('aria-invalid', 'true');
    await expect(input).toHaveValue(invalid);
    await expect(p.locator('.product-notice')).toHaveCount(0);
  }
  await input.fill('1');
  await expect(p.getByRole('button', { name: /^Уменьшить количество/ })).toBeDisabled();
  await input.fill('99');
  await expect(p.getByRole('button', { name: /^Увеличить количество/ })).toBeDisabled();
  await input.fill('2');
  await p.getByTestId('add-to-cart').click();
  await expect(p.locator('.product-notice')).toBeVisible();
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
  await total(p, 360);
});
Then('счётчик корзины восстанавливается после ошибки и позволяет повтор', async function (this: CustomWorld) {
  const p = page(this);
  const input = p.getByTestId('cart-line').getByRole('spinbutton');
  await input.fill('0');
  await input.press('Tab');
  await expect(input).toHaveAttribute('aria-invalid', 'true');
  await total(p, 360);
  await input.fill('3');
  await fail(p);
  await input.press('Tab');
  await expect(input).toBeDisabled();
  await expect(p.getByRole('alert')).toContainText('Проверьте соединение');
  await expect(input).toBeEnabled();
  await expect(input).toHaveValue('2');
  await total(p, 360);
  await input.fill('3');
  await input.press('Enter');
  await expect(input).toBeEnabled();
  await total(p, 540);
  await expect(input).toHaveValue('3');
  await p.reload({ waitUntil: 'networkidle' });
  await expect(input).toHaveValue('3');
  await total(p, 540);
});
Then('очистка сохраняет выбор после ошибки и возвращает фокус', async function (this: CustomWorld) {
  const p = page(this);
  const clear = p.getByRole('button', { name: 'Очистить корзину группы', exact: true });
  await clear.click();
  await p.keyboard.press('Escape');
  await expect(clear).toBeFocused();
  await clear.click();
  await fail(p);
  await p.getByRole('button', { name: 'Да, очистить', exact: true }).click();
  await expect(p.getByRole('dialog').getByRole('alert')).toContainText('Проверьте соединение');
  await expect(p.getByTestId('cart-line')).toHaveCount(1);
  await p.getByRole('button', { name: 'Да, очистить', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Корзина пока пуста', exact: true })).toBeVisible();
  await expect(p.getByRole('link', { name: 'Перейти к фотографиям', exact: true })).toBeFocused();
});
Then('очистка контактов сохраняет черновик', async function (this: CustomWorld) {
  const p = page(this);
  const email = p.getByRole('textbox', { name: 'Email', exact: true });
  // E8: the email is the only contact; the form has no name and no phone.
  await expect(p.getByRole('textbox', { name: 'Имя покупателя', exact: true })).toHaveCount(0);
  await expect(p.getByRole('textbox', { name: 'Телефон', exact: true })).toHaveCount(0);
  await email.focus();
  await p.keyboard.press('Tab');
  await expect(p.getByRole('button', { name: 'Очистить email', exact: true })).toBeFocused();
  await p.keyboard.press('Enter');
  await expect(email).toHaveValue('');
  await expect(email).toBeFocused();
  await p.reload({ waitUntil: 'networkidle' });
  await expect(email).toHaveValue('');
  await email.fill('parent@example.test');
  await expect(p.getByRole('textbox', { name: 'Комментарий — необязательно', exact: true })).toHaveValue('Тестовый комментарий');
  await p.getByRole('checkbox', { name: 'Состав и условия проверены', exact: true }).check();
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/\/orders\/access\/[a-f0-9]{32}$/);
  const buyer = await p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0].buyer);
  expect([buyer.email, buyer.name, buyer.phone]).toEqual(['parent@example.test', undefined, undefined]);
});
Then('корзина и оформление на ширине {int} не обрезают действия', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 1000 });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const clear = await p.getByRole('button', { name: 'Очистить корзину группы', exact: true }).boundingBox();
  const items = await p.locator('.cart-children').boundingBox();
  expect(clear && items && clear.y - (items.y + items.height)).toBeLessThanOrEqual(24);
  const quantity = await p.getByTestId('cart-line').locator('.ui-quantity').boundingBox();
  const price = await p.locator('.cart-line__total').boundingBox();
  expect(quantity && price && price.x - (quantity.x + quantity.width)).toBeLessThanOrEqual(24);
  await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await expect(p.locator('.checkout-contacts')).toBeVisible();
  const email = await p.locator('.contact-email .v-field').boundingBox();
  expect(email?.width).toBeLessThanOrEqual(560);
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '32px';
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
