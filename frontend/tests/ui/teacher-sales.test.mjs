import test from 'node:test';
import assert from 'node:assert/strict';
import { bundleNote, paidTotalText, printsText, salesFigures } from '../../src/modules/morefoto/overview/sales-rules.ts';

// E9: the «Продажи» block of a teacher's group card.
const sales = { groupId: 'g', paidOrders: 4, families: 3, prints: 1205, digitalFiles: 5, bundles: 0, paidTotal: 1234550, products: [] };

test('four counters in reading order and the paid sum in rubles', () => {
  assert.deepEqual(
    salesFigures(sales).map((figure) => [figure.label, figure.value]),
    [
      ['Оплаченных заказов', 4],
      ['Купивших семей', 3],
      ['Печатных фото', 1205],
      ['Цифровых файлов', 5]
    ]
  );
  assert.equal(paidTotalText(sales).replace(/\s/g, ' '), '12 345,5 ₽');
  assert.equal(paidTotalText({ ...sales, paidTotal: 0 }).replace(/\s/g, ' '), '0 ₽');
  assert.equal(printsText(1205).replace(/\s/g, ' '), '1 205 шт.');
});

test('E9-DEC-04: bought «all photos» sets are named apart and declined', () => {
  assert.equal(bundleNote(0), '');
  assert.equal(bundleNote(1), 'и 1 комплект «все фото ребёнка»');
  assert.equal(bundleNote(3), 'и 3 комплекта «все фото ребёнка»');
  assert.equal(bundleNote(11), 'и 11 комплектов «все фото ребёнка»');
});
