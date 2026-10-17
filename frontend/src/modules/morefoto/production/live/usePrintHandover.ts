import { isAxiosError } from 'axios';
import { shallowRef } from 'vue';
import { livePaymentsApi } from '../../orders/live/payments-api';
import { handoverErrorText } from '../../orders/live/receipt-rules';
import type { PrintHandovers } from '../../orders/live/receipt-types';

/**
 * G2: the handover of the paid print of one group. Marking sends one closing receipt per order and cannot be undone, so
 * the selection starts with every waiting order and is confirmed explicitly; every answer replaces the whole list.
 */
export function usePrintHandover() {
  const view = shallowRef<PrintHandovers | null>(null);
  const selected = shallowRef<string[]>([]);
  const marking = shallowRef(false);
  const busy = shallowRef(false);
  const error = shallowRef('');
  const notice = shallowRef('');
  let groupId = '';

  function waiting(): string[] {
    return view.value?.orders.filter((order) => order.state === 'waiting').map((order) => order.orderId) ?? [];
  }
  async function load(): Promise<void> {
    if (!groupId) return;
    const requested = groupId;
    try {
      const next = await livePaymentsApi.handovers(requested);
      if (requested === groupId) view.value = next;
    } catch {
      // The archive block stays usable; the handover appears once the server answers.
      if (requested === groupId) view.value = null;
    }
  }
  function open(id: string): void {
    groupId = id;
    view.value = null;
    marking.value = false;
    error.value = '';
    notice.value = '';
    void load();
  }
  function start(): void {
    selected.value = waiting();
    error.value = '';
    notice.value = '';
    marking.value = true;
  }
  function cancel(): void {
    marking.value = false;
  }
  async function confirm(): Promise<void> {
    if (busy.value || !groupId || !selected.value.length) return;
    busy.value = true;
    error.value = '';
    try {
      const count = selected.value.length;
      view.value = await livePaymentsApi.handOver(groupId, selected.value);
      marking.value = false;
      notice.value = 'Выдача отмечена: ' + count + ' — покупателям уйдут закрывающие чеки.';
    } catch (cause) {
      error.value = handoverErrorText({
        network: isAxiosError(cause) && !cause.response,
        status: isAxiosError(cause) ? (cause.response?.status ?? null) : null,
        code: isAxiosError(cause) ? ((cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '') : ''
      });
      if (isAxiosError(cause) && cause.response) await load();
    } finally {
      busy.value = false;
    }
  }
  return { view, selected, marking, busy, error, notice, open, load, start, cancel, confirm };
}
