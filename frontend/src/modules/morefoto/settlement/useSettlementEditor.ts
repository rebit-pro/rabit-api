import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import type { SaleCommand, SaleErrors } from './types';
import { SaleValidationError, saveSettlement } from './service';
export function useSettlementEditor(saved: () => void) {
  const auth = useAuthStore(),
    command = ref<SaleCommand | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<SaleErrors>({}),
    restored = shallowRef(false);
  let factory: (() => SaleCommand) | null = null,
    key = '',
    alive = true;
  onScopeDispose(() => {
    alive = false;
  });
  function open(create: () => SaleCommand, context: string) {
    if (busy.value) return;
    factory = create;
    key = 'morefoto:demo:settlement-draft:' + auth.user?.id + ':' + context;
    const fresh = create();
    let draft: SaleCommand | null = null;
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
      try {
        command.value = factory();
        restored.value = false;
        error.value = '';
        errors.value = {};
      } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'Не удалось обновить данные.';
      }
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
      const copy = structuredClone(JSON.parse(JSON.stringify(command.value))) as SaleCommand;
      await saveSettlement(auth.getAccessToken() ?? '', copy);
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        saved();
      }
    } catch (cause) {
      if (alive) {
        error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить. Черновик сохранён.';
        if (cause instanceof SaleValidationError) errors.value = cause.errors;
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
