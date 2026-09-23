import { test, expect, type Page } from '@playwright/test';
import { login, logout, password } from './helpers.js';

// B4: invitation, first sign-in, password recovery and change. Tokens are test-only fixtures from tools/e2e/prepare.php;
// the MySQL verifier (tools/e2e/verify-access.php) checks that only their SHA-256 is stored.
const inviteToken = 'b4InviteFixtureToken' + 'A'.repeat(23);
const resetToken = 'b4ResetFixtureToken' + 'B'.repeat(24);
const invitedPassword = 'B4-invited-test-only-password';
const resetPassword = 'B4-reset-test-only-password';

const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
});

async function signIn(page: Page, email: string, secret: string) {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
  await page.getByLabel('Пароль', { exact: true }).fill(secret);
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
}

async function setNewPassword(page: Page, secret: string, submit: string) {
  // The reset card itself is titled «Новый пароль», so the field is found by its role.
  await page.getByRole('textbox', { name: 'Новый пароль', exact: true }).fill(secret);
  await page.getByRole('textbox', { name: 'Повторите пароль', exact: true }).fill(secret);
  await page.getByRole('button', { name: submit, exact: true }).click();
}

test('B4: приглашение по ссылке — пароль, вход и одноразовая ссылка', async ({ page }) => {
  await page.goto('/access/invite/' + inviteToken);
  const preview = page.getByTestId('invitation-preview');
  await expect(preview).toContainText('b4-invited');
  await expect(preview).toContainText('b***@example.invalid');
  await expect(preview).not.toContainText('b4-invited@example.invalid');

  await setNewPassword(page, 'short', 'Задать пароль и войти');
  await expect(page.getByText('Не меньше 10 символов', { exact: true })).toBeVisible();

  const weak = page.waitForResponse((r) => r.url().endsWith('/accept'));
  await setNewPassword(page, 'b4-invited@example.invalid', 'Задать пароль и войти');
  expect((await weak).status()).toBe(422);
  await expect(page.getByTestId('access-error')).toContainText('Пароль слишком простой');

  const accept = page.waitForResponse((r) => r.url().endsWith('/accept'));
  await setNewPassword(page, invitedPassword, 'Задать пароль и войти');
  expect((await accept).status()).toBe(200);
  await expect(page).toHaveURL(/\/cabinet\/welcome$/);
  await expect(page.getByRole('heading', { name: 'Добро пожаловать, b4-invited!', exact: true })).toBeVisible();
  const next = page.getByRole('region', { name: 'Что дальше', exact: true });
  await expect(next.getByRole('link', { name: 'Ссылки и сроки', exact: true })).toBeVisible();
  await expect(next.getByRole('link', { name: 'Сотрудники', exact: true })).toHaveCount(0);

  await logout(page);
  await page.goto('/access/invite/' + inviteToken);
  await expect(page.getByTestId('access-link-problem')).toContainText('Ссылка уже использована');
  await signIn(page, 'b4-invited@example.invalid', invitedPassword);
  await expect(page).toHaveURL(/\/cabinet\/overview$/);
});

test('B4: ожидающий регистрации сотрудник получает общий ответ и подсказки', async ({ page }) => {
  const rejected = page.waitForResponse((r) => r.url().endsWith('/auth/login'));
  await signIn(page, 'b4-pending@example.invalid', password);
  expect((await rejected).status()).toBe(401);
  await expect(page.getByTestId('login-api-error')).toHaveText('Неверный email или пароль.');
  await expect(page.getByRole('link', { name: 'Забыли пароль?', exact: true })).toBeVisible();
  await expect(page.getByText(/Получили приглашение\? Откройте ссылку из письма/)).toBeVisible();
});

test('B4: восстановление доступа отвечает одинаково для любого адреса', async ({ page }) => {
  for (const email of ['nobody-b4@example.invalid', 'b4-pending@example.invalid']) {
    await page.goto('/access/recover');
    const request = page.waitForResponse((r) => r.url().endsWith('/api/v1/auth/password-resets'));
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
    await page.getByRole('button', { name: 'Отправить ссылку', exact: true }).click();
    expect((await request).status()).toBe(202);
    await expect(page.getByTestId('recover-sent')).toContainText('Если адрес зарегистрирован, мы отправили письмо');
  }
});

