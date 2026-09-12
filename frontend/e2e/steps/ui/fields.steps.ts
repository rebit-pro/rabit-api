import { Given, Then, When } from '@cucumber/cucumber';
import { expect, type Page, type Locator } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
async function height(locator: Locator, value: number) {
  await expect.poll(async () => Math.round((await locator.boundingBox())?.height ?? 0)).toBe(value);
}
async function state(p: Page, name: string) {
  await p.locator('.ui-state .v-field').click();
  await p.getByRole('option', { name, exact: true }).click();
}
Given('открыты образцы интерфейса', async function (this: CustomWorld) {
  await page(this).goto(this.baseUrl + '/demo/ui', { waitUntil: 'networkidle' });
  await expect(page(this).getByRole('heading', { name: 'Поля и кнопки', exact: true })).toBeVisible();
});
Given('пользователь открывает образцы из демонстрационных доступов', async function (this: CustomWorld) {
  const p = page(this);
  await p.goto(this.baseUrl + '/login', { waitUntil: 'networkidle' });
  await p.getByText('Демонстрационные доступы', { exact: true }).click();
  await p.getByRole('link', { name: 'Образцы полей и кнопок', exact: true }).click();
  await expect(p).toHaveURL(/\/demo\/ui$/);
});
When('включает компактную плотность образцов', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Компактная', exact: true }).click();
});
Then('обычные поля образцов имеют высоту {int}', async function (this: CustomWorld, size: number) {
  for (const id of ['ui-name', 'ui-product', 'ui-autocomplete']) await height(page(this).getByTestId(id).locator('.v-field'), size);
  for (const id of ['ui-name', 'ui-product', 'ui-autocomplete', 'ui-comment']) {
    const corners = await page(this)
      .getByTestId(id)
      .locator('.v-field__outline')
      .evaluate((el) => {
        const start = getComputedStyle(el.querySelector('.v-field__outline__start')!);
        const end = getComputedStyle(el.querySelector('.v-field__outline__end')!);
        return [start.borderTopLeftRadius, start.borderBottomLeftRadius, end.borderTopRightRadius, end.borderBottomRightRadius];
      });
    expect(corners, 'Видимая рамка ' + id).toEqual(['4px', '4px', '4px', '4px']);
  }
});
Then('заполнение очистка и состояния полей работают', async function (this: CustomWorld) {
  const p = page(this);
  await state(p, 'Заполненные');
  const input = p.getByTestId('ui-name').locator('input');
  await expect(input).toHaveValue('Тестовый покупатель');
  const clear = p.getByRole('button', { name: 'Очистить имя покупателя', exact: true });
  await input.focus();
  await p.keyboard.press('Tab');
  await expect(clear).toBeFocused();
  await p.keyboard.press('Enter');
  await expect(input).toHaveValue('');
  await expect(input).toBeFocused();
  await input.fill('Имя после очистки');
  await state(p, 'Ошибка');
  await expect(p.getByTestId('ui-name')).toContainText('Проверьте значение');
  await expect(input).toHaveAttribute('aria-invalid', 'true');
  await state(p, 'Отключены');
  await expect(input).toBeDisabled();
  await state(p, 'Только чтение');
  await expect(input).toHaveAttribute('readonly', '');
  await expect(input).toHaveValue('Тестовый покупатель');
  await state(p, 'Загрузка');
  await expect(input).toBeDisabled();
  await expect(p.getByTestId('ui-name').getByRole('progressbar')).toBeVisible();
  await state(p, 'Пустые');
  await expect(input).toBeEnabled();
  await expect(input).toHaveValue('');
});
Then('поиск и восстановление списков работают с клавиатуры', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('button', { name: 'Пустой список', exact: true }).click();
  const select = p.getByTestId('ui-product').locator('.v-field');
  await select.click();
  await expect(p.getByText('Нет доступных вариантов', { exact: true })).toBeVisible();
  await p.keyboard.press('Escape');
  await p.getByRole('button', { name: 'Ошибка списка', exact: true }).click();
  await expect(p.getByTestId('ui-product')).toContainText('Не удалось загрузить');
  await p.getByRole('button', { name: 'Повторить загрузку', exact: true }).click();
  await expect(p.getByTestId('ui-product')).not.toContainText('Не удалось загрузить');
  await p.getByTestId('ui-product').locator('input').focus();
  await p.keyboard.press('ArrowDown');
  await expect(p.getByRole('option', { name: 'Холст 30 × 45', exact: true })).toBeVisible();
  await expect(p.locator('.mf-ui-overlay').filter({ has: p.getByRole('option', { name: 'Холст 30 × 45', exact: true }) })).toBeVisible();
  await p.getByRole('option', { name: 'Холст 30 × 45', exact: true }).click();
  await expect(p.getByTestId('ui-product')).toContainText('Холст 30 × 45');
  const autocomplete = p.getByTestId('ui-autocomplete').getByRole('combobox', { name: 'Учреждение', exact: true });
  await autocomplete.fill('Горизонт');
  await p.getByRole('option', { name: 'Школа «Горизонт»', exact: true }).click();
  await expect(autocomplete).toHaveValue('Школа «Горизонт»');
});
Then('действия образца выполняются один раз и возвращают фокус', async function (this: CustomWorld) {
  const p = page(this);
  const save = p.getByTestId('ui-save');
  await save.evaluate((el) => {
    (el as HTMLElement).click();
    (el as HTMLElement).click();
  });
  await expect(save).toBeDisabled();
  await expect(p.getByTestId('ui-button-notice')).toHaveText('Пример сохранён. Операций: 1.');
  const remove = p.getByRole('button', { name: 'Удалить пример', exact: true });
  await remove.click();
  await expect(p.getByRole('dialog')).toBeVisible();
  await p.keyboard.press('Escape');
  await expect(p.getByRole('dialog')).not.toBeVisible();
  await expect(remove).toBeFocused();
  await remove.click();
  await p.getByRole('button', { name: 'Подтвердить удаление', exact: true }).click();
  await expect(p.getByTestId('ui-button-notice')).toHaveText('Демонстрационный элемент удалён.');
  await expect(remove).toBeFocused();
});
Then('образцы на ширине {int} не обрезают поля и действия', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 1000 });
  await state(p, 'Заполненные');
  await p.getByRole('checkbox', { name: 'Длинные подписи', exact: true }).check();
  await p.getByRole('button', { name: 'Компактная', exact: true }).click();
  await height(p.getByTestId('ui-name').locator('.v-field'), width < 768 ? 44 : 40);
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const button = p.getByTestId('ui-save');
  await button.click();
  await expect(p.getByTestId('ui-button-notice')).toContainText('Операций: 1.');
  expect(await button.evaluate((el) => el.scrollWidth <= el.clientWidth + 2)).toBe(true);
  await expect
    .poll(() =>
      button.evaluate((el) => {
        const content = el.querySelector('.v-btn__content') as HTMLElement;
        const style = getComputedStyle(el);
        return el.clientHeight >= content.scrollHeight + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
      })
    )
    .toBe(true);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '32px';
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await p.getByRole('button', { name: 'Сбросить образцы', exact: true }).click();
  await expect(p.getByTestId('ui-name').locator('input')).toHaveValue('');
});
Then('панель кадра сохраняет размеры и работу клавиатуры', async function (this: CustomWorld) {
  const p = page(this);
  for (const viewport of [
    { width: 1440, height: 1000 },
    { width: 1440, height: 600 },
    { width: 390, height: 844 }
  ]) {
    await p.setViewportSize(viewport);
    await height(p.locator('.product-selector .v-select .v-field'), 48);
    await height(p.locator('.product-selector .v-text-field:not(.v-select) .v-field'), 48);
    await height(p.getByTestId('add-to-cart'), 48);
    await height(p.getByRole('button', { name: 'Следующий кадр', exact: true }), 44);
  }
  const input = p.locator('.product-quantity input');
  await input.focus();
  await p.keyboard.press('ArrowRight');
  await expect(p.getByRole('heading', { name: 'Кадр A001-01', exact: true })).toBeVisible();
  const select = p.locator('.product-selector .v-select .v-field');
  await select.click();
  await p.keyboard.press('Escape');
  await expect(p.getByRole('dialog')).toBeVisible();
  await p.getByRole('button', { name: 'Следующий кадр', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Кадр A001-02', exact: true })).toBeVisible();
  await p.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
  await expect(p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true })).toBeFocused();
});
Then('работа с образцами не изменяет данные покупки', async function (this: CustomWorld) {
  const p = page(this);
  const snapshot = () =>
    p.evaluate(() => Object.fromEntries(Object.entries(localStorage).filter(([key]) => key.startsWith('morefoto:demo:'))));
  const before = await snapshot();
  await p.goto(this.baseUrl + '/demo/ui', { waitUntil: 'networkidle' });
  await state(p, 'Заполненные');
  await p.getByTestId('ui-save').click();
  await expect(p.getByTestId('ui-button-notice')).toContainText('Операций: 1.');
  await p.getByRole('button', { name: 'Сбросить образцы', exact: true }).click();
  expect(await snapshot()).toEqual(before);
});
