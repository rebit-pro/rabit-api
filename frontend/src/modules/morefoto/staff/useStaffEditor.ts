import { computed, ref, shallowRef } from 'vue';
import { staffApi, staffError } from './api';
import type { AssignmentOptions, StaffDraft, StaffSummary } from './model';
function requestId(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
}
export function useStaffEditor(saved: () => Promise<void>) {
  // StaffFields edits nested fields through v-model, so the draft must be deeply reactive.
  const draft = ref<StaffDraft | null>(null);
  const options = shallowRef<AssignmentOptions | null>(null);
  const busy = shallowRef(false);
  const error = shallowRef('');
  const replacements = computed(() => {
    if (!draft.value || !options.value) return [];
    const ids = new Set<number>();
    if (draft.value.role === 'teacher')
      for (const group of options.value.groups)
        if (draft.value.groupIds.includes(group.id) && group.teacherId && group.teacherId !== draft.value.id) ids.add(group.teacherId);
    if (draft.value.role === 'curator' || draft.value.role === 'head')
      for (const institution of options.value.institutions) {
        const occupant = draft.value.role === 'curator' ? institution.curatorId : institution.headId;
        if (draft.value.institutionIds.includes(institution.id) && occupant && occupant !== draft.value.id) ids.add(occupant);
      }
    return [...ids].map((id) => options.value?.staff.find((item) => item.id === id)?.name ?? 'сотрудник #' + id);
  });
  async function open(item?: StaffSummary): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
      const loadedOptions = await staffApi.options();
      options.value = loadedOptions;
      if (item) {
        const detail = await staffApi.detail(item.id);
        draft.value = {
          id: detail.id,
          revision: detail.revision,
          requestId: requestId(),
          name: detail.name,
          email: detail.email,
          role: detail.role,
          active: detail.active,
          institutionIds: detail.institutionIds,
          groupIds: detail.groupIds,
          replaceAssignments: false,
          reason: '',
          assignmentSignature: loadedOptions.assignmentSignature
        };
      } else {
        draft.value = {
          id: null,
          revision: null,
          requestId: requestId(),
          name: '',
          email: '',
          role: 'teacher',
          active: true,
          institutionIds: [],
          groupIds: [],
          replaceAssignments: false,
          reason: '',
          assignmentSignature: loadedOptions.assignmentSignature
        };
      }
    } catch (cause) {
      error.value = staffError(cause);
    } finally {
      busy.value = false;
    }
  }
  function close(): void {
    if (!busy.value) draft.value = null;
  }
  async function refresh(): Promise<void> {
    if (draft.value) await open(draft.value.id === null ? undefined : ({ id: draft.value.id } as StaffSummary));
  }
  async function save(): Promise<void> {
    if (!draft.value) return;
    if (!draft.value.name.trim() || !draft.value.email.includes('@')) {
      error.value = 'Укажите имя и корректный email.';
      return;
    }
    if (replacements.value.length && (!draft.value.replaceAssignments || !draft.value.reason.trim())) {
      error.value = 'Подтвердите замену ответственных и укажите причину.';
      return;
    }
    busy.value = true;
    error.value = '';
    try {
      await staffApi.save(draft.value);
      draft.value = null;
      await saved();
    } catch (cause) {
      error.value = staffError(cause);
    } finally {
      busy.value = false;
    }
  }
  return { draft, options, busy, error, replacements, open, close, refresh, save };
}
