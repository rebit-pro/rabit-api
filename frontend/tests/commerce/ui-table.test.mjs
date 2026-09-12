import test from 'node:test';
import assert from 'node:assert/strict';
import { sortTableRows, clampTablePage, tableCellText } from '../../src/modules/morefoto/ui/table-values.ts';
const columns = [{ key: 'value', label: 'Значение', sortable: true, type: 'number' }];
test('table compares numbers rather than formatted strings and keeps source order intact', () => {
  const rows = [
    { id: 'b', value: 1000 },
    { id: 'a', value: 90 },
    { id: 'c', value: 250 }
  ];
  assert.deepEqual(
    sortTableRows(rows, columns, { key: 'value', direction: 'asc' }).map((row) => row.id),
    ['a', 'c', 'b']
  );
  assert.deepEqual(
    rows.map((row) => row.id),
    ['b', 'a', 'c']
  );
});
test('table sorts dates across months and orders missing values last in both directions', () => {
  const rows = [
    { id: 'b', value: '2026-09-01' },
    { id: 'empty', value: null },
    { id: 'a', value: '2026-08-31' }
  ];
  const dates = [{ ...columns[0], type: 'date' }];
  assert.deepEqual(
    sortTableRows(rows, dates, { key: 'value', direction: 'asc' }).map((row) => row.id),
    ['a', 'b', 'empty']
  );
  assert.deepEqual(
    sortTableRows(rows, dates, { key: 'value', direction: 'desc' }).map((row) => row.id),
    ['b', 'a', 'empty']
  );
});
test('table tie order is stable by ID and forbidden columns do not sort', () => {
  const rows = [
    { id: 'b', value: 1 },
    { id: 'a', value: 1 }
  ];
  assert.deepEqual(
    sortTableRows(rows, columns, { key: 'value', direction: 'desc' }).map((row) => row.id),
    ['a', 'b']
  );
  assert.deepEqual(sortTableRows(rows, columns, { key: 'unknown', direction: 'asc' }), rows);
});
test('table page stays in range after filtering and removing the last page', () => {
  assert.equal(clampTablePage(5, 40, 10), 4);
  assert.equal(clampTablePage(4, 3, 10), 1);
  assert.equal(clampTablePage(1, 0, 10), 1);
});
test('table preserves zero values and explains missing values', () => {
  assert.equal(tableCellText(0, columns[0]), '0');
  assert.equal(tableCellText(null, columns[0]), 'Не указано');
  assert.equal(
    tableCellText('paid', { key: 'status', label: 'Статус', type: 'status', statuses: { paid: { label: 'Оплачен', tone: 'success' } } }),
    'Оплачен'
  );
});
