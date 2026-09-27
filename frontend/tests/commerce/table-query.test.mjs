import test from 'node:test';
import assert from 'node:assert/strict';
import { tableViewFromQuery, tableViewParams, tableViewQuery } from '../../src/modules/morefoto/ui/table-query.ts';
import { csvText } from '../../src/modules/morefoto/ui/csv.ts';
import { tableMoment } from '../../src/modules/morefoto/ui/table-values.ts';

const keys = ['createdAt', 'total'];
const fallback = { key: 'createdAt', direction: 'desc' };

test('server table view survives the URL and falls back on anything unknown (#92 DEC-05)', () => {
  const view = tableViewFromQuery({ sort: 'total', direction: 'asc', page: '3', pageSize: '50' }, keys, fallback);
  assert.deepEqual(view, { sort: { key: 'total', direction: 'asc' }, page: 3, pageSize: 50 });
  assert.deepEqual(tableViewQuery(view, fallback), { sort: 'total', direction: 'asc', page: '3', pageSize: '50' });
  assert.deepEqual(tableViewParams(view), { page: 3, pageSize: 50, sort: 'total', direction: 'asc' });

  const broken = tableViewFromQuery({ sort: 'BUYER_EMAIL', page: '-1', pageSize: '1000' }, keys, fallback);
  assert.deepEqual(broken, { sort: fallback, page: 1, pageSize: 25 });
  assert.deepEqual(tableViewQuery(broken, fallback), {}, 'defaults keep the link short');
});

test('CSV opens in a Russian Excel: BOM, semicolons, CRLF and quoted cells (#92 DEC-03)', () => {
  const text = csvText(
    ['Номер', 'Покупатель'],
    [
      ['MF-1', 'Иванова; Мария'],
      ['MF-2', 'Сказал "да"']
    ]
  );
  assert.equal(text, '﻿Номер;Покупатель\r\nMF-1;"Иванова; Мария"\r\nMF-2;"Сказал ""да"""\r\n');
});

test('table moment fits one line in Moscow time', () => {
  assert.equal(tableMoment('2026-09-26T09:15:00Z'), '26.09.2026, 12:15');
});
