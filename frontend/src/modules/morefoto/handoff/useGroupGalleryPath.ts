import { shallowRef, watch } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { linksApi } from './links-api';
import { galleryPath, type GalleryStep } from './galleryPath';

/**
 * Path of one group to its gallery for screens outside «Ссылки и сроки». It is re-read whenever `changed` gives a new
 * value (the frames of the group changed); null — demo mode, a transmitted group or an unreadable link state.
 */
export function useGroupGalleryPath(groupId: () => string, changed: () => unknown) {
  const steps = shallowRef<GalleryStep[] | null>(null);
  let ticket = 0;
  watch(
    [groupId, changed],
    async ([id]) => {
      const current = ++ticket;
      if (!id || isMockApiEnabled) {
        steps.value = null;
        return;
      }
      try {
        const link = await linksApi.detail(id);
        if (current === ticket) steps.value = link.sentAt ? null : galleryPath(link);
      } catch {
        // The path is a hint: a failed read hides it instead of blocking the photo work.
        if (current === ticket) steps.value = null;
      }
    },
    { immediate: true }
  );
  return steps;
}
