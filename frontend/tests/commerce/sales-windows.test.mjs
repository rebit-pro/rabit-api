import test from 'node:test';
import assert from 'node:assert/strict';
import {
  daysLeft,
  galleryBuys,
  mergeCartLines,
  quoteErrorMessage,
  salesStateTitle,
  sellableLines,
  sellsAnything,
  sellsKind
} from '../../src/modules/morefoto/commerce/sales-windows.ts';
import { checkoutOutcome, orderSalesBlock, reorderLines } from '../../src/modules/morefoto/orders/live/rules.ts';
import { paymentError } from '../../src/modules/morefoto/orders/live/payment-rules.ts';
import { digitalSalesAfter } from '../../src/modules/morefoto/handoff/rules.ts';
import { groupStateTone, linkStatus } from '../../src/modules/morefoto/ui/statusTone.ts';

const kinds = new Map([
  ['print', 'physical'],
  ['file', 'digital'],
  ['set', 'bundle']
]);
const line = (assignmentId, productId, quantity = 1) => ({ assignmentId, productId, quantity });
const orderLine = (id, kind, quantity = 1) => ({
  id,
  assignmentId: 'a-' + id,
  childCode: 'A',
  photoId: kind === 'bundle' ? null : 'p-' + id,
  productId: kind,
  quantity,
  product: { id: kind, name: kind, description: '', kind, price: 100, printCount: 1, staffDiscount: false, active: true },
  photo: kind === 'bundle' ? null : { id: 'p-' + id, assignmentId: 'a-' + id, code: 'A00' + id, width: 1, height: 1 },
  unitPrice: 100,
  total: 100 * quantity,
  discount: 0,
  coveredByGift: false
});
const order = (state, paymentStatus, kindsOfLines) => ({
  paymentStatus,
  period: {
    groupId: 'g',
    state,
    timezone: 'Europe/Moscow',
    sentAt: null,
    closesAt: null,
    digitalSalesUntil: null,
    deliveryDueAt: null,
    now: ''
  },
  quote: { lines: kindsOfLines.map((kind, index) => orderLine(String(index + 1), kind, kind === 'physical' ? 3 : 1)) }
});

test('E7-T13: the open window sells everything, the digital window only digital photos and bundles', () => {
  for (const kind of ['physical', 'digital', 'bundle']) assert.equal(sellsKind('open', kind), true, kind);
  assert.equal(sellsKind('digital', 'physical'), false);
  assert.equal(sellsKind('digital', 'digital'), true);
  assert.equal(sellsKind('digital', 'bundle'), true);
  for (const state of ['preparing', 'closed']) {
    assert.equal(sellsAnything(state), false, state);
    assert.equal(sellsKind(state, 'digital'), false, state);
  }
  assert.equal(sellsAnything('digital'), true);
  assert.deepEqual(['preparing', 'open', 'digital', 'closed'].map(salesStateTitle), [
    'Готовим фотографии',
    'Приём открыт',
    'Печать закрыта',
    'Приём завершён'
  ]);
});

test('E7-T13: print lines of a stored cart drop out in the digital window, unknown products stay for the server', () => {
  const stored = [line('a1', 'print', 2), line('a1', 'file'), line('a2', 'set'), line('a3', 'gone')];
  assert.deepEqual(sellableLines(stored, kinds, 'digital'), { kept: [stored[1], stored[2], stored[3]], dropped: 1 });
  assert.deepEqual(sellableLines(stored, kinds, 'open'), { kept: stored, dropped: 0 });
});

test('E7-T13: order lines join a cart without duplicating the same frame and product', () => {
  const merged = mergeCartLines([line('a1', 'file')], [line('a1', 'file'), line('a2', 'set'), line('a2', 'set')]);
  assert.deepEqual(merged, [line('a1', 'file'), line('a2', 'set')]);
});

