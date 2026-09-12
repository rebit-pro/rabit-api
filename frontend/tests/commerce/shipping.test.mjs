import { settlement } from '../../src/modules/morefoto/settlement/rules.ts';
import test from 'node:test';
import assert from 'node:assert/strict';
import { buildPlan, composeVersion } from '../../src/modules/morefoto/production/rules.ts';
import { projectProduction } from '../../src/modules/morefoto/production/projection.ts';
import { inspectDelivery, deliveryErrors } from '../../src/modules/morefoto/shipping/rules.ts';
import { pendingDelivery, rowSignature, orderSignature } from '../../src/modules/morefoto/shipping/identity.ts';
import { transferSummary } from '../../src/modules/morefoto/shipping/projection.ts';
function fixture() {
  const group = {
    id: 'g',
    name: 'Группа',
    kind: 'regular',
    institutionId: 'i',
    shootId: 's',
    state: 'closed',
    closesAt: '2026-09-07T09:00:00Z'
  };
  const order = {
    id: 'o',
    number: 'MF-1',
    groupId: 'g',
    productionStatus: 'not-started',
    paymentStatus: 'paid',
    paidAt: '2026-09-06T09:00:00Z',
    quote: {
      total: 50000,
      lines: [
        {
          id: 'l',
          photoId: 'p',
          photo: { id: 'p', code: 'A001-01' },
          childCode: 'A001',
          quantity: 2,
          product: { id: 'pair', name: 'Пара', kind: 'physical', printCount: 2 }
        }
      ]
    }
  };
  const input = {
    group,
    organization: { groups: [group] },
    photos: { photos: [] },
    orders: [order],
    state: { jobs: [], operations: [] },
    now: '2026-09-08T09:00:00Z'
  };
  const plan = buildPlan(input),
    version = composeVersion(plan, null, input.now, 'Анна', 'Проверено');
  version.startedAt = input.now;
  version.packages = { o: { at: input.now, actor: 'Анна' } };
  const job = { id: 'j', number: 'ПЗ-1', institutionId: 'i', shootId: 's', groupId: 'g', revision: 1, versions: [version], history: [] };
  input.state.jobs.push(job);
  const item = { group, job, plan, institutionName: 'Сад', shootName: 'Осень' };
  return { input, item, order, version, state: input.state, now: input.now };
}
const inspect = (f) => inspectDelivery(f.item, f.state, f.now);
function ready(f) {
  f.version.ready = { at: f.now, actor: 'Анна', responsible: 'Анна', comment: 'Сверено' };
}
function transfer(f) {
  ready(f);
  const t = {
    id: 't',
    number: 'ПД-1',
    institutionId: 'i',
    institutionName: 'Сад',
    shootId: 's',
    shootName: 'Осень',
    at: f.now,
    recordedAt: f.now,
    actor: 'Анна',
    responsible: 'Анна',
    receiver: 'Елена',
    comment: 'PRIVATE-COMMENT',
    groups: [structuredClone(inspect(f).transferGroup)]
  };
  f.state.transfers = [...(f.state.transfers ?? []), t];
  return t;
}
function command() {
  return { kind: 'ready', date: '2026-09-08T12:00', responsible: 'Анна', receiver: 'Елена', comment: 'Проверено', confirmed: true };
}
test('R15 readiness requires current closed started and completely packed version', () => {
  for (const change of [
    (f) => {
      f.item.plan.closed = false;
    },
    (f) => {
      f.item.job = null;
    },
    (f) => {
      f.version.plan = { ...f.version.plan, signature: 'old' };
    },
    (f) => {
      delete f.version.startedAt;
    },
    (f) => {
      f.version.packages = {};
    },
    (f) => {
      f.version.plan.rows = [];
    }
  ]) {
    const f = fixture();
    change(f);
    assert.equal(inspect(f).view.canReady, false);
    assert.ok(inspect(f).view.problem);
  }
  assert.equal(inspect(fixture()).view.canReady, true);
});
test('R15 ready packages count actual prints and cannot be marked twice', () => {
  const f = fixture();
  ready(f);
  const v = inspect(f);
  assert.equal(v.view.status, 'Готово к передаче');
  assert.equal(v.view.packs, 1);
  assert.equal(v.view.prints, 4);
  assert.equal(v.view.canReady, false);
  assert.equal(v.view.canUnready, true);
  assert.equal(v.transferGroup.rows.length, 1);
});
test('R15 reopened group keeps updated seven day deadline and cannot transfer', () => {
  const f = fixture();
  ready(f);
  f.input.group.state = 'open';
  f.input.group.closesAt = '2026-09-12T09:00:00Z';
  f.item.plan = buildPlan(f.input);
  const v = inspect(f);
  assert.equal(v.view.deadline, '2026-09-19T09:00:00.000Z');
  assert.equal(v.transferGroup, null);
});
test('R15 deadline boundary is not overdue and late readiness remains actionable', () => {
  const f = fixture();
  f.now = '2026-09-14T09:00:00Z';
  assert.equal(inspect(f).view.overdue, false);
  f.now = '2026-09-14T09:00:01Z';
  assert.equal(inspect(f).view.overdue, true);
  assert.equal(inspect(f).view.canReady, true);
});
test('R15 completed transfer removes pending packages and locks unready', () => {
  const f = fixture();
  transfer(f);
  const v = inspect(f);
  assert.equal(v.view.status, 'Передано в учреждение');
  assert.equal(v.view.packs, 0);
  assert.equal(v.view.canUnready, false);
  assert.equal(v.transferGroup, null);
  f.now = '2026-10-01T09:00:00Z';
  assert.equal(inspect(f).view.overdue, false);
});
test('R15 ready status projects only own paid physical composition without mutation', () => {
  const f = fixture();
  ready(f);
  const original = structuredClone(f.order);
  assert.equal(orderSignature(f.order), rowSignature(f.version.plan.rows));
  const o = projectProduction([f.order], f.state)[0];
  assert.equal(o.productionStatus, 'ready');
  assert.equal(o.physicalDelivery.readyAt, f.now);
  assert.deepEqual(f.order, original);
});
test('R15 hold pending refund and unapproved reprint suppress readiness', () => {
  for (const patch of [{ hold: true }, { refunds: [{ status: 'pending', amount: 100 }] }, { needsReprint: true, reprintApproved: false }]) {
    const f = fixture();
    ready(f);
    f.order.settlement = { ...settlement(f.order), ...patch };
    assert.notEqual(projectProduction([f.order], f.state)[0].productionStatus, 'ready');
  }
});
test('R15 removing readiness resets persisted parent projection to printing', () => {
  const f = fixture();
  ready(f);
  const persisted = projectProduction([f.order], f.state);
  delete f.version.ready;
  const o = projectProduction(persisted, f.state)[0];
  assert.equal(o.productionStatus, 'printing');
  assert.equal(o.physicalDelivery, undefined);
});
test('R15 new version does not inherit readiness', () => {
  const f = fixture();
  ready(f);
  const v = composeVersion(f.item.plan, f.item.job, f.now, 'Анна', 'Срок изменён');
  assert.equal(v.ready, undefined);
  f.item.job.versions.push(v);
  assert.equal(projectProduction([f.order], f.state)[0].productionStatus, 'printing');
});
test('R15 transfer projects delivered and preserves actual receipt after refund', () => {
  const f = fixture();
  transfer(f);
  f.order.settlement = { ...settlement(f.order), hold: true };
  const o = projectProduction([f.order], f.state)[0];
  assert.equal(o.productionStatus, 'delivered');
  assert.equal(o.physicalDelivery.number, 'ПД-1');
});
test('R15 tracked ready and delivered orders remain in current production plan', () => {
  for (const status of ['ready', 'delivered']) {
    const f = fixture();
    f.order.productionStatus = status;
    assert.equal(buildPlan(f.input).signature, f.item.plan.signature);
  }
});
test('R15 corrected order is pending again but unchanged delivered order is excluded', () => {
  const f = fixture();
  transfer(f);
  const rows = structuredClone(f.version.plan.rows);
  assert.deepEqual(pendingDelivery(rows, f.state), []);
  rows[0].quantity = 1;
  assert.equal(pendingDelivery(rows, f.state).length, 1);
});
test('R15 last transferred composition wins for A to B to A correction', () => {
  const f = fixture();
  const a = transfer(f),
    b = structuredClone(a);
  b.id = 'b';
  b.groups[0].rows[0].quantity = 1;
  f.state.transfers.push(b);
  assert.equal(pendingDelivery(f.version.plan.rows, f.state).length, 1);
  assert.equal(projectProduction([f.order], f.state)[0].productionStatus, 'ready');
});
test('R15 already delivered unchanged new version does not create zero package transfer', () => {
  const f = fixture();
  transfer(f);
  const v = structuredClone(f.version);
  v.number = 2;
  f.item.job.versions.push(v);
  assert.equal(inspect(f).view.status, 'Передано в учреждение');
  assert.equal(inspect(f).transferGroup, null);
});
test('R15 operation validates explicit confirmation, comment and names', () => {
  const c = command();
  assert.deepEqual(deliveryErrors(c, '2026-09-08T09:00:00Z', '2026-09-08T09:00:00Z'), {});
  c.confirmed = false;
  c.comment = ' ';
  c.responsible = ' ';
  c.kind = 'transfer';
  c.receiver = ' ';
  assert.deepEqual(Object.keys(deliveryErrors(c, '2026-09-08T09:00:00Z', '2026-09-08T09:00:00Z')).sort(), [
    'comment',
    'confirmed',
    'receiver',
    'responsible'
  ]);
});
test('R15 dates reject future impossible and earlier event but allow exact previous minute', () => {
  for (const date of ['2026-02-30T12:00', '2026-09-08T12:01', '2026-09-08T11:59'])
    assert.ok(deliveryErrors({ ...command(), date }, '2026-09-08T09:00:45Z', '2026-09-08T09:00:30Z').date);
  assert.equal(deliveryErrors(command(), '2026-09-08T09:00:45Z', '2026-09-08T09:00:30Z').date, undefined);
});
test('R15 unready validates reason without requiring date or receiver', () => {
  assert.deepEqual(deliveryErrors({ ...command(), kind: 'unready', date: '', responsible: '', receiver: '' }, 'now', 'then'), {});
});
test('R15 institution summary is explicit whitelist and scoped to allowed groups', () => {
  const f = fixture(),
    t = transfer(f);
  t.futureSecret = 'DO-NOT-EXPOSE';
  t.groups.push({ ...structuredClone(t.groups[0]), groupId: 'foreign', groupName: 'FOREIGN' });
  const summary = transferSummary(t, new Set(['g']), false),
    json = JSON.stringify(summary);
  for (const text of ['PRIVATE-COMMENT', 'DO-NOT-EXPOSE', 'FOREIGN', 'MF-1', 'A001', 'rows', 'orderId', 'signature'])
    assert.equal(json.includes(text), false, text);
  assert.equal(summary.groups[0].packs, 1);
  assert.equal(summary.groups[0].prints, 4);
  assert.equal(transferSummary(t, new Set(['g']), true).comment, 'PRIVATE-COMMENT');
});
