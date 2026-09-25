import { isAxiosError } from 'axios';
import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import type { ConditionsCommand, ManagementErrors } from '../management/types';
import { conditionsApi, conditionsError, type ConditionsAttempt } from './api';
import { conditionsAttempt, conditionsCommandErrors, createConditionsCommand, type ConditionsEditorSource } from './conditions-command';

export type { ConditionsEditorSource } from './conditions-command';

interface Draft {
  command: ConditionsCommand;
  pending: ConditionsAttempt | null;
}
export function useConditionsEditor(load: () => Promise<ConditionsEditorSource | null>, saved: () => Promise<unknown>) {
  const auth = useAuthStore();
  const command = ref<ConditionsCommand | null>(null);
  const pending = shallowRef<ConditionsAttempt | null>(null);
  const busy = shallowRef(false);
  const error = shallowRef('');
  const errors = shallowRef<ManagementErrors>({});
  const restored = shallowRef(false);
  let key = '';
  let alive = true;
  function persist(): void {
    if (command.value && key) localStorage.setItem(key, JSON.stringify({ command: command.value, pending: pending.value }));
  }
  function open(source: ConditionsEditorSource): void {
    if (busy.value) return;
    // v2: drafts before E6 have no payment cost policy and would be rejected by the stricter contract.
    key = `morefoto:live:conditions-draft:v2:${auth.user?.id}:${source.groupId ?? 'global'}`;
    let draft: Draft | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null') as Draft | null;
    } catch {
      /* Invalid local draft is ignored. */
    }
    const valid =
      draft?.command?.kind === 'conditions' &&
      draft.command.groupId === source.groupId &&
      typeof draft.command.catalogRevision === 'number' &&
      typeof draft.command.requestId === 'string' &&
      (!!source.groupId ||
        (typeof draft.command.paymentCosts?.rate === 'string' && typeof draft.command.paymentCosts.savedRateBps === 'number'));
    command.value = valid && draft ? draft.command : createConditionsCommand(source);
    pending.value = valid && draft ? (draft.pending ?? null) : null;
    restored.value = !!valid;
    error.value = pending.value
      ? 'Ответ на сохранение не получен. Повторите сохранение, чтобы проверить результат без повторной записи.'
      : '';
    errors.value = {};
    persist();
  }
  function close(): void {
    if (!busy.value) command.value = null;
  }
  async function reset(): Promise<void> {
    if (busy.value || pending.value) return;
    busy.value = true;
    try {
      const source = await load();
      if (!alive || !source) return;
      command.value = createConditionsCommand(source);
      restored.value = false;
      error.value = '';
      errors.value = {};
      persist();
    } catch (cause) {
      if (alive) error.value = conditionsError(cause);
    } finally {
      if (alive) busy.value = false;
    }
  }
  async function save(): Promise<void> {
    if (!command.value || busy.value) return;
    errors.value = pending.value ? {} : conditionsCommandErrors(command.value);
    if (Object.keys(errors.value).length) {
      error.value = 'Проверьте выделенные поля.';
      await nextTick();
      document.querySelector<HTMLElement>('[data-testid="admin-dialog"] [aria-invalid="true"]')?.focus();
      return;
    }
    if (!pending.value) {
      pending.value = conditionsAttempt(command.value);
      persist();
    }
    busy.value = true;
    error.value = '';
    try {
      await conditionsApi.save(pending.value);
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        pending.value = null;
        await saved();
      }
    } catch (cause) {
      if (!alive) return;
      const status = isAxiosError(cause) ? cause.response?.status : undefined;
      if (status && status < 500) {
        pending.value = null;
        if (command.value) command.value.requestId = crypto.randomUUID().replace(/-/g, '');
      }
      error.value = pending.value
        ? 'Ответ на сохранение не получен. Повторите сохранение, чтобы проверить результат без повторной записи.'
        : conditionsError(cause);
      persist();
    } finally {
      if (alive) busy.value = false;
    }
  }
  watch(command, persist, { deep: true, flush: 'sync' });
  onScopeDispose(() => {
    alive = false;
  });
  return { command, pending, busy, error, errors, restored, open, close, reset, save };
}
