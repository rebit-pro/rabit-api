import { onScopeDispose, shallowRef, watch, type Ref } from 'vue';
import { livePaymentsApi } from '../live/payments-api';
import type { OrderReceipts } from '../live/receipt-types';

const POLL_MS = 15000;
const POLLS = 20;

/** G2: the receipts of a paid order. While one is being registered it is reloaded for a few minutes, then by the button. */
export function useOrderReceipts(orderKey: Ref<string>) {
  const receipts = shallowRef<OrderReceipts | null>(null);
  const loading = shallowRef(false);
  let polls = 0;
  let timer: ReturnType<typeof setTimeout> | undefined;
  function stop(): void {
    if (timer) clearTimeout(timer);
    timer = undefined;
  }
  async function load(): Promise<void> {
    stop();
    loading.value = true;
    try {
      receipts.value = await livePaymentsApi.receipts(orderKey.value);
      if (receipts.value.receipts.some((receipt) => receipt.status === 'pending') && polls++ < POLLS)
        timer = setTimeout(() => void load(), POLL_MS);
    } catch {
      // The block is a convenience: ЮKassa sends the receipt to the order email anyway.
      receipts.value = null;
    } finally {
      loading.value = false;
    }
  }
  function reload(): void {
    polls = 0;
    void load();
  }
  watch(orderKey, reload, { immediate: true });
  onScopeDispose(stop);
  return { receipts, loading, reload };
}
