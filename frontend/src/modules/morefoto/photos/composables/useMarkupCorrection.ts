import { computed, shallowRef, watch, type Ref } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { linkError, linksApi, markupCorrectionApi, type LiveLinkDetail } from '../../handoff/links-api';
import { idempotencyKey } from '../api';

/**
 * D4: a transmitted group without orders is corrected in a session that pauses its sales. The link card says whether
 * the session may start and gives the revision and the signature of the commands; the server repeats every check.
 */
export function useMarkupCorrection(groupId: Ref<string | undefined>) {
  const link = shallowRef<LiveLinkDetail | null>(null);
  const busy = shallowRef(false);
  const error = shallowRef('');
  let ticket = 0;
  async function load(): Promise<void> {
    const id = groupId.value;
    const current = ++ticket;
    if (isMockApiEnabled || !id) {
      link.value = null;
      return;
    }
    try {
      const detail = await linksApi.detail(id);
      if (current === ticket) link.value = detail;
    } catch (cause) {
      if (current !== ticket) return;
      link.value = null;
      error.value = linkError(cause);
    }
  }
  async function run(command: (detail: LiveLinkDetail) => Promise<void>): Promise<boolean> {
    const id = groupId.value;
    if (!id || busy.value) return false;
    busy.value = true;
    error.value = '';
    try {
      // A fresh card gives the current revision and the signature of the frames the staff member has just checked.
      await command(await linksApi.detail(id));
      return true;
    } catch (cause) {
      // A lost answer may still have been saved: the card reloaded below shows the actual state.
      error.value = linkError(cause);
      return false;
    } finally {
      busy.value = false;
      await load();
    }
  }
  watch(
    groupId,
    () => {
      error.value = '';
      void load();
    },
    { immediate: true }
  );
  return {
    busy,
    error,
    correcting: computed(() => !!link.value?.markupCorrectionAt),
    correctable: computed(() => !!link.value?.markupCorrectable),
    start: () => run((detail) => markupCorrectionApi.start(detail.groupId, detail.revision, idempotencyKey())),
    finish: () => run((detail) => markupCorrectionApi.finish(detail.groupId, detail.revision, detail.signature, idempotencyKey()))
  };
}
