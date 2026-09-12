import { When, Then } from '@cucumber/cucumber';
import { expect } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld) {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
When('добавляет продукцию {string} в количестве {int}', async function (this: CustomWorld, name: string, quantity: number) {
  const p = page(this);
  await expect(p.getByRole('combobox', { name: 'Продукция', exact: true })).toHaveCount(1);
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await p.getByRole('option', { name, exact: true }).click();
  const input = p.getByRole('spinbutton');
  if (await input.count()) await input.fill(String(quantity));
  await p.getByTestId('add-to-cart').click();
  await expect(p.getByTestId('add-to-cart')).toBeEnabled();
  await expect(p.locator('.product-notice')).toBeVisible();
});
When('переходит в корзину из кадра', async function (this: CustomWorld) {
  await page(this).getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
  await expect(page(this).getByRole('heading', { name: 'Корзина', exact: true })).toBeVisible();
});
When('открывает корзину галереи', async function (this: CustomWorld) {
  await page(this).getByRole('link', { name: 'Открыть корзину', exact: true }).click();
});
Then('в корзине {int} позиции', async function (this: CustomWorld, count: number) {
  await expect(page(this).getByTestId('cart-line')).toHaveCount(count);
});
Then('итог корзины равен {int} рублей', async function (this: CustomWorld, total: number) {
  await expect
    .poll(async () => Number((await page(this).getByTestId('cart-total').innerText()).replace(/[^0-9,]/g, '').replace(',', '.')))
    .toBe(total);
});
Then('в корзине есть подарок', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('cart-gift')).toHaveCount(1);
});
Then('подарок в корзине отсутствует', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('cart-gift')).toHaveCount(0);
});
When('удаляет из корзины продукцию {string}', async function (this: CustomWorld, id: string) {
  await page(this)
    .getByRole('button', { name: new RegExp('^Удалить .*:' + id + '$') })
    .click();
});
When('нажимает очистить корзину', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Очистить корзину группы', exact: true }).click();
});
When('подтверждает очистку корзины', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Да, очистить', exact: true }).click();
});
