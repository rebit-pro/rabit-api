import { Given, When, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import type { OrganizationState } from '../../../src/modules/morefoto/organization/types.js';
import { signOut } from '../../support/shell.js';

function page(world: CustomWorld) {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function login(p: Page, base: string, email = 'organizer@morefoto.test') {
  await p.goto(base + '/login', { waitUntil: 'networkidle' });
  if (!p.url().includes('/login')) {
    await signOut(p);
  }
  await p.getByLabel('Email', { exact: true }).fill(email);
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/\/cabinet\//);
  await expect(p.locator('h1')).toBeVisible();
}
async function list(p: Page, base: string) {
  await p.goto(base + '/cabinet/institutions', { waitUntil: 'networkidle' });
  await expect(p.getByRole('button', { name: 'Новое учреждение', exact: true })).toBeEnabled();
}
async function select(p: Page, id: string, title: string) {
  await p.getByTestId(id).locator('.v-field').click();
  await p.getByRole('option', { name: title, exact: true }).click();
}
async function save(p: Page) {
  await p.getByRole('dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
  await expect(p.getByRole('status').filter({ hasText: /сохранен[ао]/ })).toBeVisible();
}
async function institution(p: Page, name = 'Детский сад «Маяк»') {
  await p.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await p.getByLabel('Название учреждения', { exact: true }).fill(name);
  await p.getByLabel('Адрес учреждения', { exact: true }).fill('Воронеж, улица Маячная, 10');
  await select(p, 'org-curator', 'Рита · curator@morefoto.test');
  await select(p, 'org-head', 'Ирина · head@morefoto.test');
  await save(p);
}
async function openInstitution(p: Page, name: string) {
  await p
    .getByRole('button', { name: 'Открыть ' + name, exact: true })
    .filter({ visible: true })
    .click();
  await expect(p.getByRole('heading', { name, exact: true, level: 1 })).toBeVisible();
}
async function shoot(p: Page, name = 'Осень — Маяк', date = '2026-10-01') {
  await p.getByRole('button', { name: 'Новая съёмка', exact: true }).click();
  await p.getByLabel('Название съёмки', { exact: true }).fill(name);
  await p.getByLabel('Дата съёмки', { exact: true }).fill(date);
  await save(p);
}
async function openShoot(p: Page, name: string) {
  await p
    .getByRole('button', { name: 'Открыть ' + name, exact: true })
    .filter({ visible: true })
    .click();
  await expect(p.getByRole('heading', { name, exact: true, level: 1 })).toBeVisible();
}
async function group(p: Page, name = 'Морские звёзды', staff = false) {
  await p.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await p.getByLabel('Название группы', { exact: true }).fill(name);
  if (staff) await select(p, 'org-kind', 'Сотрудники');
  await select(p, 'org-teacher', 'Мария · teacher@morefoto.test');
  await save(p);
}
async function state(p: Page): Promise<OrganizationState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:organization:v1') ?? 'null'));
}
Given('организатор открыл учреждения R07', async function (this: CustomWorld) {
  await login(page(this), this.baseUrl);
  await list(page(this), this.baseUrl);
});
Given('сотрудник R07 входит как {string}', async function (this: CustomWorld, email: string) {
  await login(page(this), this.baseUrl, email);
});
When('создаёт учреждение R07 {string}', async function (this: CustomWorld, name: string) {
  await institution(page(this), name);
});
When('открывает учреждение R07 {string}', async function (this: CustomWorld, name: string) {
  await openInstitution(page(this), name);
});
When('редактирует адрес учреждения R07', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
  await p.getByLabel('Адрес учреждения', { exact: true }).fill('Воронеж, улица Маячная, 25');
  await save(p);
});
Then('учреждение R07 сохранено после обновления', async function (this: CustomWorld) {
  const p = page(this);
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.locator('body')).toContainText('Воронеж, улица Маячная, 25');
  const record = (await state(p)).institutions.find((item) => item.name === 'Детский сад «Маяк»');
  expect(record?.curatorId).toBe(102);
  expect(record?.headId).toBe(103);
  expect(record?.revision).toBe(2);
});
Then('форма учреждения R07 проверяет обязательные поля и дубликат', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByLabel('Название учреждения', { exact: true })).toBeFocused();
  await expect(p.getByText('Введите адрес от 5 до 240 символов.')).toBeVisible();
  await p.getByLabel('Название учреждения', { exact: true }).fill(' Детский сад «Солнечный» ');
  await p.getByLabel('Адрес учреждения', { exact: true }).fill('Воронеж, Учебная улица, 12');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('Учреждение с таким названием и адресом уже существует.')).toBeVisible();
});
Then('две съёмки R07 сохраняют отдельные группы и ссылки', async function (this: CustomWorld) {
  const p = page(this);
  await openInstitution(p, 'Детский сад «Солнечный»');
  await p.getByRole('button', { name: 'Новая съёмка', exact: true }).click();
  await p.getByLabel('Название съёмки', { exact: true }).fill('Повторная съёмка');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByLabel('Дата съёмки', { exact: true })).toBeFocused();
  await p.getByLabel('Дата съёмки', { exact: true }).fill('2026-10-01');
  await save(p);
  const institutionUrl = p.url();
  await openShoot(p, 'Повторная съёмка');
  await group(p, 'Звёздочки');
  const first = await state(p);
  const firstGroup = first.groups.find((item) => item.name === 'Звёздочки' && item.id !== 'sun-stars')!;
  await p.goto(institutionUrl, { waitUntil: 'networkidle' });
  await shoot(p, 'Повторная съёмка', '2027-10-01');
  const second = await state(p);
  const secondShoot = second.shoots.find((item) => item.date === '2027-10-01')!;
  await p.goto(institutionUrl + '/shoots/' + secondShoot.id, { waitUntil: 'networkidle' });
  await group(p, 'Звёздочки');
  const final = await state(p);
  expect(final.groups.filter((item) => item.name === 'Звёздочки')).toHaveLength(3);
  expect(final.groups.find((item) => item.id === firstGroup.id)).toEqual(firstGroup);
  expect(final.groups.find((item) => item.id === 'sun-stars')?.galleryToken).toBe('158-group-7bc93615c4e94fd18a207d560b3e1f82');
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.getByRole('button', { name: 'Редактировать Звёздочки', exact: true }).filter({ visible: true })).toBeVisible();
});
When('готовит новую группу R07', async function (this: CustomWorld) {
  const p = page(this);
  await institution(p);
  await openInstitution(p, 'Детский сад «Маяк»');
  await shoot(p);
  await openShoot(p, 'Осень — Маяк');
  await group(p);
});
Then('новая галерея R07 показывает контекст без запуска срока', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('link', { name: 'Галерея: Морские звёзды', exact: true }).filter({ visible: true }).click();
  await expect(p).toHaveURL(/\/g\//);
  await expect(p.getByRole('heading', { name: 'Фотографии ещё готовятся', exact: true })).toBeVisible();
  await expect(p.locator('body')).toContainText('Морские звёзды');
  await expect(p.locator('body')).toContainText('Осень — Маяк');
  await expect(p.locator('body')).toContainText('Детский сад «Маяк»');
  await expect(p.locator('body')).toContainText('готов');
  const g = (await state(p)).groups.find((item) => item.name === 'Морские звёзды');
  expect(g?.state).toBe('preparing');
  expect(g?.closesAt).toBeNull();
  await expect(p.locator('[data-testid="gallery-photo"]')).toHaveCount(0);
});
Then('папки сотрудников R07 проверяются в контексте съёмки', async function (this: CustomWorld) {
  const p = page(this);
  await openInstitution(p, 'Детский сад «Солнечный»');
  await openShoot(p, 'Осенняя съёмка · 2026');
  await p.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await p.getByLabel('Название группы', { exact: true }).fill('Сотрудники повторно');
  await select(p, 'org-kind', 'Сотрудники');
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByText('В этой съёмке уже есть папка сотрудников.')).toBeVisible();
  await p.getByRole('button', { name: 'Закрыть форму', exact: true }).click();
  await p.getByRole('button', { name: 'Редактировать Сотрудники', exact: true }).filter({ visible: true }).click();
  await expect(p.getByTestId('org-kind').locator('input')).toHaveAttribute('readonly', '');
});
Then('ошибка сохранения R07 сохраняет ввод и разрешает один повтор', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await p.getByLabel('Название учреждения', { exact: true }).fill('Сад с повтором');
  await p.getByLabel('Адрес учреждения', { exact: true }).fill('Воронеж, улица Повторная, 12');
  await p.evaluate(() => (window as Window & { __MOREFOTO_MOCKS__?: { failNextRequest(): void } }).__MOREFOTO_MOCKS__?.failNextRequest());
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByRole('dialog').getByRole('alert')).toContainText('Не удалось загрузить');
  await p.reload({ waitUntil: 'networkidle' });
  await p.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await expect(p.getByLabel('Название учреждения', { exact: true })).toHaveValue('Сад с повтором');
  await expect(p.getByText('Восстановлен сохранённый черновик.')).toBeVisible();
  await save(p);
  expect((await state(p)).institutions.filter((item) => item.name === 'Сад с повтором')).toHaveLength(1);
});
Then('две вкладки R07 не теряют изменения учреждения', async function (this: CustomWorld) {
  const p = page(this);
  await openInstitution(p, 'Детский сад «Солнечный»');
  const second = await this.context!.newPage();
  await second.goto(p.url(), { waitUntil: 'networkidle' });
  try {
    await p.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
    await second.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
    await p.getByLabel('Адрес учреждения', { exact: true }).fill('Адрес из первой вкладки, 100');
    await second.getByLabel('Адрес учреждения', { exact: true }).fill('Адрес из второй вкладки, 200');
    await save(second);
    await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
    await expect(p.getByRole('dialog').getByRole('alert')).toContainText('Запись изменена в другой вкладке');
    expect((await state(p)).institutions.find((item) => item.id === 'sun')?.address).toBe('Адрес из второй вкладки, 200');
    await p.getByRole('button', { name: 'Закрыть форму', exact: true }).click();
    await p.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
    await expect(p.getByLabel('Адрес учреждения', { exact: true })).toHaveValue('Адрес из первой вкладки, 100');
    await p.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await expect(p.getByLabel('Адрес учреждения', { exact: true })).toHaveValue('Адрес из второй вкладки, 200');
  } finally {
    await second.close();
  }
});
Then('назначения R07 действуют для куратора руководителя и ответственного', async function (this: CustomWorld) {
  const p = page(this);
  const s = await state(p);
  const id = s.institutions.find((item) => item.name === 'Детский сад «Маяк»')!.id;
  for (const email of ['curator@morefoto.test', 'head@morefoto.test', 'teacher@morefoto.test']) {
    await login(p, this.baseUrl, email);
    await expect(p.locator('body')).toContainText('Детский сад «Маяк»');
    if (!email.startsWith('teacher')) await p.goto(this.baseUrl + '/cabinet/institutions/' + id, { waitUntil: 'networkidle' });
    await expect(p.locator('body')).toContainText('Морские звёзды');
    await expect(p.locator('body')).toContainText('Осень — Маяк');
    await expect(p.getByRole('button', { name: 'Новая группа', exact: true })).toHaveCount(0);
    await expect(p.locator('body')).not.toContainText('buyer@example');
  }
});
Then('снятие назначения R07 обновляет разрешённую область', async function (this: CustomWorld) {
  const p = page(this);
  await openInstitution(p, 'Детский сад «Солнечный»');
  await p.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
  await select(p, 'org-head', 'Не назначен');
  await save(p);
  await login(p, this.baseUrl, 'head@morefoto.test');
  await p.goto(this.baseUrl + '/cabinet/institutions/sun', { waitUntil: 'networkidle' });
  await expect(p.getByRole('heading', { name: 'Учреждение недоступно', exact: true })).toBeVisible();
  await expect(p.locator('body')).not.toContainText('Звёздочки');
});
Then('служебные формы R07 закрыты прямой ссылкой', async function (this: CustomWorld) {
  const p = page(this);
  for (const url of ['/cabinet/institutions', '/cabinet/institutions/sun/shoots/sun-summer-2026']) {
    await p.goto(this.baseUrl + url, { waitUntil: 'networkidle' });
    await expect(p).toHaveURL(/\/access/);
    await expect(p.getByRole('button', { name: 'Новая съёмка', exact: true })).toHaveCount(0);
  }
});
Then('неверный контекст R07 недоступен', async function (this: CustomWorld) {
  const p = page(this);
  for (const path of ['/cabinet/institutions/sun/shoots/missing', '/cabinet/institutions/rainbow/shoots/sun-summer-2026']) {
    await p.goto(this.baseUrl + path, { waitUntil: 'networkidle' });
    await expect(p.getByRole('heading', { name: 'Запись недоступна', exact: true })).toBeVisible();
    await expect(p.getByRole('button', { name: 'Новая группа', exact: true })).toHaveCount(0);
  }
});
Then('поиск R07 показывает пустое состояние и очищается', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByLabel('Поиск: учреждения', { exact: true }).fill('Несуществующий сад');
  await expect(p.getByText('Ничего не найдено', { exact: true })).toBeVisible();
  await p.getByRole('button', { name: 'Очистить поиск', exact: true }).focus();
  await p.keyboard.press('Enter');
  await expect(p.getByLabel('Поиск: учреждения', { exact: true })).toBeFocused();
  await expect(p.getByLabel('Поиск: учреждения', { exact: true })).toHaveValue('');
  await expect(p.getByRole('button', { name: 'Открыть Детский сад «Солнечный»', exact: true }).filter({ visible: true })).toBeVisible();
});
Then('истёкшая во время сохранения сессия R07 не создаёт учреждение', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await p.getByLabel('Название учреждения', { exact: true }).fill('Не сохранять');
  await p.getByLabel('Адрес учреждения', { exact: true }).fill('Не сохранять, улица 12');
  await p.evaluate(() => (window as Window & { __MOREFOTO_MOCKS__?: { setDelay(ms: number): void } }).__MOREFOTO_MOCKS__?.setDelay(1500));
  await p.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await p.evaluate(() => localStorage.setItem('morefoto:demo:sessions:v1', '{}'));
  await expect(p.getByRole('dialog').getByRole('alert')).toContainText('Сессия истекла');
  expect(await state(p)).toBeNull();
});
Then('правки организатора R07 сохраняют старый заказ и файлы', async function (this: CustomWorld) {
  const p = page(this);
  const orderUrl = p.url();
  const before = await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'));
  await login(p, this.baseUrl);
  await list(p, this.baseUrl);
  await openInstitution(p, 'Детский сад «Солнечный»');
  await openShoot(p, 'Лето в кадре');
  await p.getByRole('button', { name: 'Редактировать съёмку', exact: true }).click();
  await p.getByLabel('Название съёмки', { exact: true }).fill('Лето — уточнённое название');
  await p.getByLabel('Дата съёмки', { exact: true }).fill('2026-09-01');
  await save(p);
  await p.getByRole('button', { name: 'Редактировать Звёздочки', exact: true }).filter({ visible: true }).click();
  await p.getByLabel('Название группы', { exact: true }).fill('Звёздочки — обновлено');
  await save(p);
  expect(await p.evaluate(() => localStorage.getItem('morefoto:demo:orders:v1'))).toBe(before);
  await p.getByRole('link', { name: 'Галерея: Звёздочки — обновлено', exact: true }).filter({ visible: true }).click();
  await expect(p.locator('body')).toContainText('Лето — уточнённое название');
  await expect(p.locator('body')).toContainText('Звёздочки — обновлено');
  await p.goto(orderUrl, { waitUntil: 'networkidle' });
  await expect(p.getByTestId('order-payment-status')).toHaveText('Оплачено · демонстрация');
  const pending = p.waitForEvent('download');
  await p.getByTestId('download-archive').click();
  expect(await (await pending).failure()).toBeNull();
});
Then('экраны и формы R07 доступны на ширине {int}', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 900 });
  await mkdir('reports/e2e/r07', { recursive: true });
  await p.screenshot({ path: 'reports/e2e/r07/institutions-' + width + '.png', fullPage: true });
  await openInstitution(p, 'Детский сад «Солнечный»');
  await openShoot(p, 'Лето в кадре');
  await p.screenshot({ path: 'reports/e2e/r07/shoot-' + width + '.png', fullPage: true });
  await p.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await expect(p.getByLabel('Название группы', { exact: true })).toBeFocused();
  await p.getByLabel('Название группы', { exact: true }).fill('Длинное название группы для проверки переноса текста на телефоне');
  if (width === 390) await p.addStyleTag({ content: 'html { font-size: 200% !important; }' });
  await p.screenshot({ path: 'reports/e2e/r07/form-' + width + '.png', fullPage: true });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBeTruthy();
  expect(await p.locator('.mf-skip').evaluate((el) => el.getBoundingClientRect().bottom)).toBeLessThanOrEqual(0);
  await p.getByRole('dialog').getByRole('button', { name: 'Сохранить', exact: true }).scrollIntoViewIfNeeded();
  await expect(p.getByRole('dialog').getByRole('button', { name: 'Сохранить', exact: true })).toBeVisible();
  const dialog = await p.getByRole('dialog').boundingBox();
  expect(dialog!.x).toBeGreaterThanOrEqual(0);
  expect(dialog!.x + dialog!.width).toBeLessThanOrEqual(width + 1);
  await p.getByRole('button', { name: 'Закрыть форму', exact: true }).click();
  await expect(p.getByRole('button', { name: 'Новая группа', exact: true })).toBeFocused();
});
