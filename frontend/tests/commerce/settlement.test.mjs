import test from 'node:test';
import assert from 'node:assert/strict';
import {
  settlement,
  financials,
  remaining,
  lineBalances,
  parseMoney,
  allocateRefund,
  currentBuyer,
  currentLines,
  eligiblePhotos,
  availablePhotos,
  saleErrors,
  saleVersion
} from '../../src/modules/morefoto/settlement/rules.ts';
import { downloadAccess, downloadDeadline } from '../../src/modules/morefoto/orders/delivery/rules.ts';
const photo = { id: 'p1', code: 'A001-01' },
  second = { id: 'p2', code: 'A001-02' };
const digital = { id: 'd', kind: 'digital' },
  print = { id: 'p', kind: 'physical' };
const original = {
  id: 'o',
  groupId: 'g',
  paymentStatus: 'paid',
  paidAt: '2026-09-05T09:00:00Z',
  productionStatus: 'not-started',
  buyer: { name: 'Родитель', email: 'a@example.test', phone: '+79001111111' },
  quote: {
    total: 45000,
    gifts: [],
    lines: [
      { id: 'd', product: digital, photo, photoId: 'p1', childCode: 'A001', quantity: 1, unitPrice: 25000, total: 25000 },
      { id: 'p', product: print, photo, photoId: 'p1', childCode: 'A001', quantity: 2, unitPrice: 10000, total: 20000 }
    ]
  },
  digitalPhotos: [photo]
};
const order = () => structuredClone({ ...original, settlement: settlement(original) });
const command = (patch = {}) => ({
  action: 'refund',
  mode: 'amount',
  amount: '100.00',
  lineIds: [],
  reason: 'Согласовано с родителем',
  confirmed: true,
  files: 'keep',
  production: 'keep',
  ...patch
});
const refund = (status = 'pending', amount = 10000, allocations = { d: amount }) => ({
  id: 'r',
  status,
  amount,
  allocations,
  fileIds: [],
  history: []
});
test('R12 monetary input uses exact kopecks and rejects invalid precision or unsafe numbers', () => {
  for (const [v, n] of [
    ['0.01', 1],
    ['10,05', 1005],
    ['100', 10000],
    [' 10.5 ', 1050]
  ])
    assert.equal(parseMoney(v), n);
  for (const v of ['', '0', '-1', '1e2', '1.001', 'NaN', 'Infinity', '9007199254740991', '1 000']) assert.equal(parseMoney(v), null);
});
test('R12 processing reserves balance without changing financial net', () => {
  const o = order();
  o.settlement.refunds = [refund()];
  assert.equal(remaining(o), 35000);
  assert.equal(financials([o]).net, 45000);
  assert.equal(financials([o]).pending, 10000);
});
test('R12 failed refund frees reserved money and has no file effect', () => {
  const o = order();
  o.settlement.refunds = [{ ...refund('failed'), fileIds: null }];
  assert.equal(remaining(o), 45000);
  assert.deepEqual(availablePhotos(o), [photo]);
  assert.equal(financials([o]).refunded, 0);
});
test('R12 only confirmed refund reduces net; full refund keeps labelled paid count', () => {
  const o = order();
  o.settlement.refunds = [refund('confirmed', 45000, { d: 25000, p: 20000 })];
  assert.deepEqual(financials([o]), { paidCount: 1, paid: 45000, refunded: 45000, pending: 0, net: 0 });
});
test('R12 unpaid and pending orders never enter money totals', () => {
  assert.equal(financials([{ ...original, paymentStatus: 'pending' }, { ...original, paymentStatus: 'unpaid' }, original]).paid, 45000);
});
test('R12 amount refund allocates only unreserved original price balances', () => {
  const o = order();
  o.settlement.refunds = [refund('confirmed', 20000)];
  assert.deepEqual(lineBalances(o), { d: 5000, p: 20000 });
  assert.deepEqual(allocateRefund(o, command({ amount: '120' })), { d: 5000, p: 7000 });
});
test('R12 selected positions are deduplicated and cannot consume other lines', () => {
  const o = order();
  assert.deepEqual(allocateRefund(o, command({ mode: 'lines', lineIds: ['p', 'p'] })), { p: 20000 });
});
test('R12 refund above remainder and confirmation or reason omissions are rejected', () => {
  const o = order();
  o.settlement.refunds = [refund('pending', 40000, { d: 25000, p: 15000 })];
  assert.ok(saleErrors(o, command()).amount);
  assert.ok(saleErrors(o, command({ amount: '10', confirmed: false })).confirmed);
  assert.ok(saleErrors(o, command({ reason: '', amount: '10' })).reason);
});
test('R12 invalid selected lines and selective effect in amount mode are rejected', () => {
  assert.ok(saleErrors(order(), command({ mode: 'lines', lineIds: ['foreign'] })).lineIds);
  assert.ok(saleErrors(order(), command({ files: 'selected' })).files);
});
test('R12 contacts and current position projection preserve the purchase snapshot', () => {
  const o = order();
  o.settlement.corrections = [
    { kind: 'contacts', buyerAfter: { ...o.buyer, email: 'new@example.test' } },
    { kind: 'line', after: { ...o.quote.lines[1], quantity: 1 } }
  ];
  assert.equal(currentBuyer(o).email, 'new@example.test');
  assert.equal(currentLines(o)[1].quantity, 1);
  assert.deepEqual(o.quote, original.quote);
  assert.deepEqual(o.buyer, original.buyer);
});
test('R12 replacing a digital frame changes only future entitlement', () => {
  const o = order();
  o.settlement.corrections = [{ kind: 'line', after: { ...o.quote.lines[0], photo: second, photoId: second.id } }];
  assert.deepEqual(eligiblePhotos(o), [second]);
  assert.deepEqual(o.digitalPhotos, [photo]);
});
test('R12 removing digital line preserves a gift granted at purchase', () => {
  const o = order();
  o.quote.gifts = ['A001'];
  o.settlement.corrections = [{ kind: 'line', after: { ...o.quote.lines[0], quantity: 0 } }];
  assert.deepEqual(eligiblePhotos(o), [photo]);
});
test('R12 confirmed selected refund restricts only the captured entitlement ids', () => {
  const o = order();
  o.digitalPhotos.push(second);
  o.settlement.refunds = [{ ...refund('confirmed'), fileIds: ['p1'] }];
  assert.deepEqual(availablePhotos(o), [second]);
  assert.equal(downloadAccess(o, '2026-09-07T00:00:00Z').state, 'available');
});
test('R12 confirmed all-files restriction preserves history but prevents new download', () => {
  const o = order();
  o.settlement.refunds = [{ ...refund('confirmed'), fileIds: null }];
  assert.equal(downloadAccess(o, '2026-09-07T00:00:00Z').state, 'revoked');
  assert.deepEqual(o.digitalPhotos, [photo]);
});
test('R12 late decision allows files within original month; refund decision suspends delivery', () => {
  const o = order();
  o.latePayment = true;
  const now = '2026-09-07T00:00:00Z';
  assert.equal(downloadAccess(o, now).state, 'review');
  o.settlement.decisions = [{ decision: 'fulfil' }];
  assert.equal(downloadAccess(o, now).state, 'available');
  assert.equal(downloadAccess(o, downloadDeadline(o.paidAt)).state, 'expired');
  o.settlement.decisions.push({ decision: 'refund' });
  assert.equal(downloadAccess(o, now).state, 'refund');
  assert.equal(o.paidAt, original.paidAt);
});
test('R12 production that has started cannot be silently put on hold', () => {
  for (const status of ['printing', 'ready', 'delivered'])
    assert.ok(saleErrors({ ...order(), productionStatus: status }, command({ production: 'hold' })).production);
  assert.equal(saleErrors(order(), command({ production: 'hold' })).production, undefined);
});
test('R12 quantity reductions are explicit and never create unpaid additions', () => {
  for (const quantity of ['-1', '1.5', '3', ''])
    assert.ok(saleErrors(order(), command({ action: 'line', lineId: 'p', quantity })).quantity);
  for (const quantity of ['0', '1', '2'])
    assert.equal(saleErrors(order(), command({ action: 'line', lineId: 'p', quantity })).quantity, undefined);
});
test('R12 recovery and contact changes require verified order and valid email', () => {
  assert.ok(saleErrors(order(), command({ action: 'recover', email: 'invalid', verified: false, result: 'confirmed' })).email);
  assert.ok(saleErrors(order(), command({ action: 'recover', email: 'x@example.test', verified: false, result: 'confirmed' })).verified);
});
test('R12 concurrent payment or production and any settlement action invalidate an old form', () => {
  const o = order();
  const before = saleVersion(o);
  o.productionStatus = 'printing';
  assert.notEqual(saleVersion(o), before);
  o.productionStatus = 'not-started';
  o.settlement.revision++;
  assert.notEqual(saleVersion(o), before);
});
