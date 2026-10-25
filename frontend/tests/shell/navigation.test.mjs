import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { cabinetNavigation } from '../../src/modules/morefoto/layouts/navigation.ts';

const titles = (groups) => groups.map((group) => [group.title, group.items.map((item) => item.title)]);
const organizerPermissions = ['catalog.manage', 'staff.manage', 'order.read', 'media.manage'];

test('live organizer sees work and settings groups', () => {
  assert.deepEqual(titles(cabinetNavigation({ role: 'organizer', permissions: organizerPermissions, demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Съёмки', 'Ссылки и сроки', 'Списки сотрудников', 'Заказы', 'Платежи', 'Отчёты']],
    ['Настройки', ['Каталог и цены', 'Сотрудники']]
  ]);
});

test('live roles keep the visibility of the router guards', () => {
  assert.deepEqual(titles(cabinetNavigation({ role: 'curator', permissions: ['order.read'], demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Съёмки', 'Ссылки и сроки', 'Списки сотрудников', 'Заказы', 'Платежи', 'Отчёты']]
  ]);
  assert.deepEqual(titles(cabinetNavigation({ role: 'head', permissions: [], demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Съёмки', 'Ссылки и сроки', 'Списки сотрудников', 'Отчёты', 'Вопрос куратору']]
  ]);
  assert.deepEqual(titles(cabinetNavigation({ role: 'teacher', permissions: [], demo: false })), [
    ['Работа', ['Мои группы', 'Ссылки и сроки', 'Списки сотрудников', 'Отчёты', 'Вопрос куратору']]
  ]);
});

test('permissions, not roles, open settings in live mode', () => {
  const groups = cabinetNavigation({ role: 'organizer', permissions: [], demo: false });
  assert.deepEqual(titles(groups), [['Работа', ['Обзор', 'Учреждения', 'Съёмки', 'Ссылки и сроки', 'Списки сотрудников', 'Отчёты']]]);
});

test('demo mode keeps the product screens without live API', () => {
  assert.deepEqual(titles(cabinetNavigation({ role: 'teacher', permissions: [], demo: true })), [
    ['Работа', ['Мои группы', 'Ссылки и сроки', 'Списки сотрудников', 'Доставка']]
  ]);
  const organizer = titles(cabinetNavigation({ role: 'organizer', permissions: [], demo: true }));
  assert.deepEqual(organizer[1], ['Настройки', ['Каталог и цены', 'Сотрудники']]);
  assert.ok(organizer[0][1].includes('Производство') && organizer[0][1].includes('Обращения'));
});

test('no role, no menu', () => {
  assert.deepEqual(cabinetNavigation({ role: null, permissions: organizerPermissions, demo: false }), []);
});

test('every live menu item leads to a route the live guard lets through', () => {
  const source = (path) => readFileSync(new URL('../../src/' + path, import.meta.url), 'utf8');
  // E10: a new section missing from the guard's list redirected every role back to the overview.
  const allowed = new Set(
    [
      ...source('router/index.ts')
        .match(/!\s*\[([^\]]+)\]\.includes\(String\(to\.name\)\)/)[1]
        .matchAll(/'([^']+)'/g)
    ].map((m) => m[1])
  );
  const routes = new Map(
    [...source('router/MainRoutes.ts').matchAll(/name: '([^']+)',\s*path: '([^']*)'/g)].map((m) => ['/cabinet/' + m[2], m[1]])
  );
  const permissions = ['catalog.manage', 'staff.manage', 'order.read', 'media.manage'];
  for (const role of ['organizer', 'curator', 'head', 'teacher']) {
    for (const group of cabinetNavigation({ role, permissions, demo: false })) {
      for (const item of group.items) {
        const name = routes.get(item.to);
        assert.ok(name, role + ': no route for ' + item.to);
        assert.ok(allowed.has(name), role + ': ' + item.to + ' (' + name + ') is not in the live guard list');
      }
    }
  }
});
