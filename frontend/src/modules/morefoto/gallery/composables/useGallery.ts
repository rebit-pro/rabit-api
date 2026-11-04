import { organizationChangedEvent, organizationKey } from '../../organization/repository';
import { photosChangedEvent, photoStateKey } from '../../photos/repository';
import { computed, nextTick, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { GalleryUnavailableError, loadGallery } from '../services/gallery';
import type { GalleryPhoto, GallerySnapshot } from '../types';

export function useGallery() {
  const route = useRoute();
  const router = useRouter();
  const gallery = shallowRef<GallerySnapshot | null>(null);
  const loading = shallowRef(true);
  const error = shallowRef('');
  const unavailable = shallowRef(false);
  let requestId = 0;
  let lastRouteToken: string | null = null;
  let scheduledRefresh: number | null = null;
  let returnFocus: HTMLElement | null = null;

  async function reload() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    unavailable.value = false;
    const requested = String(route.params.token ?? '');
    if (requested !== lastRouteToken) {
      gallery.value = null;
      lastRouteToken = requested;
    }
    try {
      const result = await loadGallery(requested);
      if (id !== requestId) return;
      gallery.value = result;
    } catch (cause) {
      if (id !== requestId) return;
      unavailable.value = cause instanceof GalleryUnavailableError;
      error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить галерею.';
    } finally {
      if (id === requestId) loading.value = false;
    }
  }
  watch(() => route.params.token, reload, { immediate: true });
  watch(
    () => gallery.value?.state,
    (state) => {
      if (scheduledRefresh !== null) window.clearInterval(scheduledRefresh);
      scheduledRefresh = state === 'scheduled' ? window.setInterval(() => void reload(), 30000) : null;
    }
  );
  const changed = () => {
    void reload();
  };
  const storageChanged = (event: StorageEvent) => {
    if (event.key === null || event.key === 'morefoto:demo:' + organizationKey || event.key === 'morefoto:demo:' + photoStateKey) changed();
  };
  window.addEventListener(photosChangedEvent, changed);
  window.addEventListener(organizationChangedEvent, changed);
  window.addEventListener('storage', storageChanged);
  onScopeDispose(() => {
    window.removeEventListener(photosChangedEvent, changed);
    window.removeEventListener(organizationChangedEvent, changed);
    window.removeEventListener('storage', storageChanged);
    if (scheduledRefresh !== null) window.clearInterval(scheduledRefresh);
    requestId++;
  });
  const code = computed(() => (typeof route.query.child === 'string' ? route.query.child.trim().toUpperCase() : ''));
  const allPhotos = computed(() => gallery.value?.children.flatMap((child) => child.photos) ?? []);
  const photos = computed(() => allPhotos.value.filter((photo) => photo.code.includes(code.value)));
  const activeIndex = computed(() => photos.value.findIndex((photo) => (photo.assignmentId ?? photo.id) === route.query.photo));
  const activePhoto = computed(() => photos.value[activeIndex.value] ?? null);
  const missingPhoto = computed(() => !!route.query.photo && !activePhoto.value && !!gallery.value);

  function selectCode(value: string) {
    void router.replace({
      query: { child: value.trim().toUpperCase() || undefined }
    });
  }
  function openPhoto(photo: GalleryPhoto) {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    void router.push({
      query: { ...route.query, photo: photo.assignmentId ?? photo.id }
    });
  }
  function closePhoto() {
    const query = { ...route.query };
    delete query.photo;
    void router.replace({ query });
  }
  function stepPhoto(direction: number) {
    const photo = photos.value[activeIndex.value + direction];
    if (photo)
      void router.replace({
        query: { ...route.query, photo: photo.assignmentId ?? photo.id }
      });
  }
  watch(activePhoto, async (current, previous) => {
    if (!current && previous) {
      await nextTick();
      returnFocus?.focus({ preventScroll: true });
    }
  });
  return {
    gallery,
    loading,
    error,
    unavailable,
    reload,
    code,
    photos,
    allPhotos,
    activePhoto,
    activeIndex,
    missingPhoto,
    selectCode,
    openPhoto,
    closePhoto,
    stepPhoto
  };
}
