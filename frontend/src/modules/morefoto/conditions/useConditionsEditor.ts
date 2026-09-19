import { isAxiosError } from 'axios';
import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { moneyInputValue } from '../ui/field-values';
import type { ConditionsCommand, ManagementErrors } from '../management/types';
import { conditionsApi, conditionsError, type ConditionsAttempt, type ConditionsSnapshot } from './api';

export interface ConditionsEditorSource {
  snapshot: ConditionsSnapshot;
  groupId: string | null;
  institutionId?: string | null;
  shootId?: string | null;
}
interface Draft {
  command: ConditionsCommand;
  pending: ConditionsAttempt | null;
}
function createCommand(source: ConditionsEditorSource): ConditionsCommand {
  const value = source.snapshot;
  return {
    kind: 'conditions',
    requestId: crypto.randomUUID().replace(/-/g, ''),
    revision: value.revision,
    catalogRevision: value.catalogRevision,
    conditionsRevision: value.conditionsRevision ?? value.revision,
    groupId: source.groupId,
    institutionId: source.institutionId ?? null,
    shootId: source.shootId ?? null,
    inherit: value.inherit ?? false,
    products: value.products.map((product) => ({
      id: product.id,
      name: product.name,
      kind: product.kind,
      price: String(product.price / 100),
      active: product.active,
      staffDiscount: product.staffDiscount
    })),
    giftEnabled: value.giftThreshold > 0,
    giftThreshold: String(value.giftThreshold / 100),
    giftForStaff: value.giftForStaff
  };
}
function validate(command: ConditionsCommand): ManagementErrors {
  const errors: ManagementErrors = {};
  for (const product of command.products) {
    const price = moneyInputValue(product.price);
    if (price === null || price > 2147483647)
      errors['price:' + product.id] = 'Цена: от 0 до 21 474 836,47 ₽, до двух знаков после запятой.';
  }
  const threshold = moneyInputValue(command.giftThreshold);
  if (command.giftEnabled && (threshold === null || threshold < 1 || threshold > 2147483647))
    errors.giftThreshold = 'Порог: от 0,01 до 21 474 836,47 ₽.';
  if (command.giftEnabled && command.products.filter((product) => product.kind === 'bundle' && product.active).length !== 1)
    errors.products = 'Для подарка должен быть включён ровно один электронный комплект.';
  return errors;
}
function attempt(command: ConditionsCommand): ConditionsAttempt {
  const body: ConditionsAttempt['body'] = {
    revision: command.revision,
    catalogRevision: command.catalogRevision,
    products: command.products.map((product) => ({
      id: product.id,
      price: moneyInputValue(product.price)!,
      active: product.active,
      staffDiscount: product.staffDiscount
    })),
    giftEnabled: command.giftEnabled,
    giftThreshold: command.giftEnabled ? moneyInputValue(command.giftThreshold)! : 0,
    giftForStaff: command.giftEnabled && command.giftForStaff
  };
  if (command.groupId) {
    body.conditionsRevision = command.conditionsRevision;
    body.inherit = command.inherit;
  }
  return { groupId: command.groupId, key: command.requestId, body };
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
    key = `morefoto:live:conditions-draft:${auth.user?.id}:${source.groupId ?? 'global'}`;
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
      typeof draft.command.requestId === 'string';
    command.value = valid && draft ? draft.command : createCommand(source);
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
      command.value = createCommand(source);
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
    errors.value = pending.value ? {} : validate(command.value);
    if (Object.keys(errors.value).length) {
      error.value = 'Проверьте выделенные поля.';
      await nextTick();
      document.querySelector<HTMLElement>('[data-testid="admin-dialog"] [aria-invalid="true"]')?.focus();
      return;
    }
    if (!pending.value) {
      pending.value = attempt(command.value);
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
