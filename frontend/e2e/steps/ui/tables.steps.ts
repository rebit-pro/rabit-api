import { Then } from '@cucumber/cucumber';
import { expect, type Page, type Locator } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
function visibleRows(section: Locator) {
  return section.locator('[data-row-id]:visible');
}
async function fieldSelect(section: Locator, label: string, value: string, p: Page) {
  await section.getByRole('combobox', { name: label, exact: true }).locator('..').click();
  await p.getByRole('option', { name: value, exact: true }).click();
}
Then('поиск статус и даты фильтруют учреждения совместно', async function (this: CustomWorld) {
  const p = page(this);
  await p.setViewportSize({ width: 1440, height: 1000 });
  const section = p.getByTestId('ui-table-institutions');
  await expect(visibleRows(section)).toHaveCount(10);
  await section.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
  await expect(section.getByTestId('ui-table-result')).toContainText('Выбрано: 10');
  await section.getByRole('textbox', { name: 'Поиск в списке', exact: true }).fill('Школа');
  await expect(section.getByText('Выбор строк сброшен: изменились условия поиска.', { exact: true })).toBeVisible();
  await expect(section.getByTestId('ui-table-result')).toContainText('Выбрано: 0');
  await fieldSelect(section, 'Статус списка', 'Готово к съёмке', p);
  await section.getByLabel('Дата с', { exact: true }).fill('2026-08-01');
  await section.getByLabel('Дата по', { exact: true }).fill('2026-08-31');
  await expect(section.getByTestId('ui-table-result')).toHaveText('Найдено: 4 · Выбрано: 0');
  expect((await visibleRows(section).evaluateAll((rows) => rows.map((row) => row.getAttribute('data-row-id')))).sort()).toEqual([
    'INS-001',
    'INS-019',
    'INS-037',
    'INS-043'
  ]);
  await section.getByLabel('Дата с', { exact: true }).fill('2026-09-30');
  await expect(section.getByText('Дата начала должна быть не позже даты окончания.', { exact: true })).toBeVisible();
  await section.getByRole('button', { name: 'Сбросить фильтры', exact: true }).click();
  await section.getByRole('textbox', { name: 'Поиск в списке', exact: true }).fill('158-007');
  await expect(visibleRows(section)).toHaveCount(1);
  await expect(visibleRows(section)).toContainText('Большое путешествие');
  await section.getByRole('textbox', { name: 'Поиск в списке', exact: true }).fill('Нет такого учреждения');
  await expect(section.getByRole('heading', { name: 'Ничего не найдено', exact: true })).toBeVisible();
});
Then('таблица сортирует числа и даты и сохраняет страницу карточки', async function (this: CustomWorld) {
  const p = page(this);
  await p.setViewportSize({ width: 1440, height: 1000 });
  const section = p.getByTestId('ui-table-orders');
  expect((await visibleRows(section).first().boundingBox())?.height).toBeLessThanOrEqual(56);
  await p.getByRole('button', { name: 'Компактная', exact: true }).click();
  expect((await visibleRows(section).first().boundingBox())?.height).toBeLessThanOrEqual(48);
  await section.getByRole('button', { name: 'Сортировать: Сумма', exact: true }).click();
  await expect(visibleRows(section).first()).toHaveAttribute('data-row-id', 'ORDER-001');
  await section.getByRole('button', { name: 'Сортировать: Сумма', exact: true }).click();
  await expect(visibleRows(section).first()).toHaveAttribute('data-row-id', 'ORDER-050');
  await expect(section.getByRole('columnheader', { name: 'Сортировать: Сумма', exact: true })).toHaveAttribute('aria-sort', 'descending');
  await section.getByRole('button', { name: 'Следующая страница', exact: true }).click();
  await expect(section.getByTestId('ui-table-range')).toHaveText('11–20 из 50');
  const id = await visibleRows(section).first().getAttribute('data-row-id');
  const open = section.getByRole('button', { name: 'Открыть ' + id, exact: true });
  await open.focus();
  await p.keyboard.press('Enter');
  await expect(p.getByRole('dialog')).toContainText(id!);
  await p.keyboard.press('Escape');
  await expect(open).toBeFocused();
  await expect(section.getByTestId('ui-table-range')).toHaveText('11–20 из 50');
  await section.getByRole('button', { name: 'Сортировать: Создан', exact: true }).click();
  const dates = await visibleRows(section).locator('[data-column=date]').allTextContents();
  const parsed = dates.map((value) => {
    const [d, m, y] = value.trim().split('.');
    return y + '-' + m + '-' + d;
  });
  expect(parsed).toEqual([...parsed].sort());
  await fieldSelect(section, 'Строк на странице', '25', p);
  await expect(visibleRows(section)).toHaveCount(25);
  await expect(section.getByTestId('ui-table-range')).toHaveText('1–25 из 50');
});
Then('таблица удаляет выбранные записи с подтверждением и исправляет страницу', async function (this: CustomWorld) {
  const p = page(this);
  await p.setViewportSize({ width: 1440, height: 1000 });
  const section = p.getByTestId('ui-table-orders');
  await section.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
  await expect(p.getByRole('dialog')).toHaveCount(0);
  await section.getByRole('button', { name: 'Следующая страница', exact: true }).click();
  await section.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
  await expect(section.getByTestId('ui-table-result')).toContainText('Выбрано: 20');
  await section.getByRole('button', { name: 'Удалить выбранные', exact: true }).click();
  await expect(p.getByRole('dialog')).toContainText('Выбрано: 20');
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
  await expect(section.getByTestId('ui-table-result')).toContainText('Найдено: 50');
  await section.getByRole('button', { name: 'Удалить выбранные', exact: true }).click();
  await p.getByRole('button', { name: 'Подтвердить удаление записей', exact: true }).click();
  await expect(section.getByTestId('ui-table-result')).toHaveText('Найдено: 30 · Выбрано: 0');
  await section.getByRole('button', { name: 'Следующая страница', exact: true }).click();
  await expect(section.getByTestId('ui-table-range')).toHaveText('21–30 из 30');
  await section.getByRole('checkbox', { name: 'Выбрать все на странице', exact: true }).check();
  await section.getByRole('button', { name: 'Удалить выбранные', exact: true }).click();
  await p.getByRole('button', { name: 'Подтвердить удаление записей', exact: true }).click();
  await expect(section.getByTestId('ui-table-range')).toHaveText('11–20 из 20');
  await expect(section.getByRole('button', { name: 'Следующая страница', exact: true })).toBeDisabled();
  await expect(p.getByTestId('ui-table-institutions').getByTestId('ui-table-result')).toHaveText('Найдено: 50 · Выбрано: 0');
});
Then('таблицы показывают состояния и восстанавливают записи', async function (this: CustomWorld) {
  const p = page(this);
  const section = p.getByTestId('ui-table-institutions');
  await section.getByRole('button', { name: 'Показать загрузку', exact: true }).click();
  await expect(section.getByRole('progressbar')).toBeVisible();
  await expect(visibleRows(section)).toHaveCount(10);
  await section.getByRole('button', { name: 'Ошибка загрузки', exact: true }).click();
  await expect(section.getByRole('alert')).toContainText('Не удалось загрузить');
  await section.getByRole('button', { name: 'Повторить загрузку списка', exact: true }).click();
  await expect(visibleRows(section)).toHaveCount(10);
  await section.getByRole('button', { name: 'Показать пустой список', exact: true }).click();
  await expect(section.getByRole('heading', { name: 'Список пока пуст', exact: true })).toBeVisible();
  await section.getByRole('button', { name: 'Восстановить 50 записей', exact: true }).click();
  await section.getByRole('textbox', { name: 'Поиск в списке', exact: true }).fill('158-004');
  await section.getByRole('button', { name: 'Открыть INS-004', exact: true }).click();
  await expect(p.getByRole('dialog')).toContainText('Не указано');
  await p.getByRole('button', { name: 'Закрыть карточку', exact: true }).click();
  await expect(section.getByRole('button', { name: 'Открыть INS-004', exact: true })).toBeFocused();
});
Then('таблицы на ширине {int} сохраняют поиск выбор карточку и действия', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 1000 });
  const section = p.getByTestId('ui-table-institutions');
  await section.getByRole('textbox', { name: 'Поиск в списке', exact: true }).fill('158-007');
  await expect(visibleRows(section)).toHaveCount(1);
  if (width < 1100) {
    await expect(section.locator('.ui-table-mobile')).toBeVisible();
    await section.getByLabel('Дополнительные сведения INS-007', { exact: true }).click();
    await expect(visibleRows(section)).toContainText('158-007');
    await fieldSelect(section, 'Сортировать по', 'Участников', p);
  }
  await section.getByRole('checkbox', { name: 'Выбрать INS-007', exact: true }).check();
  await expect(section.getByTestId('ui-table-result')).toContainText('Выбрано: 1');
  await section.getByRole('button', { name: 'Действия INS-007', exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
  await p.getByText('Открыть INS-007', { exact: true }).click();
  await expect(p.getByRole('dialog')).toContainText('Большое путешествие');
  await p.keyboard.press('Escape');
  await expect(section.getByRole('button', { name: 'Открыть INS-007', exact: true })).toBeFocused();
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await p.evaluate(() => {
    document.documentElement.style.fontSize = '32px';
  });
  expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await section.getByRole('button', { name: 'Действия INS-007', exact: true }).click();
  await p.getByText('Удалить INS-007', { exact: true }).click();
  await p.getByRole('button', { name: 'Подтвердить удаление записей', exact: true }).click();
  await expect(section.getByRole('heading', { name: 'Ничего не найдено', exact: true })).toBeVisible();
});
Then('изменения таблиц изолированы от покупательского хранилища', async function (this: CustomWorld) {
  const p = page(this);
  const snapshot = () =>
    p.evaluate(() => Object.fromEntries(Object.entries(localStorage).filter(([key]) => key.startsWith('morefoto:demo:'))));
  const before = await snapshot();
  const section = p.getByTestId('ui-table-orders');
  await section.getByRole('button', { name: 'Показать пустой список', exact: true }).click();
  await p.getByRole('button', { name: 'Сбросить образцы', exact: true }).click();
  await expect(section.getByTestId('ui-table-result')).toHaveText('Найдено: 50 · Выбрано: 0');
  expect(await snapshot()).toEqual(before);
});
