import { computed, onScopeDispose, reactive, shallowRef, watch } from 'vue';
import type { OrderSnapshot } from '../types';
import type { SupportErrors } from '../delivery/types';
import { supportPhotos, validateSupport } from '../delivery/rules';
import { createSupportRequest, readSupportDraft, SupportValidationError } from '../delivery/service';
import { randomKey } from '../services/orders';
import { writeDemo } from '../../mocks/storage';
export function useOrderSupport(order: () => OrderSnapshot) {
  const draft = reactive(readSupportDraft(order()));
  const busy = shallowRef(false);
  const errors = shallowRef<SupportErrors>({});
  const error = shallowRef('');
  const notice = shallowRef('');
  const photos = computed(() =>
    supportPhotos(order()).map((photo) => ({
      title: photo.code,
      value: photo.id
    }))
  );
  let active = true;
  onScopeDispose(() => {
    active = false;
  });
  watch(draft, (value) => writeDemo('support:draft:' + order().id, { ...value }), { deep: true, immediate: true });
  async function submit() {
    if (busy.value) return;
    errors.value = validateSupport(draft, order());
    error.value = '';
    notice.value = '';
    if (Object.keys(errors.value).length) return;
    busy.value = true;
    try {
      const result = await createSupportRequest(order().accessKey, {
        ...draft
      });
      if (!active) return;
      notice.value = 'Обращение ' + result.number + ' сохранено в демонстрации. Настоящее сообщение не отправлялось.';
      draft.requestId = randomKey();
      draft.message = '';
      draft.photoId = '';
    } catch (cause) {
      if (!active) return;
      if (cause instanceof SupportValidationError) errors.value = cause.fields;
      error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить обращение. Повторите попытку.';
    } finally {
      if (active) busy.value = false;
    }
  }
  return { draft, busy, errors, error, notice, photos, submit };
}
