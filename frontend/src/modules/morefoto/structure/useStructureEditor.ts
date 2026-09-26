import { isAxiosError } from 'axios';
import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { structureApi, structureError } from './api';
import {
  createAttempt,
  fieldsFrom,
  refreshDraft,
  validateFields,
  type FieldErrors,
  type StructureDraft,
  type StructureItem,
  type StructureKind,
  type StructureScope
} from './model';
const uncertainMessage = 'Ответ на сохранение не получен. Повторите сохранение, чтобы проверить результат без создания дубликата.';
const newKey = () => crypto.randomUUID().replace(/-/g, '');
export function useStructureEditor(scope: StructureScope, saved: () => Promise<unknown>) {
  const auth = useAuthStore();
  const draft = ref<StructureDraft | null>(null),
    busy = shallowRef(false),
    restored = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<FieldErrors>({});
  let storageKey = '',
    alive = true;
  function persist(): boolean {
    if (!draft.value || !storageKey) return true;
    try {
      localStorage.setItem(storageKey, JSON.stringify(draft.value));
      return true;
    } catch {
      error.value = 'Не удалось сохранить черновик в браузере. Освободите место и повторите попытку.';
      return false;
    }
  }
  function keyFor(kind: StructureKind, parentId: string | null, id: string | null): string {
    return `morefoto:live:structure-draft:${auth.user?.id}:${kind}:${parentId ?? 'root'}:${id ?? 'new'}`;
  }
  /** A group opened outside its shoot page (the institution page) names its shoot explicitly. */
  function open(kind: StructureKind, item?: StructureItem, parent?: string): void {
    if (busy.value) return;
    const parentId = kind === 'institution' ? null : kind === 'shoot' ? (scope.institutionId ?? null) : (parent ?? scope.shootId ?? null);
    storageKey = keyFor(kind, parentId, item?.id ?? null);
    const fields = fieldsFrom(item);
    let stored: StructureDraft | null = null;
    try {
      stored = JSON.parse(localStorage.getItem(storageKey) ?? 'null') as StructureDraft | null;
    } catch {
      /* Invalid local drafts are ignored. */
    }
    const valid =
      stored &&
      stored.kind === kind &&
      stored.parentId === parentId &&
      stored.id === (item?.id ?? null) &&
      /^[a-f0-9]{32}$/.test(stored.key) &&
      [stored.fields, stored.base].every(
        (value) =>
          value &&
          typeof value.name === 'string' &&
          typeof value.date === 'string' &&
          typeof value.address === 'string' &&
          ['regular', 'staff'].includes(value.groupKind)
      ) &&
      (!stored.id || (Number.isInteger(stored.revision) && (stored.revision ?? 0) > 0)) &&
      (!stored.pending ||
        (stored.pending.key === stored.key &&
          stored.pending.path === createAttempt(stored).path &&
          stored.pending.method === createAttempt(stored).method));
    draft.value =
      valid && stored
        ? stored
        : {
            kind,
            parentId,
            id: item?.id ?? null,
            revision: item?.revision ?? null,
            fields,
            base: { ...fields },
            key: newKey(),
            pending: null
          };
    restored.value = !!valid;
    error.value = draft.value.pending ? uncertainMessage : '';
    errors.value = {};
    persist();
  }
  /** Moves a new, not yet sent group to another shoot; its local draft follows under the new key. */
  function setParent(parentId: string): void {
    const current = draft.value;
    if (!current || busy.value || current.id || current.pending || current.parentId === parentId) return;
    localStorage.removeItem(storageKey);
    // The key changes first: the synchronous draft watcher persists the change under it.
    storageKey = keyFor(current.kind, parentId, null);
    current.parentId = parentId;
  }
  function close(): void {
    if (!busy.value) draft.value = null;
  }
  async function refresh(): Promise<void> {
    if (!draft.value || busy.value || draft.value.pending) return;
    busy.value = true;
    try {
      const current = draft.value;
      if (current.id) {
        const source: StructureScope = {
          kind: current.kind,
          institutionId: current.kind === 'shoot' ? (current.parentId ?? undefined) : scope.institutionId,
          shootId: current.kind === 'group' ? (current.parentId ?? undefined) : undefined
        };
        let page = await structureApi.list(source, 1, 100),
          item = page.items.find((entry) => entry.id === current.id);
        for (let number = 2; !item && number <= page.meta.totalPages; number++) {
          page = await structureApi.list(source, number, 100);
          item = page.items.find((entry) => entry.id === current.id);
        }
        if (!alive) return;
        if (!item) {
          error.value = 'Запись больше недоступна. Закройте редактор и обновите список.';
          return;
        }
        draft.value = refreshDraft(current, item, newKey());
      } else {
        draft.value.key = newKey();
      }
      error.value = '';
      errors.value = {};
      restored.value = false;
      persist();
    } catch (cause) {
      if (alive) error.value = structureError(cause);
    } finally {
      if (alive) busy.value = false;
    }
  }
  async function save(): Promise<void> {
    if (!draft.value || busy.value) return;
    errors.value = draft.value.pending ? {} : validateFields(draft.value.kind, draft.value.fields);
    if (Object.keys(errors.value).length) {
      error.value = 'Проверьте выделенные поля.';
      await nextTick();
      document.querySelector<HTMLElement>('[data-testid="admin-dialog"] [aria-invalid="true"]')?.focus();
      return;
    }
    if (!draft.value.pending) draft.value.pending = createAttempt(draft.value);
    if (!persist()) return;
    const attempt = draft.value.pending,
      savingStorageKey = storageKey;
    busy.value = true;
    error.value = '';
    try {
      await structureApi.save(attempt);
      localStorage.removeItem(savingStorageKey);
      if (alive) {
        draft.value = null;
        await saved();
      }
    } catch (cause) {
      if (!alive || !draft.value) return;
      const status = isAxiosError(cause) ? cause.response?.status : undefined;
      // Authentication failures cannot disprove an earlier commit whose response was lost.
      if (status && status < 500 && status !== 401 && status !== 403) {
        draft.value.pending = null;
        draft.value.key = newKey();
      }
      error.value = draft.value.pending ? uncertainMessage : structureError(cause);
      persist();
    } finally {
      if (alive) busy.value = false;
    }
  }
  watch(draft, persist, { deep: true, flush: 'sync' });
  onScopeDispose(() => {
    alive = false;
  });
  return {
    draft,
    busy,
    restored,
    error,
    errors,
    open,
    setParent,
    close,
    refresh,
    save
  };
}
