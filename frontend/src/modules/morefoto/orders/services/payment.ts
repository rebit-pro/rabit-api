import type { CartQuote } from '../../commerce/types';
import { calculateQuote } from '../../commerce/services/pricing';
import { getCatalog } from '../../commerce/mocks/catalog';
import { resolveDemoGallery } from '../../gallery/services/gallery';
import { setGalleryScenario } from '../../gallery/mocks/state';
import { getDemoNow, setDemoNow } from '../../mocks/clock';
import { readDemo, writeDemo } from '../../mocks/storage';
import { simulateRequest } from '../../mocks/runtime';
import { resolveOrder, randomKey, saveOrder, withOrderLock } from './orders';
import { quoteSignature } from './checkout';
import { digitalEntitlements } from './entitlements';
import { finishPayment } from './payment-rules';
import type { OrderSnapshot, PaymentAttempt, PaymentResult } from '../types';

export function paymentContext(order: OrderSnapshot) {
  try {
    const gallery = resolveDemoGallery(order.galleryToken);
    return {
      now: gallery.referenceNow,
      closesAt: gallery.closesAt ?? order.closesAt,
      accepting: gallery.state === 'open' && !!gallery.closesAt && Date.parse(gallery.referenceNow) < Date.parse(gallery.closesAt)
    };
  } catch {
    return { now: getDemoNow(), closesAt: order.closesAt, accepting: false };
  }
}
export class PaymentChangedError extends Error {
  constructor(public quote: CartQuote) {
    super('Цена, состав или условия изменились. Проверьте новый итог перед оплатой.');
  }
}
export interface PaymentIntent {
  requestId: string;
  precedingAttemptId: string | null;
}
export function paymentIntent(order: OrderSnapshot): PaymentIntent {
  const attempts = order.paymentAttempts ?? [];
  const last = attempts[attempts.length - 1];
  if (last?.status === 'pending') return { requestId: last.requestId, precedingAttemptId: attempts[attempts.length - 2]?.id ?? null };
  const previous = readDemo<PaymentIntent | null>('payment:intent:' + order.id, null);
  if (previous && previous.precedingAttemptId === (last?.id ?? null)) return previous;
  const next = { requestId: randomKey(), precedingAttemptId: last?.id ?? null };
  writeDemo('payment:intent:' + order.id, next);
  return next;
}
export async function startDemoPayment(accessKey: string, intent: PaymentIntent, signature: string) {
  await simulateRequest();
  const result = await withOrderLock(() => {
    const order = resolveOrder(accessKey);
    const attempts = order.paymentAttempts ?? [];
    const previous = attempts.find((item) => item.requestId === intent.requestId);
    const last = attempts[attempts.length - 1];
    if (previous) return { order, attemptId: previous.id, created: false };
    if (order.paymentStatus === 'paid' || last?.status === 'pending') return { order, attemptId: last?.id ?? '', created: false };
    if ((last?.id ?? null) !== intent.precedingAttemptId)
      throw new Error('Статус оплаты обновился. Проверьте результат перед повторной попыткой.');
    if (!/^[a-f0-9]{32}$/.test(intent.requestId)) throw new Error('Обновите страницу оплаты.');
    const context = paymentContext(order);
    if (!context.accepting) throw new Error('Приём заказов закрыт. Начать новую оплату или повторить отказанную попытку нельзя.');
    const gallery = resolveDemoGallery(order.galleryToken);
    const source = order.quote.lines.map(({ id, childCode, photoId, productId, quantity }) => ({
      id,
      childCode,
      photoId,
      productId,
      quantity
    }));
    const quote = calculateQuote(gallery, source, getCatalog(gallery.groupId));
    if (quoteSignature(quote) !== signature) throw new PaymentChangedError(quote);
    if (quote.invalid.length || !quote.lines.length) throw new Error('Часть продукции больше недоступна. Новая оплата заблокирована.');
    const attempt: PaymentAttempt = {
      id: randomKey(),
      requestId: intent.requestId,
      amount: quote.total,
      status: 'pending',
      startedAt: context.now
    };
    const repriced = quoteSignature(order.quote) !== quoteSignature(quote);
    const updated: OrderSnapshot = {
      ...order,
      quote,
      digitalPhotos: digitalEntitlements(gallery, quote),
      paymentStatus: 'pending',
      paymentAttempts: [...attempts, attempt],
      history: [
        ...(order.history ?? []),
        ...(repriced ? [{ type: 'price-reviewed' as const, at: context.now, amount: quote.total, previousTotal: order.quote.total }] : []),
        { type: 'payment-started', at: context.now, attemptId: attempt.id, amount: attempt.amount }
      ]
    };
    saveOrder(updated);
    return { order: updated, attemptId: attempt.id, created: true };
  });
  if (readDemo('payment:lose-response-once', false)) {
    writeDemo('payment:lose-response-once', false);
    throw new Error('Ответ на запуск оплаты не получен. Проверьте статус текущей попытки; повторная оплата не требуется.');
  }
  return result;
}
export async function completeDemoPayment(accessKey: string, attemptId: string, result: PaymentResult): Promise<OrderSnapshot> {
  await simulateRequest();
  return withOrderLock(() => {
    const current = resolveOrder(accessKey);
    const context = paymentContext(current);
    const updated = finishPayment(current, attemptId, result, context.now, context.closesAt);
    if (updated !== current) saveOrder(updated);
    return updated;
  });
}
export function setPaymentTime(order: OrderSnapshot, value: 'before' | 'after') {
  const close = paymentContext(order).closesAt;
  setGalleryScenario('default');
  setDemoNow(value === 'after' ? new Date(Date.parse(close) + 1000).toISOString() : order.createdAt);
}
