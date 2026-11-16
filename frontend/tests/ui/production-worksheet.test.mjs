import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  reportSelection,
  worksheetSourceKey,
  worksheetSourceSelection,
  worksheetSources,
  worksheetOrderName,
  worksheetCells,
  worksheetColumns,
  worksheetError,
  worksheetFileContext
} from '../../src/modules/morefoto/sales-report/worksheet.ts';

const id = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
const otherId = '0f8fad5b-d9cb-469f-a165-70867728950e';
const line = {
  id: 'line-1',
  kind: 'physical',
  columnKey: 'snapshot-15x21',
  productName: 'Печать',
  format: '15x21',
  quantity: 2,
  printCount: 3,
  prints: 6,
  unitPrice: 100,
  total: 180,
  discount: 20,
  coveredByGift: false,
  selected: true,
  files: [
    {
      filename: 'IMG_0007.JPG',
      path: 'Школа/Группа/Печать/6шт/IMG_0007.JPG',
      printsInFile: 12
    }
  ]
};
const order = {
  id: 'order-1',
  number: 'MF-001',
  firstName: 'Иван',
  lastName: 'Иванов',
  childCodes: ['A', 'B'],
  ambiguous: true,
  paymentStatus: 'paid',
  latePayment: false,
  total: 500,
  includedTotal: 180,
  discount: 20,
  electronicTotal: 50,
  giftCodes: [],
  lines: [line, { ...line, id: 'outside', columnKey: 'outside', selected: false }]
};
const group = {
  id: 'group',
  name: 'Группа',
  institutionName: 'Школа',
  shootName: 'Осень',
  orders: [order],
  deferredOrders: [],
  outsideOrders: [],
  total: 180,
  prints: 6
};
const version = {
  type: 'institution',
  id,
  institutionId: 'institution',
  groupIds: ['group'],
  number: 1,
  kind: 'full',
  status: 'ready',
  layoutVersion: 2,
  createdAt: '2026-11-12T10:00:00+00:00',
  expiresAt: '2026-12-12T10:00:00+00:00',
  files: 1,
  prints: 12
};

test('T10/T13: the explicit version survives query/selection and never becomes latest or preview', () => {
  const selected = worksheetSourceSelection('institution:' + id);
  assert.deepEqual(selected, {
    view: 'worksheet',
    worksheetMode: 'final',
    archiveType: 'institution',
    archiveId: id
  });
  assert.equal(worksheetSourceKey(reportSelection({ ...selected, unused: 'ignored' })), 'institution:' + id);
  assert.deepEqual(worksheetSourceSelection('preview'), {
    view: 'worksheet',
    worksheetMode: 'preview'
  });
  assert.equal(worksheetSourceSelection('latest'), null);
  assert.equal(worksheetSourceSelection('institution:missing'), null);
  assert.equal(
    reportSelection({
      view: 'worksheet',
      worksheetMode: 'final',
      archiveId: 'missing'
    }).archiveId,
    'missing'
  );
  assert.equal(reportSelection({ view: ['worksheet'] }).view, '');
});

test('T10/T12: list exposes each ID and state, disabling unavailable files without picking a replacement', () => {
  const report = {
    versions: [
      version,
      {
        ...version,
        id: otherId,
        number: 2,
        kind: 'supplement',
        status: 'pending'
      }
    ]
  };
  const items = worksheetSources(report, Date.parse('2026-11-12T12:00:00+00:00'));
  assert.deepEqual(
    items.map((item) => item.value),
    ['preview', 'institution:' + id, 'institution:' + otherId]
  );
  assert.equal(items[1].props.disabled, false);
  assert.equal(items[2].props.disabled, true);
  assert.match(items[1].title, /Полный выпуск/);
  assert.match(items[2].title, /Дополнение.*Собирается/);
  assert.equal(worksheetSources({ versions: [version] }, Date.parse('2027-01-01T00:00:00+00:00'))[1].props.disabled, true);
  assert.equal(
    worksheetSources({ versions: [{ ...version, layoutVersion: 1 }] }, Date.parse('2026-11-12T12:00:00+00:00'))[1].props.disabled,
    true
  );
});

test('T05/T06/T11: original filenames, 15x21 and each order contribution remain intact', () => {
  const before = structuredClone(order);
  const cells = worksheetCells(order);
  assert.deepEqual(Object.keys(cells), ['snapshot-15x21']);
  assert.equal(cells['snapshot-15x21'][0].files[0].filename, 'IMG_0007.JPG');
  assert.equal(cells['snapshot-15x21'][0].prints, 6);
  assert.equal(cells['snapshot-15x21'][0].files[0].printsInFile, 12);
  assert.equal(cells['snapshot-15x21'][0].total, 180);
  assert.equal(worksheetCells(order, true).outside[0].id, 'outside');
  const columns = [
    { key: 'snapshot-15x21', name: 'Печать', format: '15x21' },
    { key: 'outside', name: 'Другая продукция', format: '20x30' }
  ];
  assert.deepEqual(worksheetColumns(group, columns), [columns[0]]);
  assert.deepEqual(worksheetColumns(group, columns, true), columns);
  assert.deepEqual(order, before);
});

test('T06: name stays on the order, missing name is explicit and equal names do not supply identity', () => {
  assert.equal(worksheetOrderName(order), 'Иванов Иван');
  assert.equal(worksheetOrderName({ ...order, firstName: null }), 'Не указано');
  assert.equal(worksheetOrderName({ ...order, lastName: null }), 'Не указано');
  assert.equal(worksheetOrderName({ ...order, id: 'another-order' }), 'Иванов Иван');
  assert.equal(order.ambiguous, true);
  assert.deepEqual(order.childCodes, ['A', 'B']);
});

test('T12/T15: failures explain the unavailable exact selection instead of rendering a success', () => {
  assert.match(worksheetError('WORKSHEET_SOURCE_MISSING'), /неполон/);
  assert.match(worksheetError('WORKSHEET_AREA_MISMATCH'), /другой области/);
  assert.match(worksheetError('WORKSHEET_TOO_LARGE'), /10 000/);
  assert.match(worksheetError('VERSION_NOT_FOUND'), /не найдена/);
  assert.equal(worksheetError('unrecognized'), null);
});

test('R2: same original basename stays distinguishable by public frame and child codes', () => {
  const a = {
    filename: 'IMG_0007.JPG',
    path: null,
    printsInFile: null,
    code: 'Z001',
    childCode: 'A'
  };
  const b = { ...a, code: 'Z002' };
  assert.equal(a.filename, b.filename);
  assert.equal(worksheetFileContext(a), 'Кадр Z001 · ребёнок A');
  assert.equal(worksheetFileContext(b), 'Кадр Z002 · ребёнок A');
  assert.equal(worksheetFileContext({ ...a, code: null }), null);
});
