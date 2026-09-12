import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { orderPeriod } from './period';
export function useOrderPeriod(group: () => string) {
  const version = shallowRef(0);
  const update = () => {
    version.value++;
  };
  onMounted(() => {
    window.addEventListener('morefoto:demo:changed', update);
    window.addEventListener('storage', update);
  });
  onScopeDispose(() => {
    window.removeEventListener('morefoto:demo:changed', update);
    window.removeEventListener('storage', update);
  });
  return computed(() => {
    void version.value;
    return orderPeriod(group());
  });
}
