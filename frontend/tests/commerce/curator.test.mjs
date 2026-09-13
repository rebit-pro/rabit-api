import test from 'node:test';
import assert from 'node:assert/strict';
import {
  orderInScope,
  parseExtension,
  caseErrors,
  matchesOrder,
  matchesCase,
  emptyFilters
} from '../../src/modules/morefoto/curator/rules.ts';
import { closingAfterCorrection } from '../../src/modules/morefoto/handoff/rules.ts';
const now = '2026-09-07T09:00:00Z',
  previous = '2026-09-06T15:00:00Z';
const organization = {
  institutions: [
    { id: 'sun', curatorId: 102 },
    { id: 'school', curatorId: 7 }
  ],
  groups: [
    { id: 'g', institutionId: 'sun' },
    { id: 'f', institutionId: 'school' }
  ]
};
const order = {
  id: 'o',
  number: 'MF-10',
  groupId: 'g',
  institutionId: 'sun',
  shootId: 'summer',
  groupName: 'Звёздочки',
  createdAt: '2026-09-07T22:10:00Z',
  paymentStatus: 'paid',
  productionStatus: 'printing',
  latePayment: true,
  buyer: { name: 'Имя', email: 'buyer@example.test', phone: '79001111111' },
  quote: { lines: [{ childCode: 'A', photo: { code: 'A001' } }] }
};
const command = { action: 'extend', closesAt: '2026-09-10T18:00', reason: 'Просьба родителя', confirmed: true };
test('R11 only organizer and assigned curator can see order context', () => {
  for (const role of ['head', 'teacher', 'parent', '']) assert.equal(orderInScope(order, organization, { id: 102, role }), false);
  assert.equal(orderInScope(order, organization, { id: 102, role: 'curator' }), true);
  assert.equal(orderInScope({ groupId: 'f' }, organization, { id: 102, role: 'curator' }), false);
  assert.equal(orderInScope({ groupId: 'missing' }, organization, { id: 1, role: 'organizer' }), false);
  assert.equal(orderInScope({ groupId: 'f' }, organization, { id: 1, role: 'organizer' }), true);
});
test('R11 extension is strictly later than now and current deadline, with valid Moscow dates', () => {
  assert.equal(parseExtension(command.closesAt, now, previous), '2026-09-10T15:00:00.000Z');
  for (const value of ['2026-09-07T12:00', '2026-09-06T18:00', '2026-09-31T12:00', '', '2026-09-10'])
    assert.equal(parseExtension(value, now, previous), null);
  assert.equal(parseExtension('2026-09-10T18:00', now, '2026-09-11T00:00:00Z'), null);
  assert.equal(parseExtension(command.closesAt, now, null), null);
});
test('R11 extending across year preserves minute and allows leap date only in leap year', () => {
  assert.equal(parseExtension('2028-02-29T18:30', now, previous), '2028-02-29T15:30:00.000Z');
  assert.equal(parseExtension('2027-02-29T18:30', now, previous), null);
  assert.equal(parseExtension('2027-01-01T00:30', '2026-12-31T00:00:00Z', '2026-12-31T15:00:00Z'), '2026-12-31T21:30:00.000Z');
});
test('R11 reason and impact confirmation are required', () => {
  assert.deepEqual(caseErrors(command, now, previous), {});
  assert.ok(caseErrors({ ...command, reason: '', confirmed: false }, now, previous).reason);
  assert.ok(caseErrors({ ...command, confirmed: false }, now, previous).confirmed);
  assert.ok(caseErrors({ ...command, reason: 'x'.repeat(501) }, now, previous).reason);
});
test('R11 reply needs a valid status and visible explanation', () => {
  assert.deepEqual(caseErrors({ action: 'reply', status: 'resolved', comment: 'Вопрос решён' }, now, previous), {});
  assert.ok(caseErrors({ action: 'reply', status: 'received', comment: 'ok' }, now, previous).status);
  assert.ok(caseErrors({ action: 'reply', status: 'resolved', comment: 'x'.repeat(2001) }, now, previous).comment);
});
test('R11 order filters combine context, independent states, Moscow dates and late payment', () => {
  const f = {
    ...emptyFilters(),
    institution: 'sun',
    shoot: 'summer',
    group: 'g',
    payment: 'paid',
    production: 'printing',
    late: 'yes',
    dateFrom: '2026-09-08',
    dateTo: '2026-09-08'
  };
  assert.equal(matchesOrder(order, f), true);
  for (const patch of [{ group: 'f' }, { shoot: 'autumn' }, { payment: 'unpaid' }, { production: 'ready' }, { dateTo: '2026-09-07' }])
    assert.equal(matchesOrder(order, { ...f, ...patch }), false);
});
test('R11 order search handles number, contact and photo code without crossing context filters', () => {
  for (const query of ['mf-10', 'BUYER@', 'a001', '7900']) assert.equal(matchesOrder(order, { ...emptyFilters(), query }), true);
  assert.equal(matchesOrder(order, { ...emptyFilters(), query: 'a001', institution: 'school' }), false);
});
test('R11 inbox uses appeal date and supports state, topic, reply address and request number', () => {
  const item = {
    order,
    request: {
      number: 'MF-10-H1',
      createdAt: '2026-09-09T22:30:00Z',
      status: 'received',
      topic: 'extension',
      replyEmail: 'reply@example.test',
      message: 'Нужно продлить'
    }
  };
  assert.equal(
    matchesCase(item, {
      ...emptyFilters(),
      dateFrom: '2026-09-10',
      dateTo: '2026-09-10',
      status: 'received',
      topic: 'extension',
      query: 'reply@'
    }),
    true
  );
  assert.equal(matchesCase(item, { ...emptyFilters(), status: 'resolved' }), false);
});
test('R11 correcting handoff retains agreed extension; a later actual start still gets a full seven days', () => {
  assert.equal(closingAfterCorrection('2026-09-01T09:00:00Z', '2026-09-15T15:00:00Z'), '2026-09-15T15:00:00Z');
  assert.equal(closingAfterCorrection('2026-09-10T09:00:00Z', '2026-09-15T15:00:00Z'), '2026-09-17T09:00:00.000Z');
});
