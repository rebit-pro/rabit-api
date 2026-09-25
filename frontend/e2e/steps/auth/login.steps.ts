import { Given, When, Then } from '@cucumber/cucumber';
import { expect } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
import { openUserMenu, signOut } from '../../support/shell.js';

function page(world: CustomWorld) {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}

async function submit(world: CustomWorld, email: string, password: string) {
  const browserPage = page(world);
  await browserPage.getByLabel('Email', { exact: true }).fill(email);
  await browserPage.getByLabel('Пароль', { exact: true }).fill(password);
  await browserPage.getByTestId('login-submit').click();
}

Given('пользователь открывает страницу входа', async function (this: CustomWorld) {
  await page(this).goto(this.baseUrl, { waitUntil: 'networkidle' });
  await expect(page(this)).toHaveURL(/\/login$/);
});

When('пользователь входит как {string}', async function (this: CustomWorld, email: string) {
  await submit(this, email, 'morefoto-demo');
  await expect(page(this)).toHaveURL(/\/cabinet\//);
});
When('пользователь вводит email {string} и пароль {string}', async function (this: CustomWorld, email: string, password: string) {
  await submit(this, email, password);
});
When('пользователь отправляет пустую форму', async function (this: CustomWorld) {
  await page(this).getByTestId('login-submit').click();
});
When('пользователь отправляет форму входа', async function (this: CustomWorld) {
  await page(this).getByTestId('login-submit').click();
});
Then('обязательные поля объясняют ошибки', async function (this: CustomWorld) {
  await expect(page(this).getByText('Введите email', { exact: true })).toBeVisible();
  await expect(page(this).getByText('Введите пароль', { exact: true })).toBeVisible();
  await expect(page(this)).toHaveURL(/\/login$/);
});
Then('форма входа не содержит витрину P2P и капчу', async function (this: CustomWorld) {
  await expect(page(this).getByRole('heading', { name: 'Вход в кабинет' })).toBeVisible();
  await expect(page(this).locator('body')).not.toContainText(/P2P|Trader|CAPTCHA|GeeTest|Зарегистрироваться/i);
  await expect(page(this).locator('script[src*="geetest"]')).toHaveCount(0);
  await expect(page(this).getByTestId('login-submit')).toBeEnabled();
});
Then('заголовок кабинета {string}', async function (this: CustomWorld, heading: string) {
  await expect(page(this).getByRole('heading', { name: heading, exact: true })).toBeVisible();
});
Then('кабинет содержит {string}', async function (this: CustomWorld, text: string) {
  await expect(page(this).locator('body')).toContainText(text);
});
Then('кабинет не содержит {string}', async function (this: CustomWorld, text: string) {
  await expect(page(this).locator('body')).not.toContainText(text);
});
Then('профиль и выход доступны', async function (this: CustomWorld) {
  await openUserMenu(page(this));
  await expect(page(this).getByRole('link', { name: 'Профиль', exact: true })).toBeVisible();
  await expect(page(this).getByRole('button', { name: 'Выйти', exact: true })).toBeVisible();
  await page(this).keyboard.press('Escape');
});
Then('пользователь должен увидеть ошибку авторизации {string}', async function (this: CustomWorld, text: string) {
  await expect(page(this).getByTestId('login-api-error')).toContainText(text);
  await expect(page(this)).toHaveURL(/\/login$/);
});
When('пользователь выбирает демонстрационную роль {string}', async function (this: CustomWorld, role: string) {
  await page(this).getByText('Демонстрационные доступы', { exact: true }).click();
  await page(this).getByRole('button', { name: role, exact: true }).click();
});
Then('поля заполнены для {string}', async function (this: CustomWorld, email: string) {
  await expect(page(this).getByLabel('Email', { exact: true })).toHaveValue(email);
  await expect(page(this).getByLabel('Пароль', { exact: true })).toHaveValue('morefoto-demo');
});
When('обновляет страницу', async function (this: CustomWorld) {
  await page(this).waitForURL(/\/cabinet\//);
  await page(this).reload({ waitUntil: 'networkidle' });
});
When('пользователь выходит', async function (this: CustomWorld) {
  await signOut(page(this));
});
Then('снова открыта форма входа', async function (this: CustomWorld) {
  await expect(page(this)).toHaveURL(/\/login(?:\?.*)?$/);
  await expect(page(this).getByRole('heading', { name: 'Вход в кабинет' })).toBeVisible();
});
When('пользователь открывает адрес {string}', async function (this: CustomWorld, path: string) {
  await page(this).goto(this.baseUrl + path, { waitUntil: 'networkidle' });
});
When('следующая загрузка кабинета завершается ошибкой', async function (this: CustomWorld) {
  await expect(page(this).getByRole('heading', { name: 'Кабинет куратора' })).toBeVisible();
  await openUserMenu(page(this));
  await page(this).getByRole('link', { name: 'Профиль', exact: true }).click();
  await expect(page(this).getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  await page(this).evaluate(() => {
    (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void } }).__MOREFOTO_MOCKS__?.failNextRequest();
  });
  await page(this).getByRole('link', { name: 'Обзор', exact: true }).click();
});
When('пользователь повторяет загрузку', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Повторить загрузку' }).click();
});
When('срок сессии истёк', async function (this: CustomWorld) {
  await page(this).waitForURL(/\/cabinet\//);
  await page(this).evaluate(() => localStorage.setItem('morefoto:demo:auth:expires_at', '2000-01-01T00:00:00Z'));
  await page(this).reload({ waitUntil: 'networkidle' });
});
