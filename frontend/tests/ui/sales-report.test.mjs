import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  CHILD_STATUS,
  childOrdersText,
  descendFilter,
  pdfFileName,
  reportCrumbs,
  reportFigures,
  reportFilter,
  reportTitle,
  rowFilter
} from '../../src/modules/morefoto/sales-report/rules.ts';

const I = '9b2f1c4e-3a5d-4e6f-8a7b-1c2d3e4f5a6b';
const S = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
const G = '0f8fad5b-d9cb-469f-a165-70867728950e';
const zero = { children: 0, orderedFamilies: 0, paidFamilies: 0, paidOrders: 0, prints: 0, digitalFiles: 0, bundles: 0, paidTotal: 0 };
const report = (level, rows = []) => ({
  level,
  institution: 'all' === level ? null : { id: I, name: 'Детский сад № 5' },
  shoot: 'shoot' === level || 'group' === level ? { id: S, name: 'Осень' } : null,
  group: 'group' === level ? { id: G, name: 'Солнышко' } : null,
  totals: zero,
  products: [],
  rows: rows.map((id) => ({ id, name: id, totals: zero })),
  children: [],
  generatedAt: '2026-10-24T21:30:00+00:00'
});

test('the address keeps only well-formed filters', () => {
  assert.deepEqual(reportFilter({ institutionId: I, shootId: 'x', groupId: [G], other: G }), { institutionId: I });
  assert.deepEqual(reportFilter({}), {});
});

test('a row opens the level below with all its ancestors', () => {
  assert.deepEqual(rowFilter(report('all'), I), { institutionId: I });
  assert.deepEqual(rowFilter(report('institution'), S), { institutionId: I, shootId: S });
  assert.deepEqual(rowFilter(report('shoot'), G), { institutionId: I, shootId: S, groupId: G });
});

test('breadcrumbs start at the top of the role', () => {
  const titles = (role) => reportCrumbs(report('group'), role).map((crumb) => crumb.title);
  assert.deepEqual(titles('organizer'), ['Все учреждения', 'Детский сад № 5', 'Осень', 'Солнышко']);
  assert.deepEqual(titles('curator')[0], 'Все учреждения');
  assert.deepEqual(titles('head'), ['Детский сад № 5', 'Осень', 'Солнышко']);
  assert.deepEqual(titles('teacher'), ['Детский сад № 5', 'Осень', 'Солнышко']);
  assert.equal(reportCrumbs(report('group'), 'teacher').at(-1).filter, undefined);
  assert.equal(reportTitle(report('all')), 'Все учреждения');
});

test('the head lands on the institution, the teacher goes down to the only group', () => {
  assert.deepEqual(descendFilter(report('all', [I]), 'head'), { institutionId: I });
  assert.equal(descendFilter(report('institution', [S]), 'head'), null);
  assert.deepEqual(descendFilter(report('institution', [S]), 'teacher'), { institutionId: I, shootId: S });
  assert.deepEqual(descendFilter(report('shoot', [G]), 'teacher'), { institutionId: I, shootId: S, groupId: G });
  assert.equal(descendFilter(report('shoot', [G, 'other']), 'teacher'), null);
  assert.equal(descendFilter(report('all', [I]), 'organizer'), null);
  assert.equal(descendFilter(report('group'), 'teacher'), null);
});

test('figures read like the PDF', () => {
  const figures = reportFigures({
    children: 21,
    orderedFamilies: 12,
    paidFamilies: 9,
    paidOrders: 1,
    prints: 2,
    digitalFiles: 5,
    bundles: 1,
    paidTotal: 123_450
  });
  assert.deepEqual(
    figures.map((figure) => [figure.key, figure.value.replace(/\s/g, ' '), figure.label]),
    [
      ['total', '1 234,50 ₽', 'оплачено родителями'],
      ['orders', '1', 'оплаченный заказ'],
      ['families', '12 / 9', 'заказали / оплатили, из 21 ребёнка'],
      ['prints', '2', 'печатных фото'],
      ['files', '5', 'цифровых файлов и 1 комплект «все фото»']
    ]
  );
  assert.equal(childOrdersText(1, 3), '1 из 3');
  assert.equal(childOrdersText(2, 2), '2');
  assert.equal(reportFigures({ ...zero, paidTotal: 500_000 })[0].value.replace(/\s/g, ' '), '5 000 ₽');
});

test('every child status has a label and a tone; the PDF name uses the Moscow date', () => {
  assert.deepEqual(
    Object.entries(CHILD_STATUS).map(([key, value]) => [key, value.label, value.tone]),
    [
      ['paid', 'Оплатил', 'success'],
      ['late', 'Поздняя оплата', 'warning'],
      ['unpaid', 'Заказ без оплаты', 'danger'],
      ['none', 'Нет заказа', 'neutral']
    ]
  );
  assert.equal(pdfFileName('group', '2026-10-24T21:30:00+00:00'), 'morefoto-otchet-group-2026-10-25.pdf');
});
