import { readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

type Fixture = Record<'open' | 'preparing' | 'closed' | 'revoked', { token: string; groupId: string; photoId: string }>;
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = '/g/' + fixture.open.token;
const storageKey = 'morefoto:live:question:v1:' + fixture.open.token;

async function openQuestion(page: Page) {
  await page.getByTestId('gallery-question-open').click();
  const dialog = page.getByTestId('gallery-question');
  await expect(dialog.getByRole('heading', { name: 'Вопрос куратору', exact: true })).toBeVisible();
  return dialog;
}

test('a parent asks the curator from the gallery and keeps the conversation in this browser', async ({ page, browser }) => {
  await page.goto(gallery);
  let dialog = await openQuestion(page);
  await expect(dialog).toContainText('Имя и текст передаются кураторам МореФото через мессенджер MAX.');
  await dialog.getByTestId('question-send').click();
  await expect(dialog.getByRole('alert')).toContainText('Укажите, как к вам обращаться.');

  await dialog.getByLabel('Как к вам обращаться', { exact: true }).fill('K3 Мария');
  await dialog.getByLabel('Ваш вопрос', { exact: true }).fill('Когда будут готовы фотографии?');
  const created = page.waitForResponse((r) => r.url().endsWith('/questions') && r.request().method() === 'POST');
  await dialog.getByTestId('question-send').click();
  expect((await created).status()).toBe(201);
  const thread = dialog.getByTestId('question-thread');
  await expect(thread.locator('[data-author="parent"]')).toHaveCount(1);
  await expect(thread).toContainText('Когда будут готовы фотографии?');
  await expect(thread).toContainText('Отправляется куратору');
  await expect(dialog.getByLabel('Как к вам обращаться', { exact: true })).toHaveCount(0);
  const questionKey = await page.evaluate((key) => localStorage.getItem(key), storageKey);
  expect(questionKey).toMatch(/^[a-f0-9]{64}$/);
  writeFileSync('var/k3-questions.json', JSON.stringify({ questionKey }));

  // The same browser gets the history back after a reload; a lost request keeps the text and its key for a safe repeat.
  await page.reload();
  dialog = await openQuestion(page);
  await expect(dialog.getByTestId('question-thread')).toContainText('Когда будут готовы фотографии?');
  const keys: string[] = [];
  await page.route('**/api/v1/public/questions/current/messages', async (route) => {
    keys.push(route.request().headers()['idempotency-key'] ?? '');
    if (keys.length === 1) await route.abort('internetdisconnected');
    else await route.continue();
  });
  await dialog.getByLabel('Сообщение', { exact: true }).fill('Код кадра A001-01, можно ли напечатать крупнее?');
  await dialog.getByTestId('question-send').click();
  await expect(dialog.getByRole('alert')).toContainText('Нет связи с сервером. Текст сохранён');
  await expect(dialog.getByLabel('Сообщение', { exact: true })).toHaveValue('Код кадра A001-01, можно ли напечатать крупнее?');
  const added = page.waitForResponse((r) => r.url().endsWith('/questions/current/messages') && r.request().method() === 'POST');
  await dialog.getByTestId('question-send').click();
  expect((await added).status()).toBe(200);
  expect(keys).toHaveLength(2);
  expect(keys[1]).toBe(keys[0]);
  await expect(dialog.getByTestId('question-thread').locator('[data-author="parent"]')).toHaveCount(2);
  await expect(dialog.getByLabel('Сообщение', { exact: true })).toHaveValue('');
  await page.unroute('**/api/v1/public/questions/current/messages');

  // Another browser with the same gallery link does not see this conversation.
  const stranger = await browser.newPage();
  await stranger.goto(gallery);
  const strangerDialog = await openQuestion(stranger);
  await expect(strangerDialog.getByLabel('Как к вам обращаться', { exact: true })).toBeVisible();
  await expect(strangerDialog).not.toContainText('Когда будут готовы фотографии?');
  await stranger.close();
});

test('a new curator answer is marked on the gallery until the parent opens it', async ({ page }) => {
  await page.goto(gallery);
  await page.evaluate(({ key, value }) => localStorage.setItem(key, value), { key: storageKey, value: 'b'.repeat(64) });
  await page.route('**/api/v1/public/questions/current', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 7,
          number: 7,
          messages: [
            {
              id: 1,
              author: 'parent',
              authorName: 'K3 Мария',
              text: 'Когда будут фото?',
              createdAt: '2026-09-25T09:00:00Z',
              delivery: 'delivered'
            },
            { id: 2, author: 'curator', authorName: 'Рита', text: 'В пятницу.', createdAt: '2026-09-25T09:05:00Z', delivery: null }
          ]
        }
      }
    })
  );
  await page.reload();
  await expect(page.getByTestId('gallery-question-unread')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Вопрос куратору, новый ответ' })).toBeVisible();
  const dialog = await openQuestion(page);
  await expect(dialog.locator('[data-author="curator"]')).toContainText('Рита · куратор');
  await expect(dialog.locator('[data-author="curator"]')).toContainText('В пятницу.');
  await dialog.getByRole('button', { name: 'Закрыть переписку' }).click();
  await expect(page.getByTestId('gallery-question-unread')).toHaveCount(0);
});

test('a teacher writes to the curators from the cabinet; a curator cannot open this conversation', async ({ page }) => {
  await login(page, 'teacher');
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Вопрос куратору', exact: true }).click();
  await expect(page).toHaveURL(/\/cabinet\/questions$/);
  await expect(page.getByRole('heading', { name: 'Вопрос куратору', level: 1 })).toBeVisible();
  await page.getByLabel('Ваш вопрос', { exact: true }).fill('K3 Можно перенести съёмку группы на четверг?');
  const sent = page.waitForResponse((r) => r.url().endsWith('/api/v1/questions/mine/messages'));
  await page.getByTestId('question-send').click();
  expect((await sent).status()).toBe(200);
  await expect(page.getByTestId('question-thread')).toContainText('Отправляется куратору');
  await page.reload();
  await expect(page.getByTestId('question-thread')).toContainText('K3 Можно перенести съёмку группы на четверг?');

  const curator = await page.context().browser()!.newPage();
  await login(curator, 'curator');
  await expect(curator.getByLabel('Основная навигация').getByRole('link', { name: 'Вопрос куратору', exact: true })).toHaveCount(0);
  const denied = await curator.request.get('/api/v1/questions/mine', { headers: { Authorization: 'Bearer ' + (await token(curator)) } });
  expect(denied.status()).toBe(403);
  await curator.close();
});

test('the question screens fit desktop and mobile', async ({ page }) => {
  const { questionKey } = JSON.parse(readFileSync('var/k3-questions.json', 'utf8')) as { questionKey: string };
  await page.goto(gallery);
  await page.evaluate(({ key, value }) => localStorage.setItem(key, value), { key: storageKey, value: questionKey });
  for (const [name, size] of [
    ['desktop', { width: 1440, height: 1000 }],
    ['mobile', { width: 390, height: 844 }]
  ] as const) {
    await page.setViewportSize(size);
    await page.goto(gallery);
    const dialog = await openQuestion(page);
    await expect(dialog.getByTestId('question-thread').locator('[data-author="parent"]')).toHaveCount(2);
    const width = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(width).toBeLessThanOrEqual(size.width);
    await page.screenshot({ path: test.info().outputPath('k3-gallery-question-' + name + '.png') });
  }
});
