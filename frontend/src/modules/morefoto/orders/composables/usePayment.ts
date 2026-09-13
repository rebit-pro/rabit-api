import { computed, onScopeDispose, shallowRef, watch } from 'vue';
import type { CartQuote } from '../../commerce/types';
import type { OrderSnapshot, PaymentOutcome, PaymentResult } from '../types';
import { loadOrder } from '../services/orders';
import { quoteSignature } from '../services/checkout';
import {
  completeDemoPayment,
  paymentContext,
  paymentIntent,
  PaymentChangedError,
  setPaymentTime,
  startDemoPayment
} from '../services/payment';

export function usePayment(source: () => OrderSnapshot) {
  const busy = shallowRef(false);
  const error = shallowRef('');
  const notice = shallowRef('');
  const outcome = shallowRef<PaymentOutcome>('paid');
  const revisedQuote = shallowRef<CartQuote | null>(null);
  const reviewed = shallowRef(false);
  let active = true;
  onScopeDispose(() => {
    active = false;
  });
  const context = computed(() => paymentContext(source()));
  const quote = computed(() => revisedQuote.value ?? source().quote);
  const pending = computed(() => source().paymentStatus === 'pending');
  const paid = computed(() => source().paymentStatus === 'paid');
  const canStart = computed(
    () =>
      !busy.value &&
      !pending.value &&
      !paid.value &&
      context.value.accepting &&
      quote.value.lines.length > 0 &&
      !quote.value.invalid.length &&
      (!revisedQuote.value || reviewed.value)
  );
  watch(
    () => source().paymentStatus,
    (status) => {
      if (status === 'pending' || status === 'paid') {
        revisedQuote.value = null;
        reviewed.value = false;
      }
    }
  );
  async function run(action: () => Promise<void>) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
      await action();
    } catch (cause) {
      if (!active) return;
      error.value = cause instanceof Error ? cause.message : 'Не удалось проверить оплату.';
      if (cause instanceof PaymentChangedError) {
        revisedQuote.value = cause.quote;
        reviewed.value = false;
      }
    } finally {
      if (active) busy.value = false;
    }
  }
  async function start() {
    if (!canStart.value) return;
    const order = source();
    const chosen = outcome.value;
    const signature = quoteSignature(quote.value);
    const intent = paymentIntent(order);
    await run(async () => {
      const result = await startDemoPayment(order.accessKey, intent, signature);
      if (!result.created) {
        if (active) notice.value = 'Показан результат уже начатой попытки. Новая оплата не создавалась.';
        return;
      }
      if (chosen === 'paid' || chosen === 'declined') {
        await completeDemoPayment(order.accessKey, result.attemptId, chosen);
      } else if (active) {
        notice.value =
          chosen === 'connection-lost'
            ? 'Связь прервалась после начала оплаты. Результат неизвестен; проверьте текущую попытку, не платите повторно.'
            : 'Тестовая попытка ждёт подтверждения. Новая оплата не требуется.';
      }
    });
  }
  async function respond(result: PaymentResult) {
    const order = source();
    const attempts = order.paymentAttempts ?? [];
    const attempt = attempts[attempts.length - 1];
    if (!attempt || !pending.value) return;
    await run(async () => {
      await completeDemoPayment(order.accessKey, attempt.id, result);
    });
  }
  async function check() {
    const key = source().accessKey;
    await run(async () => {
      const order = await loadOrder(key);
      if (active)
        notice.value =
          order.paymentStatus === 'pending' ? 'Подтверждение ещё не получено. Повторная оплата не требуется.' : 'Статус оплаты обновлён.';
    });
  }
  function time(value: 'before' | 'after') {
    if (busy.value) return;
    setPaymentTime(source(), value);
    error.value = '';
    notice.value = 'Демонстрационное время изменено. Сохранённые результаты платежей остаются в заказе.';
  }
  return { busy, error, notice, outcome, quote, revisedQuote, reviewed, context, pending, paid, canStart, start, respond, check, time };
}
