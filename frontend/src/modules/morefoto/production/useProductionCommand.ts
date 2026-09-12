import { ref, shallowRef, watch, nextTick, onScopeDispose } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { saveProduction } from './service';
import type { ProductionCommand } from './types';
export function useProductionCommand(saved: () => void) {
  const auth = useAuthStore(),
    command = ref<ProductionCommand | null>(null),
    busy = shallowRef(false),
    error = shallowRef(''),
    restored = shallowRef(false);
  let factory: (() => ProductionCommand) | null = null,
    key = '',
    alive = true;
  onScopeDispose(() => {
    alive = false;
  });
  function open(create: () => ProductionCommand) {
    if (busy.value) return;
    factory = create;
    const fresh = create();
    key = `morefoto:demo:production-draft:${auth.user?.id}:${fresh.groupId}:${fresh.kind}:${fresh.orderId ?? ''}`;
    let draft: ProductionCommand | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null');
    } catch {
      draft = null;
    }
    const canRestore = draft?.kind === fresh.kind && draft.groupId === fresh.groupId && draft.orderId === fresh.orderId;
    command.value = canRestore ? draft : fresh;
    restored.value = !!canRestore;
    error.value = '';
  }
  function reset() {
    if (factory && !busy.value) {
      command.value = factory();
      error.value = '';
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
    try {
      await saveProduction(auth.getAccessToken() ?? '', JSON.parse(JSON.stringify(command.value)));
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        saved();
      }
    } catch (e) {
      if (alive) {
        error.value = e instanceof Error ? e.message : 'Не удалось сохранить.';
        await nextTick();
        document.querySelector<HTMLElement>('.management-error')?.focus();
      }
    } finally {
      if (alive) busy.value = false;
    }
  }
  return { command, busy, error, restored, open, reset, close, save };
}
