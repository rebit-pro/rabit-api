import type { OrderSnapshot, PaymentResult } from '../types';
export function finishPayment(
  order: OrderSnapshot,
  attemptId: string,
  result: PaymentResult,
  now: string,
  closesAt: string
): OrderSnapshot {
  if (result !== 'paid' && result !== 'declined') throw new Error('Ожидается окончательный результат оплаты.');
  const attempts = order.paymentAttempts ?? [];
  const attempt = attempts.find((item) => item.id === attemptId);
  if (!attempt) throw new Error('Эта попытка оплаты не относится к заказу.');
  // Final provider results are immutable; duplicate or stale callbacks cannot reverse them.
  if (attempt.status !== 'pending' || order.paymentStatus === 'paid') return order;
  if (attempts[attempts.length - 1]?.id !== attempt.id) throw new Error('Проверьте текущую попытку оплаты.');
  if (Date.parse(now) < Date.parse(attempt.startedAt))
    throw new Error('Демонстрационное время раньше начала попытки. Верните корректную дату.');
  const paid = result === 'paid';
  return {
    ...order,
    paymentStatus: result,
    paymentAttempts: attempts.map((item) => (item.id === attemptId ? { ...item, status: result, completedAt: now } : item)),
    ...(paid ? { paidAt: now, latePayment: Date.parse(now) >= Date.parse(closesAt) } : {}),
    history: [
      ...(order.history ?? []),
      { type: paid ? 'payment-confirmed' : 'payment-declined', at: now, attemptId, amount: attempt.amount }
    ]
  };
}
