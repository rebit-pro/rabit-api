import { test } from 'node:test';
import assert from 'node:assert/strict';
import { STATUS_TONES, toneOf } from '../../src/components/status/tones.ts';
import {
  accountStatusTone,
  groupStateTone,
  linkStatus,
  paymentTone,
  productionTone,
  staffRequestTone
} from '../../src/modules/morefoto/ui/statusTone.ts';
import { paymentLabels, productionLabels } from '../../src/modules/morefoto/orders/formatters.ts';
import { requestStatus } from '../../src/modules/morefoto/handoff/display.ts';

const keys = (value) => Object.keys(value).sort();

test('every labelled domain status has a tone', () => {
  assert.deepEqual(keys(paymentTone), keys(paymentLabels));
  assert.deepEqual(keys(productionTone), keys(productionLabels));
  assert.deepEqual(keys(staffRequestTone), keys(requestStatus));
  assert.deepEqual(keys(accountStatusTone), ['active', 'blocked', 'pending']);
  assert.deepEqual(keys(groupStateTone), ['closed', 'open', 'preparing']);
  for (const map of [paymentTone, productionTone, staffRequestTone, accountStatusTone, groupStateTone]) {
    for (const tone of Object.values(map)) assert.ok(STATUS_TONES.includes(tone), tone);
  }
});

test('meaning of tones is fixed across screens', () => {
  assert.equal(accountStatusTone.blocked, 'danger');
  assert.equal(accountStatusTone.pending, 'pending');
  assert.equal(paymentTone.declined, 'danger');
  assert.equal(staffRequestTone.clarification, 'warning');
});

test('unknown values are neutral', () => {
  assert.equal(toneOf(paymentTone, 'refunded'), 'neutral');
  assert.equal(toneOf(paymentTone, null), 'neutral');
  assert.equal(toneOf(paymentTone, 'toString'), 'neutral');
  assert.equal(toneOf(paymentTone, 'paid'), 'success');
});

test('link card status follows closing, handover and preparation', () => {
  assert.deepEqual(linkStatus({ state: 'closed', sentAt: '2026-09-01T10:00:00Z', prepared: true }), {
    tone: 'neutral',
    text: 'Приём завершён'
  });
  assert.deepEqual(linkStatus({ state: 'open', sentAt: '2026-09-01T10:00:00Z' }), { tone: 'success', text: 'Приём открыт' });
  assert.deepEqual(linkStatus({ state: 'preparing', prepared: true }), { tone: 'info', text: 'Готова к передаче' });
  assert.deepEqual(linkStatus({ state: 'preparing', prepared: false }), { tone: 'warning', text: 'Требует проверки' });
});
