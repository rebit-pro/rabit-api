import { Given, When, Then } from '@cucumber/cucumber';
import { expect } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
const tokens: Record<string, string> = {
  group: '158-group-7bc93615c4e94fd18a207d560b3e1f82',
  school: '158-school-a419e576df224fa880c3bd92617e450b',
  staff: '158-staff-f4bc8d62e1934a75b0172c6d980e5a43',
  invalid: 'no-such-gallery'
};
function page(world: CustomWorld) {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function open(world: CustomWorld, key: string, query = '') {
  await page(world).goto(world.baseUrl + '/g/' + tokens[key] + query, { waitUntil: 'networkidle' });
  await expect(page(world).getByRole('status', { name: 'Загрузка галереи' })).toHaveCount(0);
}
Given('открыта тестовая галерея {string}', async function (this: CustomWorld, key: string) {
  await open(this, key);
});
Given('галерея открывается в состоянии {string}', async function (this: CustomWorld, state: string) {
  await page(this).goto(this.baseUrl + '/login', { waitUntil: 'networkidle' });
  await page(this).getByText('Демонстрационные доступы', { exact: true }).click();
  await page(this).evaluate((value) => {
    const mocks = (window as Window & { __MOREFOTO_MOCKS__?: { setGalleryScenario(value: string): void; failNextRequest(): void } })
      .__MOREFOTO_MOCKS__;
    if (value === 'error') mocks?.failNextRequest();
    else mocks?.setGalleryScenario(value);
  }, state);
  await page(this).getByRole('link', { name: 'Галерея группы', exact: true }).click();
});
Given('ширина экрана галереи {int}', async function (this: CustomWorld, width: number) {
  await page(this).setViewportSize({ width, height: 900 });
});
Given('школьная галерея открыта с чужим кадром', async function (this: CustomWorld) {
  await open(this, 'school', '?child=A001&photo=sun-stars-A001-1');
});
Given('обычная галерея открыта с параметром льготы', async function (this: CustomWorld) {
  await open(this, 'group', '?audience=staff&discount=50');
});
Given('первая миниатюра не загружается', async function (this: CustomWorld) {
  let failed = false;
  await page(this).route('**/demo/gallery-v1/sun-stars/*-thumb.webp*', async (route) => {
    if (!failed) {
      failed = true;
      await route.abort();
    } else await route.continue();
  });
  await open(this, 'group');
});
Then('в галерее четыре серии', async function (this: CustomWorld) {
  await expect(page(this).getByRole('button', { name: /^Серия A/ })).toHaveCount(4);
});
Then('видно кадров: {int}', async function (this: CustomWorld, count: number) {
  await expect(page(this).getByTestId('photo-card')).toHaveCount(count);
});
Then('галерея показывает {string}', async function (this: CustomWorld, text: string) {
  await expect(page(this).locator('body')).toContainText(text);
});
When('выбирает серию {string}', async function (this: CustomWorld, code: string) {
  await page(this)
    .getByRole('button', { name: 'Серия ' + code, exact: true })
    .click();
});
When('вводит код {string}', async function (this: CustomWorld, code: string) {
  await page(this).getByLabel('Код ребёнка или кадра', { exact: true }).fill(code);
});
When('сбрасывает поиск', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Показать все серии', exact: true }).click();
});
When('открывает кадр {string}', async function (this: CustomWorld, code: string) {
  await page(this)
    .getByRole('button', { name: 'Открыть кадр ' + code, exact: true })
    .click();
});
Then('крупно показан кадр {string}', async function (this: CustomWorld, code: string) {
  await expect(page(this).getByRole('dialog')).toBeVisible();
  await expect(page(this).getByRole('heading', { name: 'Кадр ' + code, exact: true })).toBeVisible();
  const image = page(this).getByRole('img', { name: 'Крупный кадр ' + code, exact: true });
  await expect(image).toBeVisible();
  await expect.poll(() => image.evaluate((img) => (img as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
});
Then('предыдущий кадр недоступен', async function (this: CustomWorld) {
  await expect(page(this).getByRole('button', { name: 'Предыдущий кадр' })).toBeDisabled();
});
When('нажимает стрелку вправо', async function (this: CustomWorld) {
  await page(this).keyboard.press('ArrowRight');
});
When('закрывает просмотр клавишей Escape', async function (this: CustomWorld) {
  await page(this).keyboard.press('Escape');
});
When('обновляет галерею', async function (this: CustomWorld) {
  await page(this).reload({ waitUntil: 'networkidle' });
});
Then('просмотр закрыт и фильтр {string} сохранён', async function (this: CustomWorld, code: string) {
  await expect(page(this).getByRole('dialog')).toHaveCount(0);
  await expect(page(this).getByLabel('Код ребёнка или кадра', { exact: true })).toHaveValue(code);
  await expect(page(this).getByRole('button', { name: 'Открыть кадр ' + code + '-01', exact: true })).toBeFocused();
});
Then('диалог просмотра отсутствует', async function (this: CustomWorld) {
  await expect(page(this).getByRole('dialog')).toHaveCount(0);
});
Then('изображения принадлежат подборке {string}', async function (this: CustomWorld, group: string) {
  const sources = await page(this)
    .getByTestId('photo-card')
    .locator('img')
    .evaluateAll((images) => images.map((image) => image.getAttribute('src')));
  expect(sources.length).toBeGreaterThan(0);
  expect(sources.every((src) => src?.startsWith('/demo/gallery-v1/' + group + '/'))).toBe(true);
});
Then('крупное изображение принадлежит подборке {string}', async function (this: CustomWorld, group: string) {
  await expect(page(this).getByRole('dialog').locator('img')).toHaveAttribute('src', new RegExp('/' + group + '/'));
});
When('повторяет загрузку миниатюры', async function (this: CustomWorld) {
  await page(this)
    .getByRole('button', { name: /^Повторить загрузку: Кадр/ })
    .click();
});
Then('ошибка миниатюры исчезла', async function (this: CustomWorld) {
  await expect(page(this).getByText('Кадр не загрузился', { exact: true })).toHaveCount(0);
  await expect
    .poll(() =>
      page(this)
        .getByTestId('photo-card')
        .first()
        .locator('img')
        .evaluate((img) => (img as HTMLImageElement).naturalWidth)
    )
    .toBeGreaterThan(0);
});
Then('льготные условия не показаны', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('staff-gallery')).toHaveCount(0);
});
Then('покупка недоступна', async function (this: CustomWorld) {
  await expect(page(this).getByRole('button', { name: /Оплатить|Купить|Добавить в корзину/ })).toHaveCount(0);
});
Then('галерея не имеет горизонтальной прокрутки', async function (this: CustomWorld) {
  expect(await page(this).evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});
