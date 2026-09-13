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
Then('телефон и очистка контактов сохраняют черновик', async function (this: CustomWorld) {
  const p = page(this);
  const phone = p.getByRole('textbox', { name: 'Телефон', exact: true });
  const email = p.getByRole('textbox', { name: 'Email', exact: true });
  const name = p.getByRole('textbox', { name: 'Имя покупателя', exact: true });
  await phone.fill('89001234567');
  await phone.press('Home');
  await phone.press('ArrowRight');
  expect(await phone.evaluate((el) => (el as HTMLInputElement).selectionStart)).toBe(1);
  await phone.press('Delete');
  await expect(phone).toHaveValue('8001234567');
  await phone.press('Tab');
  await expect(phone).toHaveValue('8001234567');
  await phone.fill('+44 20 7946 0958');
  await email.focus();
  await expect(phone).toHaveValue('+44 20 7946 0958');
  await phone.fill('8 (900) 123-45-67');
  await email.focus();
  await expect(phone).toHaveValue('+7 (900) 123-45-67');
  for (const [label, input, restored] of [
    ['Очистить имя покупателя', name, 'Тестовый покупатель'],
    ['Очистить телефон', phone, '+7 (900) 123-45-67'],
    ['Очистить email', email, 'parent@example.test']
  ] as const) {
    await input.focus();
    await p.keyboard.press('Tab');
    await expect(p.getByRole('button', { name: label, exact: true })).toBeFocused();
    await p.keyboard.press('Enter');
    await expect(input).toHaveValue('');
    await expect(input).toBeFocused();
    await p.reload({ waitUntil: 'networkidle' });
    await expect(input).toHaveValue('');
    await input.fill(restored);
  }
  await expect(name).toHaveValue('Тестовый покупатель');
  await expect(phone).toHaveValue('+7 (900) 123-45-67');
  await expect(p.getByRole('textbox', { name: 'Комментарий — необязательно', exact: true })).toHaveValue('Тестовый комментарий');
  await p.getByRole('checkbox', { name: 'Состав и демонстрационные условия проверены', exact: true }).check();
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/\/orders\/access\/[a-f0-9]{32}$/);
  expect(await p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0].buyer.phone)).toBe('+79001234567');
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
  const name = await p.locator('.contact-name .v-field').boundingBox();
  expect(name?.width).toBeLessThanOrEqual(560);
  const phone = await p.locator('[name=buyer-phone]').boundingBox();
  if (width >= 768) expect(phone?.width).toBeLessThanOrEqual(280);
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '32px';
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