test('B4: сброс пароля по ссылке завершает прежнюю сессию', async ({ page, browser }) => {
  const oldContext = await browser.newContext();
  const old = await oldContext.newPage();
  await signIn(old, 'b4-reset@example.invalid', password);
  await expect(old).toHaveURL(/\/cabinet\/overview$/);

  await page.goto('/access/reset/' + resetToken);
  const confirm = page.waitForResponse((r) => r.url().endsWith('/confirm'));
  await setNewPassword(page, resetPassword, 'Сохранить пароль и войти');
  expect((await confirm).status()).toBe(200);
  await expect(page).toHaveURL(/\/cabinet\/overview$/);

  await old.reload();
  await expect(old).toHaveURL(/\/login\?reason=revoked$/);
  await expect(old.getByTestId('login-reason')).toContainText('Сессия завершена');
  await oldContext.close();

  await page.goto('/access/reset/' + resetToken);
  await setNewPassword(page, resetPassword + '-2', 'Сохранить пароль и войти');
  await expect(page.getByTestId('access-link-problem')).toContainText('Ссылка уже использована');
});

test('B4: смена пароля в профиле проверяет текущий пароль', async ({ page }) => {
  await signIn(page, 'b4-reset@example.invalid', resetPassword);
  await expect(page).toHaveURL(/\/cabinet\/overview$/);
  await page.goto('/cabinet/profile');
  const security = page.getByTestId('profile-security');
  await security.getByLabel('Текущий пароль', { exact: true }).fill('wrong-password-b4');
  await security.getByLabel('Новый пароль', { exact: true }).fill(resetPassword + '-changed');
  await security.getByLabel('Повторите пароль', { exact: true }).fill(resetPassword + '-changed');
  await security.getByRole('button', { name: 'Сменить пароль', exact: true }).click();
  await expect(security.getByTestId('security-error')).toContainText('Текущий пароль указан неверно');

  await security.getByLabel('Текущий пароль', { exact: true }).fill(resetPassword);
  await security.getByLabel('Новый пароль', { exact: true }).fill(resetPassword + '-changed');
  await security.getByLabel('Повторите пароль', { exact: true }).fill(resetPassword + '-changed');
  await security.getByRole('button', { name: 'Сменить пароль', exact: true }).click();
  await expect(security.getByTestId('security-done')).toContainText('Пароль изменён');
  await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
});

test('B4: организатор находит ожидающих регистрации и повторяет приглашение', async ({ page }) => {
  await login(page);
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Сотрудники', exact: true }).click();
  await page.getByRole('combobox', { name: 'Статус', exact: true }).press('Enter');
  await page.getByRole('option', { name: 'Ожидает регистрации', exact: true }).click();
  const filtered = page.waitForResponse((r) => r.url().includes('/api/v1/users?') && r.url().includes('accountStatus=pending'));
  await page.getByRole('button', { name: 'Найти', exact: true }).click();
  expect((await filtered).status()).toBe(200);
  await expect(page.getByRole('button', { name: 'Редактировать сотрудника teacher', exact: true })).toHaveCount(0);
  const row = page.getByRole('button', { name: 'Редактировать сотрудника B2 Новый учитель' });
  await expect(row).toContainText('Ожидает регистрации');
  await expect(row).toContainText('приглашение отправлено');
  await row.click();
  const panel = page.getByTestId('staff-invitation');
  await expect(panel).toContainText('Приглашение отправлено');
  const resend = page.waitForResponse((r) => r.url().endsWith('/invitations') && r.request().method() === 'POST');
  await panel.getByRole('button', { name: 'Отправить приглашение повторно', exact: true }).click();
  expect([200, 429]).toContain((await resend).status());
  await expect(panel).toContainText(/Приглашение отправлено повторно|Повторить можно через минуту/);
});
