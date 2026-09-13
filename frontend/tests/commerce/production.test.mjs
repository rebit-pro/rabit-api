import test from 'node:test';
import assert from 'node:assert/strict';
import { buildPlan, composeVersion, newCount, printCount, printCsv } from '../../src/modules/morefoto/production/rules.ts';
import { projectProduction } from '../../src/modules/morefoto/production/projection.ts';
import { settlement } from '../../src/modules/morefoto/settlement/rules.ts';
const group = { id: 'g', institutionId: 'i', shootId: 's', name: 'Группа', state: 'closed', closesAt: '2026-09-08T09:00:00Z' };
function input() {
  const order = {
    id: 'o',
    number: 'MF-1',
    groupId: 'g',
    audience: 'regular',
    productionStatus: 'not-started',
    paymentStatus: 'paid',
    paidAt: '2026-09-07T09:00:00Z',
    quote: {
      total: 50000,
      lines: [
        {
          id: 'l',
          photoId: 'p',
          photo: { id: 'p', code: 'A001-01' },
          childCode: 'A001',
          quantity: 3,
          product: { id: 'pair', name: '2×10×15', kind: 'physical', printCount: 2 }
        }
      ]
    }
  };
  order.settlement = settlement(order);
  return {
    group: structuredClone(group),
    organization: { groups: [group] },
    photos: { photos: [] },
    orders: [order],
    state: { jobs: [], operations: [] },
    now: '2026-09-09T09:00:00Z'
  };
}
function job(plan) {
  return { id: 'j', number: 'ПЗ-0001', groupId: 'g', versions: [composeVersion(plan, null, 'today', 'Анна', 'Проверено')] };
}
test('R14 pair units become six physical prints and inputs stay immutable', () => {
  const i = input(),
    before = structuredClone(i),
    plan = buildPlan(i);
  assert.equal(printCount(plan.rows), 6);
  assert.deepEqual(i, before);
});
test('R14 digital images and bundles never become print rows', () => {
  const i = input();
  i.orders[0].quote.lines.push(
    { ...i.orders[0].quote.lines[0], id: 'd', product: { kind: 'digital' } },
    { ...i.orders[0].quote.lines[0], id: 'b', product: { kind: 'bundle' } }
  );
  const p = buildPlan(i);
  assert.equal(p.rows.length, 1);
  assert.equal(p.digitalCount, 2);
});
test('R14 unpaid, unconfirmed and held orders are excluded', () => {
  for (const patch of [{ paymentStatus: 'pending' }, { paidAt: undefined }, { paymentStatus: 'unpaid' }, { paymentStatus: 'declined' }]) {
    const i = input();
    Object.assign(i.orders[0], patch);
    assert.equal(buildPlan(i).rows.length, 0);
  }
  const i = input();
  i.orders[0].settlement.hold = true;
  assert.equal(buildPlan(i).rows.length, 0);
});
test('R14 pending and full refunds block; partial confirmed keep approved physical composition', () => {
  for (const [status, amount, count] of [
    ['pending', 100, 0],
    ['confirmed', 50000, 0],
    ['confirmed', 100, 1],
    ['failed', 50000, 1]
  ]) {
    const i = input();
    i.orders[0].settlement.refunds = [{ status, amount }];
    assert.equal(buildPlan(i).rows.length, count);
  }
});
test('R14 late payment requires explicit fulfilment', () => {
  const i = input();
  i.orders[0].latePayment = true;
  assert.equal(buildPlan(i).rows.length, 0);
  i.orders[0].settlement.decisions = [{ decision: 'fulfil' }];
  assert.equal(buildPlan(i).rows.length, 1);
});
test('R14 approval is required for correction after printing', () => {
  const i = input();
  i.orders[0].settlement.needsReprint = true;
  assert.equal(buildPlan(i).rows.length, 0);
  i.orders[0].settlement.reprintApproved = true;
  assert.equal(buildPlan(i).rows.length, 1);
});
test('R14 malformed quantities and metadata exclude whole order', () => {
  for (const quantity of [-1, 1.5, NaN, Infinity, Number.MAX_SAFE_INTEGER]) {
    const i = input();
    i.orders[0].quote.lines[0].quantity = quantity;
    assert.equal(buildPlan(i).rows.length, 0);
  }
  const i = input();
  i.orders[0].quote.lines[0].photo = null;
  assert.equal(buildPlan(i).rows.length, 0);
});
test('R14 already printing outside the ledger must be reconciled', () => {
  const i = input();
  i.orders[0].productionStatus = 'printing';
  assert.equal(buildPlan(i).rows.length, 0);
});
test('R14 deadline follows actual close including extension', () => {
  const i = input();
  assert.equal(buildPlan(i).deliveryAt, '2026-09-15T09:00:00.000Z');
  i.group.state = 'open';
  i.group.closesAt = '2026-09-11T09:00:00Z';
  assert.equal(buildPlan(i).closed, false);
  i.now = '2026-09-12T09:00:00Z';
  assert.equal(buildPlan(i).deliveryAt, '2026-09-18T09:00:00.000Z');
});
test('R14 unchanged rows yield zero new prints after one start', () => {
  const plan = buildPlan(input()),
    j = job(plan);
  assert.equal(newCount(j.versions[0]), 6);
  j.versions[0].startedAt = 'today';
  assert.equal(newCount(composeVersion(plan, j, 'later', 'a', 'reason')), 0);
});
test('R14 increased quantity prints only the delta across all started versions', () => {
  const i = input(),
    j = job(buildPlan(i));
  j.versions[0].startedAt = 'today';
  i.orders[0].quote.lines[0].quantity = 4;
  const v2 = composeVersion(buildPlan(i), j, 'later', 'a', 'reason');
  assert.equal(newCount(v2), 2);
  v2.startedAt = 'later';
  j.versions.push(v2);
  assert.equal(newCount(composeVersion(buildPlan(i), j, 'again', 'a', 'reason')), 0);
});
test('R14 unstarted superseded version never counts as printed', () => {
  const i = input(),
    j = job(buildPlan(i));
  i.orders[0].quote.lines[0].quantity = 4;
  assert.equal(newCount(composeVersion(buildPlan(i), j, 'later', 'a', 'reason')), 8);
});
test('R14 replaced photo prints new row and records surplus of old row', () => {
  const i = input(),
    j = job(buildPlan(i));
  j.versions[0].startedAt = 'today';
  i.orders[0].quote.lines[0].photo = { id: 'other', code: 'A001-02' };
  i.orders[0].quote.lines[0].photoId = 'other';
  const v = composeVersion(buildPlan(i), j, 'later', 'a', 'reason');
  assert.equal(newCount(v), 6);
  assert.equal(v.surplus[0].next, 6);
});
test('R14 quantity decrease preserves prior output and requires no new prints', () => {
  const i = input(),
    j = job(buildPlan(i));
  j.versions[0].startedAt = 'today';
  i.orders[0].quote.lines[0].quantity = 1;
  const v = composeVersion(buildPlan(i), j, 'later', 'a', 'reason');
  assert.equal(newCount(v), 0);
  assert.equal(v.surplus[0].next, 4);
});
test('R14 unchanged packages carry forward; changed packages reset', () => {
  const i = input(),
    j = job(buildPlan(i));
  j.versions[0].packages.o = { actor: 'Анна', at: 'today' };
  assert.ok(composeVersion(buildPlan(i), j, 'later', 'a', 'reason').packages.o);
  i.orders[0].quote.lines[0].quantity = 1;
  assert.deepEqual(composeVersion(buildPlan(i), j, 'later', 'a', 'reason').packages, {});
});
test('R14 projection queues current orders, retains print history, leaves quote untouched', () => {
  const i = input(),
    j = job(buildPlan(i));
  i.state.jobs = [j];
  assert.equal(projectProduction(i.orders, i.state)[0].productionStatus, 'queued');
  j.versions[0].startedAt = 'today';
  assert.equal(projectProduction(i.orders, i.state)[0].productionStatus, 'printing');
  assert.equal(projectProduction(i.orders, i.state)[0].quote, i.orders[0].quote);
});
test('R14 removed unstarted order leaves queue; ready and delivered remain final', () => {
  const i = input(),
    j = job(buildPlan(i));
  i.state.jobs = [j];
  j.versions.push(composeVersion({ ...buildPlan(i), rows: [] }, j, 'later', 'a', 'reason'));
  assert.equal(projectProduction(i.orders, i.state)[0].productionStatus, 'not-started');
  for (const s of ['ready', 'delivered']) {
    i.orders[0].productionStatus = s;
    assert.equal(projectProduction(i.orders, i.state)[0].productionStatus, s);
  }
});
test('R14 staff keeps original group and pre-transfer child code in package rows', () => {
  const i = input();
  i.organization.groups.push({ ...group, id: 'staff', name: 'Сотрудники' });
  i.group = i.organization.groups[1];
  i.orders[0].groupId = 'staff';
  i.orders[0].audience = 'staff';
  i.orders[0].quote.lines[0].childCode = 'A099';
  i.photos.photos = [{ id: 'p', originalGroupId: 'g' }];
  i.photos.staffRequests = [
    { status: 'transferred', institutionId: 'i', results: [{ fromGroupId: 'g', fromChildCode: 'A001', photoIds: ['p'] }] }
  ];
  const r = buildPlan(i).rows[0];
  assert.equal(r.sourceGroupName, 'Группа');
  assert.equal(r.sourceChildCode, 'A001');
  assert.equal(r.childCode, 'A099');
});
test('R14 foreign original group cannot leak through a photo', () => {
  const i = input();
  i.organization.groups.push({ id: 'foreign', institutionId: 'other', name: 'Secret' });
  i.photos.photos = [{ id: 'p', originalGroupId: 'foreign' }];
  assert.equal(buildPlan(i).rows[0].sourceGroupName, 'Группа');
});
test('R14 unrelated unpaid order changes do not invalidate the job signature', () => {
  const i = input(),
    before = buildPlan(i).signature;
  i.orders.push({ ...i.orders[0], id: 'pending', paymentStatus: 'pending' });
  assert.equal(buildPlan(i).signature, before);
});
test('R14 CSV escapes separators, quotes, newlines and spreadsheet formulas', () => {
  const i = input();
  i.orders[0].number = '=HYPERLINK("x");\n';
  const j = job(buildPlan(i)),
    csv = printCsv(j, j.versions[0]);
  assert.ok(csv.startsWith('\ufeff'));
  assert.ok(csv.includes('"\'=HYPERLINK(""x"");\n"'));
  assert.equal(printCsv(j, j.versions[0]), csv);
  assert.ok(csv.includes('К новой печати этой версии'));
});
