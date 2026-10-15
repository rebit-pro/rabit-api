import { isAxiosError } from 'axios';
import { shallowRef, watch, type Ref } from 'vue';
import { orderEmailApi } from '../live/order-email';
import { orderEmailError, type OrderEmailSummary } from '../live/order-email-rules';
import { newRequestId } from '../live/rules';

/** J2: the letter block of a paid order — reads the summary and asks for the letter once more on the buyer's click. */
export function useOrderEmail(orderKey: Ref<string>) {
  const summary = shallowRef<OrderEmailSummary | null>(null);
  const busy = shallowRef(false);
  const error = shallowRef('');
  async function load(): Promise<void> {
    try {
      summary.value = await orderEmailApi.summary(orderKey.value);
    } catch {
      // The block is a convenience: without an answer the page keeps the link and the files.
      summary.value = null;
    }
  }
  async function resend(): Promise<void> {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
      await orderEmailApi.resend(orderKey.value, newRequestId());
    } catch (cause) {
      error.value = orderEmailError({
        network: isAxiosError(cause) && !cause.response,
        code: isAxiosError(cause) ? (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code : null
      });
    } finally {
      busy.value = false;
      await load();
    }
  }
  watch(orderKey, load, { immediate: true });
  return { summary, busy, error, resend };
}
