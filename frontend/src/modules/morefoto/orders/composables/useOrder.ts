import { onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { demoChangedEvent } from '../../mocks/storage';
import { loadOrder, resolveOrder, OrderUnavailableError } from '../services/orders';
import type { OrderSnapshot } from '../types';
export function useOrder() {
  const route = useRoute();
  const order = shallowRef<OrderSnapshot | null>(null);
  const loading = shallowRef(true);
  const error = shallowRef('');
  const unavailable = shallowRef(false);
  let request = 0;
  async function reload() {
    const current = ++request;
    loading.value = true;
    error.value = '';
    order.value = null;
    unavailable.value = false;
    try {
      const result = await loadOrder(String(route.params.orderKey ?? ''));
      if (current === request) order.value = result;
    } catch (cause) {
      if (current !== request) return;
      unavailable.value = cause instanceof OrderUnavailableError;
      error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить заказ.';
    } finally {
      if (current === request) loading.value = false;
    }
  }
  function sync() {
    if (!order.value) return;
    try {
      order.value = resolveOrder(String(route.params.orderKey ?? ''));
    } catch (cause) {
      order.value = null;
      unavailable.value = true;
      error.value = cause instanceof Error ? cause.message : 'Заказ недоступен.';
    }
  }
  watch(() => route.params.orderKey, reload, { immediate: true });
  onMounted(() => {
    window.addEventListener(demoChangedEvent, sync);
    window.addEventListener('storage', sync);
  });
  onScopeDispose(() => {
    request++;
    window.removeEventListener(demoChangedEvent, sync);
    window.removeEventListener('storage', sync);
  });
  return { order, loading, error, unavailable, reload };
}
