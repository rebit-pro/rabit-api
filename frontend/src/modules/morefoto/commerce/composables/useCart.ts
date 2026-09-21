import { isMockApiEnabled } from '@/mocks/config';
import { liveState } from '../services/storefront';
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
    return isMockApiEnabled ? getCatalog(toValue(gallery).groupId) : liveState(toValue(gallery).groupId).catalog;
  });
  const calculationError = computed(() => (isMockApiEnabled ? '' : liveState(toValue(gallery).groupId).error));
  const hasQuote = computed(() => isMockApiEnabled || liveState(toValue(gallery).groupId).quote !== null);
  return { quote, catalog, calculationError, hasQuote };
}
