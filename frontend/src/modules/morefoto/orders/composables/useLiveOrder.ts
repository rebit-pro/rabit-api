import { shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiProblem, liveOrdersApi } from '../live/api';
import type { BuyerOrder } from '../live/types';

export function useLiveOrder() {
  const route = useRoute();
  const order = shallowRef<BuyerOrder | null>(null);
  const loading = shallowRef(true);
  const error = shallowRef('');
  const missing = shallowRef(false);
  const copied = shallowRef(false);
  let request = 0;
  async function reload() {
    const id = ++request;
    loading.value = true;
    error.value = '';
    missing.value = false;
    order.value = null;
    try {
      const result = await liveOrdersApi.current(String(route.params.orderKey ?? ''));
      if (id === request) order.value = result;
    } catch (cause) {
      if (id !== request) return;
      missing.value = apiProblem(cause).status === 404;
      error.value = missing.value
        ? 'Ссылка на заказ недействительна или её срок истёк. Номер заказа и ссылка на галерею не открывают заказ.'
        : 'Не удалось загрузить заказ. Проверьте соединение и повторите.';
    } finally {
      if (id === request) loading.value = false;
    }
  }
  async function copyLink() {
    try {
      await navigator.clipboard.writeText(window.location.href);
      copied.value = true;
    } catch {
      copied.value = false;
    }
  }
  watch(() => route.params.orderKey, reload, { immediate: true });
  return { order, loading, error, missing, copied, reload, copyLink };
}
