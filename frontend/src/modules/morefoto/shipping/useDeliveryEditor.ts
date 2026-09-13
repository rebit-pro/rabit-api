import { ref, shallowRef, watch, nextTick, onScopeDispose } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { saveDelivery, DeliveryValidationError } from './service';
import type { DeliveryCommand, DeliveryErrors } from './types';
export function useDeliveryEditor(saved: () => void) {
  const auth = useAuthStore(),
    command = ref<DeliveryCommand | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<DeliveryErrors>({}),
    restored = shallowRef(false);
  let factory: (() => DeliveryCommand) | null = null,
    key = '',
    alive = true;
  onScopeDispose(() => {
    alive = false;
  });
  function open(create: () => DeliveryCommand) {
    if (busy.value) return;
    factory = create;
    const fresh = create();
    key = `morefoto:demo:delivery-draft:${auth.user?.id}:${fresh.targetId}:${fresh.kind}`;
    let draft: DeliveryCommand | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {
      draft = null;
    }
    const canRestore = draft?.kind === fresh.kind && draft.targetId === fresh.targetId;
    command.value = canRestore ? draft : fresh;
    restored.value = !!canRestore;
    error.value = '';
    errors.value = {};
  }
  function reset() {
    if (factory && !busy.value) {
      command.value = factory();
      error.value = '';
      errors.value = {};
      restored.value = false;
    }
  }
  function close() {
    if (!busy.value) command.value = null;
  }
  watch(
    command,
    (value) => {
      if (value && key) localStorage.setItem(key, JSON.stringify(value));
    },
    { deep: true, flush: 'sync' }
  );
  async function save() {
    if (!command.value || busy.value) return;
    busy.value = true;
    error.value = '';
    errors.value = {};
    try {
      await saveDelivery(auth.getAccessToken() ?? '', JSON.parse(JSON.stringify(command.value)));
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        saved();
      }
    } catch (e) {
      if (alive) {
        errors.value = e instanceof DeliveryValidationError ? e.errors : {};
        error.value = e instanceof Error ? e.message : 'Не удалось сохранить.';
        await nextTick();
        document.querySelector<HTMLElement>('[role=dialog] [aria-invalid=true], .management-error')?.focus();
      }
    } finally {
      if (alive) busy.value = false;
    }
  }
  return { command, busy, error, errors, restored, open, reset, close, save };
}
