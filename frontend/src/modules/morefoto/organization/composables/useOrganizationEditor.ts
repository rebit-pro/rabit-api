import { computed, onScopeDispose, shallowRef, watch, type MaybeRefOrGetter, toValue } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { readDemo, writeDemo } from '../../mocks/storage';
import { blankFields } from '../rules';
import { OrganizationValidationError, saveEntity } from '../service';
import type { EditorTarget, EntityFields, FieldErrors, OrganizationSnapshot, SaveCommand, SaveResult } from '../types';

export function useOrganizationEditor(snapshot: MaybeRefOrGetter<OrganizationSnapshot | null>, onSaved: (result: SaveResult) => void) {
  const auth = useAuthStore();
  const command = shallowRef<SaveCommand | null>(null);
  const busy = shallowRef(false);
  const error = shallowRef('');
  const errors = shallowRef<FieldErrors>({});
  const restored = shallowRef(false);
  let alive = true;
  let draftKey = '';
  const fields = computed(() => command.value?.fields ?? blankFields());
  const title = computed(() => {
    if (!command.value) return '';
    const labels = {
      institution: ['Новое учреждение', 'Редактировать учреждение'],
      shoot: ['Новая съёмка', 'Редактировать съёмку'],
      group: ['Новая группа', 'Редактировать группу']
    };
    return labels[command.value.kind][command.value.id ? 1 : 0];
  });
  function current(target: EditorTarget): SaveCommand {
    const data = toValue(snapshot);
    const initial = blankFields();
    let revision: number | null = null;
    if (target.kind === 'institution') {
      const record = data?.institutions.find((item) => item.id === target.id);
      if (record) {
        Object.assign(initial, { name: record.name, address: record.address, curatorId: record.curatorId, headId: record.headId });
        revision = record.revision;
      }
    } else if (target.kind === 'shoot') {
      const record = data?.shoots.find((item) => item.id === target.id);
      if (record) {
        Object.assign(initial, { name: record.name, date: record.date ?? '' });
        revision = record.revision;
      }
    } else {
      const record = data?.groups.find((item) => item.id === target.id);
      if (record) {
        Object.assign(initial, { name: record.name, teacherId: record.teacherId, groupKind: record.kind });
        revision = record.revision;
      }
    }
    return { ...target, fields: initial, revision, requestId: crypto.randomUUID() };
  }
  function open(target: EditorTarget) {
    if (busy.value) return;
    draftKey = 'organization:draft:' + auth.user?.id + ':' + target.kind + ':' + (target.parentId ?? '') + ':' + (target.id ?? 'new');
    const draft = readDemo<SaveCommand | null>(draftKey, null);
    command.value = draft?.kind === target.kind && draft.id === target.id && draft.parentId === target.parentId ? draft : current(target);
    restored.value = command.value === draft;
    error.value = '';
    errors.value = {};
  }
  function patch(value: Partial<EntityFields>) {
    if (!command.value || busy.value) return;
    command.value = { ...command.value, fields: { ...fields.value, ...value } };
    errors.value = {};
    writeDemo(draftKey, command.value);
  }
  function reset() {
    if (!command.value || busy.value) return;
    command.value = current({ kind: command.value.kind, id: command.value.id, parentId: command.value.parentId });
    writeDemo(draftKey, null);
    restored.value = false;
    error.value = '';
    errors.value = {};
  }
  function close() {
    if (!busy.value) command.value = null;
  }
  async function save() {
    if (!command.value || busy.value) return;
    const token = auth.getAccessToken();
    if (!token) {
      error.value = 'Сессия истекла. Войдите снова.';
      return;
    }
    busy.value = true;
    error.value = '';
    errors.value = {};
    const pending = structuredClone(command.value);
    writeDemo(draftKey, pending);
    try {
      const result = await saveEntity(token, pending);
      writeDemo(draftKey, null);
      if (alive) {
        command.value = null;
        onSaved(result);
      }
    } catch (reason) {
      if (!alive) return;
      if (reason instanceof OrganizationValidationError) errors.value = reason.errors;
      error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить. Черновик сохранён.';
    } finally {
      if (alive) busy.value = false;
    }
  }
  watch(
    () => auth.token,
    () => {
      command.value = null;
    }
  );
  onScopeDispose(() => {
    alive = false;
  });
  return { command, fields, busy, error, errors, restored, title, open, patch, reset, close, save };
}
