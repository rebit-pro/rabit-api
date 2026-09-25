import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cabinetNavigation } from '../../src/modules/morefoto/layouts/navigation.ts';

const titles = (groups) => groups.map((group) => [group.title, group.items.map((item) => item.title)]);
const organizerPermissions = ['catalog.manage', 'staff.manage', 'order.read', 'media.manage'];

test('live organizer sees work and settings groups', () => {
  assert.deepEqual(titles(cabinetNavigation({ role: 'organizer', permissions: organizerPermissions, demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников', 'Заказы', 'Платежи']],
    ['Настройки', ['Каталог и цены', 'Сотрудники']]
  ]);
});

test('live roles keep the visibility of the router guards', () => {
  assert.deepEqual(titles(cabinetNavigation({ role: 'curator', permissions: ['order.read'], demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников', 'Заказы', 'Платежи']]
  ]);
  assert.deepEqual(titles(cabinetNavigation({ role: 'head', permissions: [], demo: false })), [
    ['Работа', ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников', 'Вопрос куратору']]
  ]);
  assert.deepEqual(titles(cabinetNavigation({ role: 'teacher', permissions: [], demo: false })), [
    ['Работа', ['Мои группы', 'Ссылки и сроки', 'Списки сотрудников', 'Вопрос куратору']]
  ]);
});

test('permissions, not roles, open settings in live mode', () => {
  const groups = cabinetNavigation({ role: 'organizer', permissions: [], demo: false });
  assert.deepEqual(titles(groups), [['Работа', ['Обзор', 'Учреждения', 'Ссылки и сроки', 'Списки сотрудников']]]);
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
