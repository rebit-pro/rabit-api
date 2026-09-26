import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import type { Catalog } from '../../../src/modules/morefoto/commerce/types.js';
const groupToken = '158-group-7bc93615c4e94fd18a207d560b3e1f82';
const staffToken = '158-staff-f4bc8d62e1934a75b0172c6d980e5a43';
const conditionsPath = '/cabinet/institutions/sun/shoots/sun-summer-2026/conditions';
const productName = 'Отпечаток 10 × 15';
function page(w: CustomWorld) {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
}
async function login(p: Page, base: string, email = 'organizer@morefoto.test') {
  await p.evaluate(() => {
    for (const key of ['token', 'user', 'expires_at']) localStorage.removeItem('morefoto:demo:auth:' + key);
  });
  await p.goto(base + '/login', { waitUntil: 'networkidle' });
  await p.getByLabel('Email', { exact: true }).fill(email);
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function catalog(p: Page, base: string) {
  await p.goto(base + '/cabinet/catalog', { waitUntil: 'networkidle' });
  await expect(p.getByRole('button', { name: 'Новая продукция', exact: true })).toBeEnabled();
}
async function users(p: Page, base: string) {
  await p.goto(base + '/cabinet/users', { waitUntil: 'networkidle' });
  await expect(p.getByRole('button', { name: 'Новый пользователь', exact: true })).toBeEnabled();
}
async function save(p: Page) {
  await p.getByRole('dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
  await expect(p.getByRole('status').filter({ hasText: 'Изменения сохранены.' })).toBeVisible();
}
async function edit(p: Page, name: string) {
  await p
    .getByRole('button', { name: 'Редактировать ' + name, exact: true })
    .filter({ visible: true })
    .click();
  await expect(p.getByTestId('admin-dialog')).toBeVisible();
}
async function selection(p: Page, label: string, value: string) {
  await p.getByLabel(label, { exact: true }).locator('..').click();
  await p.getByRole('option', { name: value, exact: true }).click();
  await p.getByTestId('admin-dialog').locator('h2').click();
}
async function readCatalog(p: Page): Promise<Catalog> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:catalog:v1') ?? 'null'));
}
async function state(p: Page): Promise<OrganizationState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:organization:v1') ?? 'null'));
}
async function changePrice(p: Page, base: string, value: string) {
  await catalog(p, base);
  await edit(p, productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill(value);
  await save(p);
}
async function groupConditions(p: Page, base: string, group = 'sun-stars') {
  await p.goto(
    base + (group === 'sun-staff' ? conditionsPath.replace('sun-summer-2026', 'sun-autumn-2026') : conditionsPath) + '?group=' + group,
    { waitUntil: 'networkidle' }
  );
  await p.getByRole('button', { name: 'Изменить условия группы', exact: true }).click();
}
async function ownPrice(p: Page, base: string, value = '420') {
  await groupConditions(p, base);
  await p.getByLabel('Собственные условия группы', { exact: true }).check();
  await p.getByLabel('Цена: ' + productName, { exact: true }).fill(value);
  await save(p);
}
async function addPhoto(p: Page, base: string, token = groupToken, name = productName, quantity = 1) {
  await p.goto(base + '/g/' + token, { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await p.getByRole('option', { name, exact: true }).click();
  if (await p.getByRole('spinbutton').count()) await p.getByRole('spinbutton').fill(String(quantity));
  await p.getByTestId('add-to-cart').click();
  await expect(p.locator('.product-notice')).toBeVisible();
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
}
async function total(p: Page, value: number) {
  await expect
    .poll(async () => Number((await p.getByTestId('cart-total').innerText()).replace(/[^0-9,]/g, '').replace(',', '.')))
    .toBe(value);
}
async function order(p: Page, base: string) {
  await addPhoto(p, base, groupToken, 'Электронный кадр');
  await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await p.getByLabel('Имя покупателя', { exact: true }).fill('Покупатель R09');
  await p.getByLabel('Телефон', { exact: true }).fill('+7 (900) 123-45-67');
  await p.getByLabel('Email', { exact: true }).fill('buyer@example.test');
  await p.getByLabel('Состав и условия проверены', { exact: true }).check();
  await p.getByTestId('create-order').click();
  await expect(p).toHaveURL(/orders\/access\/[a-f0-9]{32}$/);
  return p.url();
}
Given('организатор открыл управление R09', async function (this: CustomWorld) {
  await login(page(this), this.baseUrl);
  await catalog(page(this), this.baseUrl);
});
Then('R09 создаёт и отключает печатный товар', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Новая продукция', exact: true }).click();
  await p.getByLabel('Название продукции', { exact: true }).fill('Карточки R09');
  await p.getByLabel('Формат', { exact: true }).fill('10 × 10');
  await p.getByLabel('Единица продажи', { exact: true }).fill('пара');
  await p.getByLabel('Цена, ₽', { exact: true }).fill('350,50');
  await p.getByLabel('Отпечатков в единице', { exact: true }).fill('2');
  await p.getByLabel('Описание', { exact: true }).fill('Два отпечатка выбранного кадра');
  await save(p);
  await addPhoto(p, this.baseUrl, groupToken, 'Карточки R09', 2);
  await total(p, 701);
  await expect(p.getByTestId('cart-line')).toContainText('Всего: 4');
  await catalog(p, this.baseUrl);
  await p.getByLabel('Поиск: ассортимент', { exact: true }).fill('Карточки R09');
  await edit(p, 'Карточки R09');
  await p.getByLabel('Доступно для покупки', { exact: true }).uncheck();
  await save(p);
  await p.reload({ waitUntil: 'networkidle' });
  const item = (await readCatalog(p)).products.find((x) => x.name === 'Карточки R09');
  expect(item).toMatchObject({ price: 35050, printCount: 2, format: '10 × 10', unit: 'пара', active: false });
});
Then('R09 проверяет поля и фокус ошибки', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Новая продукция', exact: true }).click();
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByLabel('Название продукции', { exact: true })).toBeFocused();
  await p.getByLabel('Название продукции', { exact: true }).fill(productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill('-10');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('Такое название уже есть.', { exact: true })).toBeVisible();
  await expect(p.getByText(/Введите цену от 0/)).toBeVisible();
});
Then('R09 восстанавливает и сбрасывает черновик', async function (this: CustomWorld) {
  const p = page(this);
  await edit(p, productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill('999');
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
  await p.reload({ waitUntil: 'networkidle' });
  await edit(p, productName);
  await expect(p.getByLabel('Цена, ₽', { exact: true })).toHaveValue('999');
  await expect(p.getByText('Восстановлен несохранённый черновик.', { exact: true })).toBeVisible();
  await p.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
  await expect(p.getByLabel('Цена, ₽', { exact: true })).toHaveValue('180');
});
Then('R09 разрешает конфликт прайса через актуальные данные', async function (this: CustomWorld) {
  const p = page(this);
  await edit(p, productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill('333');
  const other = await p.context().newPage();
  try {
    await changePrice(other, this.baseUrl, '555');
    await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
    await expect(p.getByText(/Данные изменены в другой вкладке/)).toBeVisible();
    expect((await readCatalog(p)).products[0]!.price).toBe(55500);
    await p.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await expect(p.getByLabel('Цена, ₽', { exact: true })).toHaveValue('555');
  } finally {
    await other.close();
  }
});
Then('R09 повторяет сохранение после сбоя', async function (this: CustomWorld) {
  const p = page(this);
  await edit(p, productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill('222');
  await p.evaluate(() => {
    (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void } }).__MOREFOTO_MOCKS__?.failNextRequest();
  });
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText(/Не удалось загрузить данные/)).toBeVisible();
  await save(p);
  expect((await readCatalog(p)).revision).toBe(2);
});
Then('R09 применяет отдельную цену группы в корзине', async function (this: CustomWorld) {
  const p = page(this);
  await ownPrice(p, this.baseUrl);
  await addPhoto(p, this.baseUrl);
  await total(p, 420);
  await addPhoto(p, this.baseUrl, '158-school-a419e576df224fa880c3bd92617e450b');
  await total(p, 180);
});
Then('R09 возвращает наследование условий', async function (this: CustomWorld) {
  const p = page(this);
  await ownPrice(p, this.baseUrl);
  await groupConditions(p, this.baseUrl);
  await p.getByLabel('Общие условия каталога', { exact: true }).check();
  await save(p);
  await changePrice(p, this.baseUrl, '320');
  await addPhoto(p, this.baseUrl);
  await total(p, 320);
});
Then('R09 скрывает отключённый товар в группе', async function (this: CustomWorld) {
  const p = page(this);
  await ownPrice(p, this.baseUrl);
  await catalog(p, this.baseUrl);
  await edit(p, productName);
  await p.getByLabel('Доступно для покупки', { exact: true }).uncheck();
  await save(p);
  await p.goto(this.baseUrl + '/g/' + groupToken, { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await expect(p.getByRole('option', { name: productName, exact: true })).toHaveCount(0);
});
Then('R09 применяет подарок после скидки сотрудника', async function (this: CustomWorld) {
  const p = page(this);
  // Synthetic image in this isolated browser only; the staff fixture deliberately has no published photos.
  await p.evaluate(() => {
    const png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=';
    localStorage.setItem(
      'morefoto:demo:photos:v1',
      JSON.stringify({
        covers: {},
        photos: [
          {
            id: 'r09-staff-photo',
            code: 'A001-01',
            thumbSrc: png,
            previewSrc: png,
            width: 1,
            height: 1,
            shootId: 'sun-autumn-2026',
            groupId: 'sun-staff',
            originalGroupId: 'sun-staff',
            childCode: 'A001',
            sequence: 1,
            filename: 'synthetic.png',
            bytes: 1,
            fingerprint: 'r09-synthetic',
            source: 'seed',
            revision: 1
          }
        ]
      })
    );
  });
  await groupConditions(p, this.baseUrl, 'sun-staff');
  await p.getByLabel('Собственные условия группы', { exact: true }).check();
  await p.getByLabel('Порог подарка, ₽', { exact: true }).fill('180');
  await p.getByLabel('Подарок также действует для сотрудников', { exact: true }).check();
  await save(p);
  await addPhoto(p, this.baseUrl, staffToken, productName, 2);
  await total(p, 180);
  await expect(p.getByTestId('cart-gift')).toHaveCount(1);
});
Then('R09 отвергает чужую группу съёмки', async function (this: CustomWorld) {
  const p = page(this);
  await p.goto(this.baseUrl + conditionsPath + '?group=school-1a', { waitUntil: 'networkidle' });
  await expect(p.getByRole('button', { name: 'Изменить условия группы', exact: true })).toBeDisabled();
  await expect(p.getByText('Группа этой съёмки не найдена. Выберите группу из списка.', { exact: true })).toBeVisible();
});
Then('R09 пересматривает неоплаченную цену', async function (this: CustomWorld) {
  const p = page(this);
  const url = await order(p, this.baseUrl);
  await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  const other = await p.context().newPage();
  try {
    await catalog(other, this.baseUrl);
    await edit(other, 'Электронный кадр');
    await other.getByLabel('Цена, ₽', { exact: true }).fill('450');
    await save(other);
  } finally {
    await other.close();
  }
  await p.getByTestId('pay-demo').click();
  await expect(p.getByLabel('Новый состав и итог проверены', { exact: true })).toBeVisible();
  await p.getByLabel('Новый состав и итог проверены', { exact: true }).check();
  await p.getByTestId('pay-demo').click();
  await expect
    .poll(() => p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') ?? '[]')[0]?.paymentStatus))
    .toBe('paid');
  await p.goto(url, { waitUntil: 'networkidle' });
  await expect(p.getByTestId('order-total')).toContainText('450');
});
Then('R09 сохраняет оплаченный снимок после изменения условий', async function (this: CustomWorld) {
  const p = page(this);
  const url = await order(p, this.baseUrl);
  await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  await p.getByTestId('pay-demo').click();
  await expect
    .poll(() => p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') ?? '[]')[0]?.paymentStatus))
    .toBe('paid');
  const before = await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'));
  await ownPrice(p, this.baseUrl, '999');
  await catalog(p, this.baseUrl);
  await edit(p, 'Электронный кадр');
  await p.getByLabel('Цена, ₽', { exact: true }).fill('550');
  await p.getByLabel('Доступно для покупки', { exact: true }).uncheck();
  await save(p);
  expect(await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'))).toBe(before);
  await p.goto(url, { waitUntil: 'networkidle' });
  await expect(p.getByTestId('order-total')).toContainText('250');
  await expect(p.getByTestId('order-payment-status')).toContainText('Оплачено');
});
async function newUser(p: Page, base: string, name = 'Педагог R09', email = 'new-r09@example.test') {
  await users(p, base);
  await p.getByRole('button', { name: 'Новый пользователь', exact: true }).click();
  await p.getByLabel('Имя пользователя', { exact: true }).fill(name);
  await p.getByLabel('Email пользователя', { exact: true }).fill(email);
}
Then('R09 создаёт педагога с доступом к группам съёмки', async function (this: CustomWorld) {
  const p = page(this);
  await newUser(p, this.baseUrl);
  await selection(p, 'Съёмка для назначения групп', 'Детский сад «Солнечный» → Осенняя съёмка · 2026');
  await p.getByRole('button', { name: 'Добавить группы съёмки', exact: true }).click();
  await save(p);
  const s = await state(p);
  const created = s.users.find((x) => x.email === 'new-r09@example.test')!;
  expect(s.groups.filter((x) => x.teacherId === created.id).length).toBe(2);
  await login(p, this.baseUrl, created.email);
  await expect(p.locator('#cabinet-main')).toContainText('Пчёлки');
  await expect(p.locator('#cabinet-main')).toContainText('Сотрудники');
  await expect(p.locator('#cabinet-main')).not.toContainText('1 «А»');
  await expect(p.getByRole('link', { name: 'Каталог и цены', exact: true })).toHaveCount(0);
});
Then('R09 проверяет пользователя и защищает организатора', async function (this: CustomWorld) {
  const p = page(this);
  await newUser(p, this.baseUrl);
  await p.getByLabel('Email пользователя', { exact: true }).fill(' ORGANIZER@MOREFOTO.TEST ');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('Этот email уже используется.', { exact: true })).toBeVisible();
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
  await edit(p, 'Анна');
  await expect(p.getByLabel('Роль пользователя', { exact: true })).toBeDisabled();
  await expect(p.getByLabel('Активный пользователь', { exact: true })).toBeDisabled();
});
Then('R09 подтверждает замену руководителя', async function (this: CustomWorld) {
  const p = page(this);
  await newUser(p, this.baseUrl, 'Руководитель R09', 'head-r09@example.test');
  await selection(p, 'Роль пользователя', 'Руководитель учреждения');
  await selection(p, 'Назначенные учреждения', 'Детский сад «Солнечный»');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('Подтвердите замену текущих ответственных.', { exact: true }).first()).toBeVisible();
  await p.getByLabel('Заменить текущих ответственных', { exact: true }).check();
  await save(p);
  const s = await state(p);
  expect(s.institutions.find((x) => x.id === 'sun')?.headId).toBe(s.users.find((x) => x.email === 'head-r09@example.test')?.id);
  await login(p, this.baseUrl, 'head@morefoto.test');
  await expect(p.locator('#cabinet-main')).not.toContainText('Звёздочки');
});
Then('R09 отключает сотрудника в другой вкладке', async function (this: CustomWorld) {
  const p = page(this);
  await users(p, this.baseUrl);
  const other = await p.context().newPage();
  try {
    await other.goto(this.baseUrl, { waitUntil: 'networkidle' });
    await login(other, this.baseUrl, 'teacher@morefoto.test');
    await edit(p, 'Мария');
    await p.getByLabel('Активный пользователь', { exact: true }).uncheck();
    await save(p);
    await expect(other).toHaveURL(/login/);
    expect((await state(p)).groups.find((x) => x.id === 'sun-stars')?.teacherId).toBe(null);
    await other.getByLabel('Email', { exact: true }).fill('teacher@morefoto.test');
    await other.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
    await other.getByTestId('login-submit').click();
    await expect(other.getByText('Неверный email или пароль', { exact: true })).toBeVisible();
  } finally {
    await other.close();
  }
});
Then('R09 меняет роль и назначения сотрудника', async function (this: CustomWorld) {
  const p = page(this);
  await users(p, this.baseUrl);
  const other = await p.context().newPage();
  try {
    await other.goto(this.baseUrl, { waitUntil: 'networkidle' });
    await login(other, this.baseUrl, 'teacher@morefoto.test');
    await edit(p, 'Мария');
    await selection(p, 'Роль пользователя', 'Куратор');
    await selection(p, 'Назначенные учреждения', 'Школа · тестовая подборка');
    await save(p);
    await expect(other).toHaveURL(/login/);
    await login(other, this.baseUrl, 'teacher@morefoto.test');
    await expect(other.locator('#cabinet-main')).toContainText('Школа · тестовая подборка');
    await expect(other.locator('#cabinet-main')).not.toContainText('Детский сад «Солнечный»');
  } finally {
    await other.close();
  }
});
Then('сотрудник R09 {string} не управляет каталогом', async function (this: CustomWorld, email: string) {
  const p = page(this);
  await login(p, this.baseUrl, email);
  for (const path of ['/cabinet/catalog', '/cabinet/users', conditionsPath]) {
    await p.goto(this.baseUrl + path, { waitUntil: 'networkidle' });
    await expect(p.getByRole('button', { name: /Новая продукция|Новый пользователь|Изменить условия группы/ })).toHaveCount(0);
    await expect(p).not.toHaveURL(new RegExp(path + '$'));
  }
});
async function layout(p: Page, base: string, width: number, zoom = false) {
  await p.setViewportSize({ width, height: 920 });
  const suffix = zoom ? '-200pct' : '';
  await mkdir('reports/e2e/r09-visual', { recursive: true });
  async function shot(name: string) {
    if (zoom) await p.addStyleTag({ content: 'html{font-size:200% !important}' });
    await p.evaluate(() => window.scrollTo(0, 0));
    expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await p.screenshot({
      path: 'reports/e2e/r09-visual/' + name + '-' + width + suffix + '.png',
      fullPage: !name.includes('editor'),
      animations: 'disabled'
    });
  }
  await catalog(p, base);
  await shot('catalog');
  await edit(p, productName);
  await shot('product-editor');
  expect(await p.getByTestId('admin-dialog').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
  await expect(p.getByRole('button', { name: 'Редактировать ' + productName, exact: true }).filter({ visible: true })).toBeFocused();
  await groupConditions(p, base);
  await shot('conditions-editor');
  expect(await p.getByTestId('admin-dialog').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
  await shot('conditions');
  await users(p, base);
  await shot('users');
  await edit(p, 'Мария');
  await shot('user-editor');
  for (const hint of await p.getByTestId('admin-dialog').locator('.v-messages__message').all()) {
    if (await hint.isVisible())
      expect(
        await hint.evaluate((el) => parseFloat(getComputedStyle(el).lineHeight) / parseFloat(getComputedStyle(el).fontSize))
      ).toBeGreaterThanOrEqual(1.4);
  }
  expect(await p.getByTestId('admin-dialog').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
}
Then('R09 доступен на ширине {int}', async function (this: CustomWorld, width: number) {
  await layout(page(this), this.baseUrl, width);
});
Then('R09 доступен с увеличенным текстом', async function (this: CustomWorld) {
  await layout(page(this), this.baseUrl, 390, true);
});

Then('R09 применяет общий прайс комплекта', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Прайс и предложения', exact: true }).click();
  await p.getByLabel('Цена: Все электронные кадры ребёнка', { exact: true }).fill('750');
  await p.getByLabel('Предлагать подарок от суммы печатных товаров', { exact: true }).uncheck();
  await p.getByLabel('50% сотрудникам: Отпечаток 10 × 15', { exact: true }).uncheck();
  await save(p);
  const value = await readCatalog(p);
  expect(value.giftThreshold).toBe(0);
  expect(value.products.find((x) => x.id === 'print-10x15')?.staffDiscount).toBe(false);
  await addPhoto(p, this.baseUrl, groupToken, 'Все электронные кадры ребёнка');
  await total(p, 750);
});
Then('R09 отклоняет сохранение после истечения сессии', async function (this: CustomWorld) {
  const p = page(this);
  await edit(p, productName);
  await p.getByLabel('Цена, ₽', { exact: true }).fill('999');
  await p.evaluate(() => localStorage.removeItem('morefoto:demo:sessions:v1'));
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('Сессия истекла. Войдите снова.', { exact: true })).toBeVisible();
  expect(await readCatalog(p)).toBe(null);
  await expect(p.getByLabel('Цена, ₽', { exact: true })).toHaveValue('999');
});

Then('R09 сохраняет собственный email без ошибки перехода', async function (this: CustomWorld) {
  const p = page(this);
  await users(p, this.baseUrl);
  await edit(p, 'Анна');
  await p.getByLabel('Email пользователя', { exact: true }).fill('organizer-r09@example.test');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p).toHaveURL(/login/);
  await login(p, this.baseUrl, 'organizer-r09@example.test');
  await catalog(p, this.baseUrl);
  expect((await state(p)).users.find((x) => x.id === 101)?.email).toBe('organizer-r09@example.test');
});
