import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import type { ManagementCommand, ManagementErrors } from './types';
import { ManagementValidationError, saveManagement } from './service';
export function useManagementEditor(saved: () => void) {
  const auth = useAuthStore(),
    command = ref<ManagementCommand | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<ManagementErrors>({}),
    restored = shallowRef(false);
  let factory: (() => ManagementCommand) | null = null,
    key = '',
    alive = true;
  onScopeDispose(() => {
    alive = false;
  });
  function open(create: () => ManagementCommand, context: string) {
    if (busy.value) return;
    factory = create;
    key = 'morefoto:demo:management-draft:' + auth.user?.id + ':' + context;
    const fresh = create();
    let draft: ManagementCommand | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {
      draft = null;
    }
    command.value = draft?.kind === fresh.kind ? draft : fresh;
    restored.value = !!draft;
    error.value = '';
    errors.value = {};
  }
  function reset() {
    if (factory) {
      command.value = factory();
      restored.value = false;
      error.value = '';
      errors.value = {};
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
    if (!busy.value) command.value = null;
  }
  async function save() {
    if (!command.value || busy.value) return;
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
      await saveManagement(auth.getAccessToken() ?? '', structuredClone(JSON.parse(JSON.stringify(command.value))));
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        saved();
      }
    } catch (cause) {
      if (alive) {
        error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить. Черновик сохранён.';
        if (cause instanceof ManagementValidationError) errors.value = cause.errors;
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
  return { command, busy, error, errors, restored, open, reset, close, save };
}
