import { When, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function fillBuyer(p: Page) {
  await p.getByRole('textbox', { name: 'Имя покупателя', exact: true }).fill('Тестовый покупатель');
  await p.getByRole('textbox', { name: 'Телефон', exact: true }).fill('+7 (900) 123-45-67');
  await p.getByRole('textbox', { name: 'Email', exact: true }).fill('parent@example.test');
  await p.getByRole('textbox', { name: 'Комментарий — необязательно', exact: true }).fill('Тестовый комментарий');
}
When('переходит к оформлению заказа', async function (this: CustomWorld) {
  await page(this).getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await expect(page(this).getByRole('heading', { name: 'Оформление заказа', exact: true })).toBeVisible();
});
When('заполняет контакты покупателя', async function (this: CustomWorld) {
  await fillBuyer(page(this));
});
When('подтверждает состав заказа', async function (this: CustomWorld) {
  await page(this).getByRole('checkbox', { name: 'Состав и демонстрационные условия проверены', exact: true }).check();
});
When('создаёт тестовый заказ', async function (this: CustomWorld) {
  await page(this).getByTestId('create-order').click();
});
Then('создан один заказ на {int} рублей', async function (this: CustomWorld, total: number) {
  const p = page(this);
  await expect(p).toHaveURL(/\/orders\/access\/[a-f0-9]{32}$/);
  await expect(p.getByRole('heading', { name: 'Заказ MF-000001', exact: true })).toBeVisible();
  await expect
    .poll(async () => Number((await p.getByTestId('order-total').innerText()).replace(/[^0-9,]/g, '').replace(',', '.')))
    .toBe(total);
  expect(await p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]').length)).toBe(1);
});
Then('заказы ещё не созданы', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('create-order')).toBeEnabled();
  expect(await page(this).evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]').length)).toBe(0);
});
Then('контакты покупателя показаны в заказе', async function (this: CustomWorld) {
  await expect(page(this).getByText('parent@example.test', { exact: true })).toBeVisible();
  await expect(page(this).getByText('+79001234567', { exact: true })).toBeVisible();
});
Then('форма оформления показывает обязательные ошибки', async function (this: CustomWorld) {
  await expect(page(this).getByText('Укажите имя: от 2 до 100 символов.', { exact: true })).toBeVisible();
  await expect(page(this).getByText('Укажите email в формате name@example.ru.', { exact: true })).toBeVisible();
  await expect(page(this).getByRole('textbox', { name: 'Имя покупателя', exact: true })).toBeFocused();
});
Then('контакты сохранены в форме оформления', async function (this: CustomWorld) {
  await expect(page(this).getByRole('textbox', { name: 'Имя покупателя', exact: true })).toHaveValue('Тестовый покупатель');
  await expect(page(this).getByRole('textbox', { name: 'Email', exact: true })).toHaveValue('parent@example.test');
  await expect(page(this).getByRole('textbox', { name: 'Комментарий — необязательно', exact: true })).toHaveValue('Тестовый комментарий');
});
When('следующий запрос оформления завершится ошибкой', async function (this: CustomWorld) {
  await page(this).evaluate(() => {
    (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void } }).__MOREFOTO_MOCKS__?.failNextRequest();
  });
});
When('демонстрационная цена отпечатка меняется на {int} рублей', async function (this: CustomWorld, price: number) {
  await page(this).evaluate((value) => {
    const catalog = JSON.parse(localStorage.getItem('morefoto:demo:catalog:v1') || 'null') || {
      revision: 1,
      giftThreshold: 200000,
      giftForStaff: false,
      products: [
        {
          id: 'print-10x15',
          name: 'Отпечаток 10 × 15',
          description: 'Один отпечаток выбранного кадра на фотобумаге.',
          kind: 'physical',
          price: 18000,
          printCount: 1,
          staffDiscount: true,
          active: true
        }
      ]
    };
    catalog.products.find((product: { id: string }) => product.id === 'print-10x15').price = value * 100;
    catalog.revision++;
    localStorage.setItem('morefoto:demo:catalog:v1', JSON.stringify(catalog));
    window.dispatchEvent(new Event('morefoto:demo:changed'));
  }, price);
});
Then('подтверждение состава сброшено', async function (this: CustomWorld) {
  await expect(page(this).getByRole('checkbox', { name: 'Состав и демонстрационные условия проверены', exact: true })).not.toBeChecked();
});
When('отправляет оформление одновременно из двух вкладок', async function (this: CustomWorld) {
  const p = page(this);
  const second = await p.context().newPage();
  try {
    await second.goto(p.url(), { waitUntil: 'networkidle' });
    await fillBuyer(second);
    await second.getByRole('checkbox', { name: 'Состав и демонстрационные условия проверены', exact: true }).check();
    await Promise.all([p.getByTestId('create-order').click(), second.getByTestId('create-order').click()]);
    await expect(p).toHaveURL(/\/orders\/access\/[a-f0-9]{32}$/);
    await expect(second).toHaveURL(p.url());
  } finally {
    await second.close();
  }
});
When('приём группы закрывается перед оформлением', async function (this: CustomWorld) {
  await page(this).evaluate(() => {
    (window as Window & { __MOREFOTO_MOCKS__?: { setGalleryScenario(value: string): void } }).__MOREFOTO_MOCKS__?.setGalleryScenario(
      'closed'
    );
  });
});
Then('канал MAX недоступен в оформлении', async function (this: CustomWorld) {
  await expect(page(this).getByRole('radio', { name: 'В MAX — пока недоступно', exact: true })).toBeDisabled();
});
When('демонстрационный канал MAX становится доступен', async function (this: CustomWorld) {
  await page(this).evaluate(() => localStorage.setItem('morefoto:demo:checkout:capabilities', JSON.stringify({ maxAvailable: true })));
});
When('выбирает чек в MAX', async function (this: CustomWorld) {
  await page(this).getByRole('radio', { name: 'В MAX', exact: true }).check();
});
Then('канал чека заказа равен {string}', async function (this: CustomWorld, channel: string) {
  expect(
    await page(this).evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0]?.buyer.receiptChannel)
  ).toBe(channel);
});
When('открывает заказ по одному его номеру', async function (this: CustomWorld) {
  await page(this).goto(this.baseUrl + '/orders/access/MF-000001', { waitUntil: 'networkidle' });
});
Then('чужие контакты не показаны', async function (this: CustomWorld) {
  await expect(page(this).getByText('parent@example.test', { exact: true })).toHaveCount(0);
});

When('ответ после создания заказа теряется', async function (this: CustomWorld) {
  await page(this).evaluate(() => localStorage.setItem('morefoto:demo:checkout:lose-response-once', 'true'));
});
When('продукция отпечатка становится недоступна', async function (this: CustomWorld) {
  await page(this).evaluate(() =>
    localStorage.setItem(
      'morefoto:demo:catalog:v1',
      JSON.stringify({ revision: 2, giftThreshold: 200000, giftForStaff: false, products: [] })
    )
  );
});
Then('создание заказа заблокировано', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('create-order')).toBeDisabled();
  expect(await page(this).evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]').length)).toBe(0);
});
