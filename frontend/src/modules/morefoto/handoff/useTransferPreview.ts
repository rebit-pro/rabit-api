import { shallowRef, watch, type Ref } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { staffRequestError, staffRequestsApi } from './api';
import { reviewRequest, transferPreviewFromServer } from './rules';
import type { HandoffWorkspace, RequestPreview, ReviewPhoto, StaffRequest } from './types';

/** Проверка льготного переноса выбранной заявки: демо считает её локально, live заново читает HND-10 при каждой смене заявки. */
export function useTransferPreview(data: Ref<HandoffWorkspace | null>, selected: Ref<StaffRequest | undefined>, reviewing: Ref<boolean>) {
  const preview = shallowRef<RequestPreview<ReviewPhoto> | null>(null);
  const error = shallowRef('');
  let ticket = 0;
  async function refresh(): Promise<void> {
    const id = ++ticket,
      request = selected.value,
      workspace = data.value;
    preview.value = null;
    error.value = '';
    if (!request || !workspace || !reviewing.value || request.status !== 'submitted') return;
    try {
      const next = isMockApiEnabled
        ? reviewRequest(request, workspace.groups, { photos: workspace.photos, covers: {} })
        : transferPreviewFromServer(await staffRequestsApi.transferPreview(request.id), request, workspace.role === 'organizer');
      if (id === ticket) preview.value = next;
    } catch (cause) {
      if (id === ticket)
        error.value = isMockApiEnabled
          ? cause instanceof Error
            ? cause.message
            : 'Набор недоступен.'
          : staffRequestError(cause, 'confirm');
    }
  }
  watch([data, () => selected.value?.id, () => selected.value?.revision, () => selected.value?.status, reviewing], () => void refresh(), {
    immediate: true
  });
  return { preview, error, refresh };
}
