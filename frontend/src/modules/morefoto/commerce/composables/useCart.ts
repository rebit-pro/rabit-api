import { computed, onMounted, onScopeDispose, shallowRef, toValue, type MaybeRefOrGetter } from 'vue';
import type { GallerySnapshot } from '../../gallery/types';
import { demoChangedEvent } from '../../mocks/storage';
import { quoteCart } from '../services/cart';
import { getCatalog } from '../mocks/catalog';
export function useCart(gallery: MaybeRefOrGetter<GallerySnapshot>) {
  const revision = shallowRef(0);
  const refresh = () => {
    revision.value++;
  };
  onMounted(() => {
    window.addEventListener(demoChangedEvent, refresh);
    window.addEventListener('storage', refresh);
  });
  onScopeDispose(() => {
    window.removeEventListener(demoChangedEvent, refresh);
    window.removeEventListener('storage', refresh);
  });
  const quote = computed(() => {
    void revision.value;
    return quoteCart(toValue(gallery));
  });
  const catalog = computed(() => {
    void revision.value;
    return getCatalog(toValue(gallery).groupId);
  });
  return { quote, catalog };
}
