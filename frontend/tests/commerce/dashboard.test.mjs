import test from 'node:test';
import assert from 'node:assert/strict';
import { groupFinancials, sumGroups, filterGroups, visibleRequests } from '../../src/modules/morefoto/dashboard/rules.ts';
const groups = [
  { id: 'a', name: 'Звёзды', shootId: 's', kind: 'regular', state: 'open' },
  { id: 'b', name: 'Сотрудники', shootId: 's', kind: 'staff', state: 'closed' },
  { id: 'c', name: 'Звёзды', shootId: 'new', kind: 'regular', state: 'preparing' }
];
const order = (groupId, total, paymentStatus = 'paid', refunds = []) => ({
  groupId,
  paymentStatus,
  quote: { total },
  settlement: { refunds }
});
const rows = [
  order('a', 10001),
  order('b', 30000),
  order('a', 90000, 'unpaid'),
  order('foreign', 70000),
  order('a', 20000, 'paid', [
    { status: 'confirmed', amount: 5001 },
    { status: 'pending', amount: 1000 },
    { status: 'failed', amount: 3000 }
  ])
];
test('R13 institution counts staff once and only scoped paid orders', () => {
  const totals = groupFinancials({ groups }, rows);
  assert.deepEqual(sumGroups(groups, totals), { paidCount: 3, paid: 60001, refunded: 5001, pending: 1000, net: 55000 });
  assert.equal(totals.foreign, undefined);
});
test('R13 duplicate group selection cannot duplicate a financial total', () => {
  assert.equal(sumGroups([groups[0], groups[0]], groupFinancials({ groups }, rows)).net, 25000);
});
test('R13 historical group ID keeps orders in their original shoot', () => {
  const totals = groupFinancials({ groups }, rows);
  assert.equal(totals.c.paidCount, 0);
  assert.equal(totals.a.paidCount, 2);
});
test('R13 full confirmed refund retains paid count while pending and failed do not reduce net', () => {
  assert.deepEqual(groupFinancials({ groups }, [order('a', 12345, 'paid', [{ status: 'confirmed', amount: 12345 }])]).a, {
    paidCount: 1,
    paid: 12345,
    refunded: 12345,
    pending: 0,
    net: 0
  });
});
test('R13 unpaid pending and failed payments do not increase totals', () => {
  assert.equal(
    sumGroups(
      groups,
      groupFinancials(
        { groups },
        ['unpaid', 'pending', 'failed'].map((s) => order('a', 500, s))
      )
    ).net,
    0
  );
});
test('R13 combined filters distinguish repeated shoot names and preserve source data', () => {
  const before = structuredClone(groups);
  assert.deepEqual(filterGroups(groups, { q: ' ЗВЁЗ ', shoot: 's', state: 'open' }), [groups[0]]);
  assert.deepEqual(groups, before);
});
test('R13 foreign or unknown query values do not expand the allowed set', () => {
  assert.deepEqual(filterGroups(groups, { q: '', shoot: 'foreign', state: '' }), []);
  assert.deepEqual(filterGroups(groups, { q: '', shoot: '', state: 'unknown' }), []);
});
test('R13 clarification lists sort first and follow visible group and shoot filters', () => {
  const requests = [
    { id: '1', groupIds: ['a'], shootId: 's', status: 'submitted', createdAt: '2026-09-08' },
    { id: '2', groupIds: ['a'], shootId: 's', status: 'clarification', createdAt: '2026-09-07' },
    { id: '3', groupIds: ['b'], shootId: 's', status: 'transferred', createdAt: '2026-09-09' }
  ];
  assert.deepEqual(
    visibleRequests(requests, [groups[0]], 's').map((r) => r.id),
    ['2', '1']
  );
  assert.deepEqual(visibleRequests(requests, [groups[0]], 'new'), []);
});
test('R13 empty accessible scope has zero totals', () => {
  assert.deepEqual(groupFinancials({ groups: [] }, rows), {});
  assert.equal(sumGroups([], {}).paidCount, 0);
});
