import test from 'node:test';
import assert from 'node:assert/strict';
import {
  handoverErrorText,
  handoverOrderText,
  handoverSummary,
  receiptText,
  receiptsHint
} from '../../src/modules/morefoto/orders/live/receipt-rules.ts';

// G2: the receipts of a paid order and the handover of the print of a group.
const receipt = (kind, status, modes = ['full_prepayment']) => ({
  id: 'r',
  kind,
  status,
  amount: 60000,
  items: modes.map((paymentMode) => ({ description: 'Печать 10×15', quantity: 2, amount: 30000, paymentMode })),
  createdAt: '2026-10-17T12:00:00+03:00',
  registeredAt: null,
  fiscalDocumentNumber: null,
  fiscalStorageNumber: null,
  fiscalAttribute: null,
  handedAt: null
});
const order = (state, patch = {}) => ({
  orderId: 'o-' + state,
  orderNumber: 'MF-1',
  childCodes: ['A', 'B'],
  prints: 3,
  paidAt: '2026-10-17T12:00:00+03:00',
  state,
  handedAt: null,
  receiptStatus: null,
  reason: null,
  ...patch
});

test('the receipts block explains where receipts come from or stays hidden', () => {
  assert.equal(receiptsHint(false, []), null);
  assert.equal(receiptsHint(true, []), 'Чек придёт на e-mail заказа после подтверждения оплаты.');
  assert.match(receiptsHint(false, [receipt('payment', 'sent')]), /«Проверка чеков»/);
});

test('each receipt says what it settles', () => {
  assert.match(receiptText(receipt('payment', 'sent')), /придёт закрывающий чек/);
  assert.equal(receiptText(receipt('payment', 'sent', ['full_payment'])), 'Полный расчёт за электронные фотографии.');
  assert.match(receiptText(receipt('closing', 'sent', ['full_payment'])), /предоплата зачтена/);
  assert.equal(receiptText(receipt('closing', 'pending', ['full_payment'])), 'Печать выдана — закрывающий чек формируется.');
  assert.match(receiptText(receipt('payment', 'failed')), /Чек не сформирован/);
});

test('the handover summary counts the orders with print', () => {
  const view = (orders) => ({
    groupId: 'g',
    groupName: 'Солнышко',
    enabled: true,
    orders,
    summary: {
      waiting: orders.filter((item) => item.state === 'waiting').length,
      handed: orders.filter((item) => item.state === 'handed').length,
      blocked: orders.filter((item) => item.state === 'blocked').length
    }
  });
  assert.equal(handoverSummary(view([])), null);
  assert.equal(
    handoverSummary(view([order('waiting'), order('handed'), order('blocked')])),
    'Выдано 1 из 3 заказов с печатью. Ждут отметки: 1. Без закрывающего чека: 1.'
  );
  assert.equal(handoverSummary(view([order('handed')])), 'Выдано 1 из 1 заказа с печатью.');
  assert.equal(handoverOrderText(order('waiting')), 'MF-1 · дети A, B · 3 отпечатка');
  assert.equal(handoverOrderText(order('waiting', { childCodes: [], prints: 11 })), 'MF-1 · 11 отпечатков');
});

test('handover refusals are explained', () => {
  assert.match(handoverErrorText({ network: true, status: null, code: '' }), /ничего не задвоит/);
  assert.equal(
    handoverErrorText({ network: false, status: 409, code: 'HANDOVER_CONFLICT' }),
    'Список заказов изменился. Обновите блок и отметьте выдачу снова.'
  );
  assert.equal(
    handoverErrorText({ network: false, status: 409, code: 'RECEIPTS_DISABLED' }),
    'Чеки магазина выключены — отметить выдачу нельзя.'
  );
  assert.equal(handoverErrorText({ network: false, status: 500, code: '' }), 'Не удалось отметить выдачу. Повторите позже.');
});