test('E7-T13: an unpaid mixed order is blocked after the print close and offers its digital lines', () => {
  const mixed = order('digital', 'unpaid', ['physical', 'digital', 'bundle']);
  const block = orderSalesBlock(mixed);
  assert.equal(block?.kind, 'print');
  assert.deepEqual(
    block?.printLines.map((item) => item.product.kind),
    ['physical']
  );
  assert.deepEqual(reorderLines(block?.digitalLines ?? []), [line('a-2', 'digital'), line('a-3', 'bundle')]);
  assert.equal(orderSalesBlock(order('digital', 'unpaid', ['digital'])), null, 'a digital order is still payable');
  assert.equal(orderSalesBlock(order('open', 'pending', ['physical'])), null);
  assert.equal(orderSalesBlock(order('digital', 'paid', ['physical'])), null, 'a paid snapshot is never touched');
  assert.equal(orderSalesBlock(order('closed', 'declined', ['digital']))?.kind, 'closed');
});

test('E7-T13: a 0 ₽ order is never shown as unpayable and a gift bundle is not offered again', () => {
  const free = order('closed', 'unpaid', ['digital']);
  free.quote.total = 0;
  assert.equal(orderSalesBlock(free), null, 'nothing to pay is not a closed payment');
  const gifted = order('digital', 'unpaid', ['physical', 'bundle', 'digital']);
  gifted.quote.total = 500;
  gifted.quote.lines[1].coveredByGift = true;
  const block = orderSalesBlock(gifted);
  assert.equal(block?.kind, 'print');
  // The gift came with the print threshold; without print it would be charged in full.
  assert.deepEqual(reorderLines(block?.digitalLines ?? []), [line('a-3', 'digital')]);
  const onlyGift = order('digital', 'unpaid', ['physical', 'bundle']);
  onlyGift.quote.lines[1].coveredByGift = true;
  assert.deepEqual(orderSalesBlock(onlyGift)?.digitalLines, [], 'no bought digital photo — no reorder button');
});

test('E7-T13: server codes of the closed print explain what the buyer can still do', () => {
  const problem = { status: 409, code: 'PRINT_SALES_CLOSED', network: false };
  assert.match(checkoutOutcome(problem, false).message, /печать завершён/);
  assert.equal(checkoutOutcome(problem, true).kind, 'message', 'the code proves the key unused while recovering');
  assert.match(paymentError(problem), /новым заказом/);
  assert.match(quoteErrorMessage('PRINT_SALES_CLOSED') ?? '', /электронные фото останутся/);
  assert.equal(quoteErrorMessage('INVALID_CART'), null);
});

test('D4-T09: a markup correction pauses buying in an open or digital gallery and keeps the closed one closed', () => {
  assert.equal(galleryBuys({ state: 'open' }), true);
  assert.equal(galleryBuys({ state: 'digital', updating: false }), true);
  assert.equal(galleryBuys({ state: 'open', updating: true }), false);
  assert.equal(galleryBuys({ state: 'digital', updating: true }), false);
  assert.equal(galleryBuys({ state: 'closed', updating: false }), false);
  const paused = { status: 409, code: 'GALLERY_UPDATING', network: false };
  assert.match(checkoutOutcome(paused, false).message, /обновляются/);
  assert.equal(checkoutOutcome(paused, true).kind, 'message', 'the pause proves the key unused while recovering');
  assert.match(quoteErrorMessage('GALLERY_UPDATING') ?? '', /Выбор сохранён/);
});

test('E7-T13: digital sales last 14 calendar days and never close before print', () => {
  const sent = '2026-10-11T12:00:00.000Z';
  assert.equal(digitalSalesAfter(sent, '2026-10-18T12:00:00.000Z'), '2026-10-25T12:00:00.000Z');
  assert.equal(digitalSalesAfter(sent, '2026-10-30T12:00:00.000Z'), '2026-10-30T12:00:00.000Z');
});

test('E7-T13: countdown text and staff statuses know the digital window', () => {
  assert.equal(daysLeft('2026-10-25T12:00:00Z', '2026-10-20T12:00:00Z'), '5 дней');
  assert.equal(daysLeft('2026-10-25T12:00:00Z', '2026-10-25T02:00:00Z'), 'меньше суток');
  assert.equal(daysLeft('2026-10-25T12:00:00Z', '2026-10-25T12:00:00Z'), null);
  assert.equal(daysLeft(null, '2026-10-25T12:00:00Z'), null);
  assert.deepEqual(linkStatus({ state: 'digital', sentAt: '2026-10-11T12:00:00Z' }), { tone: 'info', text: 'Только электронные' });
  assert.equal(groupStateTone.digital, 'info');
});
