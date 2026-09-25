import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import type { HandoffCommand, HandoffErrors } from './types';
import { HandoffValidationError } from './scope';
import { saveLink } from './links-service';
import { saveStaffRequest } from './requests-service';
/**
 * Draft, submit and errors of a handoff form. `refresh` reads the workspace from the server and reports success: the
 * reset button and a close after a failed save use it, so a form is never rebuilt from a stale local copy (#28).
 */
export function useHandoffEditor(saved: () => void, refresh?: () => Promise<boolean>) {
  const auth = useAuthStore(),
    command = ref<HandoffCommand | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<HandoffErrors>({}),
    restored = shallowRef(false),
    /** The stored draft was written for another server revision and was replaced by the current data. */
    stale = shallowRef(false);
  let factory: (() => HandoffCommand) | null = null,
    key = '',
    failed = false,
    alive = true;
  onScopeDispose(() => {
    alive = false;
  });
  function open(create: () => HandoffCommand, context: string) {
    if (busy.value) return;
    factory = create;
    key = 'morefoto:' + (isMockApiEnabled ? 'demo' : 'live') + ':handoff-draft:' + auth.user?.id + ':' + context;
    const fresh = create();
    let draft: HandoffCommand | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {
      draft = null;
    }
    // The draft keeps its Idempotency-Key and body for a safe repeat only while the server revision is unchanged.
    const usable = draft?.kind === fresh.kind && draft.revision === fresh.revision;
    stale.value = draft?.kind === fresh.kind && !usable;
    command.value = usable ? draft : fresh;
    restored.value = usable;
    failed = false;
    error.value = '';
    errors.value = {};
  }
  /** «Загрузить актуальные данные»: reads the server first, then rebuilds the form with a new key. */
  async function reset() {
    if (!factory || busy.value) return;
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
      if (refresh && !(await refresh())) {
        if (alive) error.value = 'Не удалось загрузить актуальные данные. Проверьте соединение и повторите.';
        return;
      }
      if (!alive || !command.value) return;
      command.value = factory();
      restored.value = false;
      stale.value = false;
      failed = false;
    } finally {
      if (alive) busy.value = false;
    }
  }
  watch(
    command,
    (value) => {
      if (value && key) localStorage.setItem(key, JSON.stringify(value));
    },
    { deep: true, flush: 'sync' }
  );
  function close() {
    if (busy.value) return;
    command.value = null;
    // After a failed save the workspace may be behind the server; the next open compares the draft with fresh data.
    if (failed && refresh) void refresh();
    failed = false;
  }
  async function save() {
    if (!command.value || busy.value) return;
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
      const copy = structuredClone(JSON.parse(JSON.stringify(command.value))) as HandoffCommand;
      if (copy.kind === 'link') await saveLink(auth.getAccessToken() ?? '', copy);
      else await saveStaffRequest(auth.getAccessToken() ?? '', copy);
      localStorage.removeItem(key);
      failed = false;
      if (alive) {
        command.value = null;
        saved();
      }
    } catch (cause) {
      if (alive) {
        error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить. Черновик сохранён.';
        if (cause instanceof HandoffValidationError) errors.value = cause.errors;
        else failed = true;
        busy.value = false;
        await nextTick();
        (
          document.querySelector<HTMLElement>('[data-testid="admin-dialog"] [aria-invalid="true"]') ??
          document.querySelector<HTMLElement>('[data-testid="admin-dialog"] .management-error')
        )?.focus();
      }
    } finally {
      if (alive) busy.value = false;
    }
  }
  return { command, busy, error, errors, restored, stale, open, reset, close, save };
}
