import { computed, onScopeDispose, shallowRef } from 'vue';
import type { OrderSnapshot, ReceiptChannel } from '../types';
import { getDemoNow } from '../../mocks/clock';
import { checkoutCapabilities } from '../services/checkout';
import { downloadAccess } from '../delivery/rules';
import { deliveryFor, prepareDownload, resendDelivery } from '../delivery/service';
export function useOrderDelivery(order: () => OrderSnapshot) {
  const busy = shallowRef('');
  const error = shallowRef('');
  const notice = shallowRef('');
  const receiptChannel = shallowRef<ReceiptChannel>(deliveryFor(order())?.receipt.channel ?? order().buyer.receiptChannel);
  const access = computed(() => downloadAccess(order(), getDemoNow()));
  const delivery = computed(() => deliveryFor(order()));
  const maxAvailable = computed(() => {
    order();
    return checkoutCapabilities().maxAvailable;
  });
  let active = true;
  const controller = new AbortController();
  onScopeDispose(() => {
    active = false;
    controller.abort();
  });
  async function run(key: string, action: () => Promise<void>) {
    if (busy.value) return;
    busy.value = key;
    error.value = '';
    notice.value = '';
    try {
      await action();
    } catch (cause) {
      if (active) error.value = cause instanceof Error ? cause.message : 'Не удалось выполнить действие. Повторите попытку.';
    } finally {
      if (active) busy.value = '';
    }
  }
  function download(photoId?: string) {
    return run(photoId ?? 'archive', async () => {
      const result = await prepareDownload(order().accessKey, photoId, controller.signal);
      if (!active) return;
      const url = URL.createObjectURL(result.blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = result.name;
      document.body.append(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 10000);
      notice.value = 'Файл передан браузеру. Проверьте папку загрузок.';
    });
  }
  function resend(target: 'receipt' | 'filesEmail') {
    return run(target, async () => {
      await resendDelivery(order().accessKey, target, target === 'receipt' ? receiptChannel.value : 'email');
      if (active)
        notice.value =
          target === 'receipt'
            ? 'Отправка чека имитирована. Настоящее сообщение не отправлялось.'
            : 'Отправка письма со ссылкой имитирована. Настоящее письмо не отправлялось.';
    });
  }
  return {
    access,
    delivery,
    busy,
    error,
    notice,
    receiptChannel,
    maxAvailable,
    download,
    resend
  };
}
