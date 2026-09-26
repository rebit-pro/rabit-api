<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import UiBulkNotice from '../../ui/components/UiBulkNotice.vue';
import { bulkNotice, type BulkNotice } from '../../ui/removal';
import type { UiTableColumn, UiTableRow } from '../../ui/table-types';
import InstitutionOverview from './InstitutionOverview.vue';
import StructureWidget from './StructureWidget.vue';
import StructureRemoveDialog from './StructureRemoveDialog.vue';
import StructureFields from './StructureFields.vue';
import { useInstitutionPage } from '../useInstitutionPage';
import { useStructureEditor } from '../useStructureEditor';
import { structureApi, structureError, structureRemovalError } from '../api';
import { defaultShootId, type Group, type Shoot } from '../model';
const props = defineProps<{ institutionId: string }>();
const auth = useAuthStore();
const router = useRouter();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, busy: removing, error, reload, remove } = useInstitutionPage(props.institutionId);
const notice = shallowRef('');
const result = shallowRef<BulkNotice | null>(null);
const editor = useStructureEditor({ kind: 'shoot', institutionId: props.institutionId }, async () => {
  notice.value = 'Изменения сохранены.';
  await reload();
});
const { draft, busy, restored, error: saveError, errors, close, refresh, save } = editor;
// All shoots of the institution, read when a group dialog opens: the group may move to any of them.
const shoots = shallowRef<Shoot[]>([]);
const shootsLoading = shallowRef(false);
const actionError = shallowRef('');
const shootItems = computed(() =>
  shoots.value.map((shoot) => ({
    title: shoot.name + (shoot.date ? ' · ' + shoot.date.split('-').reverse().join('.') : ''),
    value: shoot.id
  }))
);
const disabled = computed(() => loading.value || removing.value || !snapshot.value || !!error.value);
const title = computed(() => {
  if (!draft.value) return '';
  if (draft.value.kind === 'institution') return 'Редактирование учреждения';
  if (draft.value.kind === 'group') return draft.value.id ? 'Редактирование группы' : 'Новая группа';
  return draft.value.id ? 'Редактирование съёмки' : 'Новая съёмка';
});
function clearNotices(): void {
  notice.value = '';
  actionError.value = '';
  result.value = null;
}
function editShoot(id?: string): void {
  if (canManage.value && !disabled.value) {
    clearNotices();
    editor.open(
      'shoot',
      snapshot.value?.shoots.items.find((shoot) => shoot.id === id)
    );
  }
}
async function editGroup(id?: string): Promise<void> {
  if (!canManage.value || disabled.value || shootsLoading.value) return;
  clearNotices();
  shootsLoading.value = true;
  try {
    shoots.value = await structureApi.shoots(props.institutionId);
  } catch (cause) {
    actionError.value = structureError(cause);
    return;
  } finally {
    shootsLoading.value = false;
  }
  const group = snapshot.value?.groups.items.find((item) => item.id === id);
  editor.open('group', group, group?.shootId ?? defaultShootId(shoots.value));
}
function editInstitution(): void {
  if (canManage.value && !disabled.value && snapshot.value) {
    clearNotices();
    editor.open('institution', snapshot.value);
  }
}
const shootColumns: UiTableColumn[] = [
  { key: 'name', label: 'Съёмка', sortable: true, primary: true, width: '52%' },
  { key: 'date', label: 'Дата', type: 'date', sortable: true, mobile: true, width: '18%' },
  { key: 'groups', label: 'Групп', type: 'number', sortable: true, mobile: true, width: '10%' }
];
const groupColumns: UiTableColumn[] = [
  { key: 'name', label: 'Группа', sortable: true, primary: true, width: '30%' },
  { key: 'shoot', label: 'Съёмка', sortable: true, mobile: true, width: '25%' },
  {
    key: 'status',
    label: 'Приём',
    type: 'status',
    sortable: true,
    mobile: true,
    width: '17%',
    statuses: {
      preparing: { label: 'Готовится', tone: 'info' },
      open: { label: 'Приём открыт', tone: 'success' },
      closed: { label: 'Приём завершён', tone: 'neutral' }
    }
  },
  { key: 'closesAt', label: 'Приём до', type: 'date', sortable: true, mobile: true }
];
const moscowDate = new Intl.DateTimeFormat('ru-RU', { timeZone: 'Europe/Moscow' });
const shootNames = computed(() => new Map((snapshot.value?.shoots.items ?? []).map((shoot) => [shoot.id, shoot.name])));
const groupsById = computed(() => new Map((snapshot.value?.groups.items ?? []).map((group) => [group.id, group])));
const shootRows = computed<UiTableRow[]>(() =>
  (snapshot.value?.shoots.items ?? []).map((shoot) => ({
    id: shoot.id,
    name: shoot.name,
    date: shoot.date,
    groups: shoot.groupCount ?? (snapshot.value?.groups.items ?? []).filter((group) => group.shootId === shoot.id).length
  }))
);
const shootFilter = shallowRef<string | null>(null);
// A removed shoot cannot stay the filter of the groups.
const activeShootFilter = computed(() => (shootFilter.value && shootNames.value.has(shootFilter.value) ? shootFilter.value : null));
const groupRows = computed<UiTableRow[]>(() =>
  (snapshot.value?.groups.items ?? [])
    .filter((group) => !activeShootFilter.value || group.shootId === activeShootFilter.value)
    .map((group) => ({
      id: group.id,
      name: group.name,
      shoot: group.shootName ?? shootNames.value.get(group.shootId) ?? '',
      status: group.status,
      closesAt: group.closesAt
    }))
);
const shootFilterItems = computed(() => [
  { title: 'Все съёмки', value: null },
  ...(snapshot.value?.shoots.items ?? []).map((shoot) => ({ title: shoot.name, value: shoot.id }))
]);
function firstPage(list: { items: unknown[]; meta: { total: number } } | undefined): string {
  return list && list.meta.total > list.items.length ? 'Показаны первые ' + list.items.length + ' из ' + list.meta.total + '.' : '';
}
function shootPath(shootId: string, tail = ''): string {
  return '/cabinet/institutions/' + encodeURIComponent(props.institutionId) + '/shoots/' + encodeURIComponent(shootId) + tail;
}
function group(id: string): Group | undefined {
  return groupsById.value.get(id);
}
const groupsEmptyText = computed(() =>
  !canManage.value
    ? 'В съёмках учреждения пока нет групп.'
    : snapshot.value?.shoots.meta.total
      ? 'Добавьте группу: у каждой будут свои фотографии и ссылка для родителей.'
      : 'Сначала добавьте съёмку — группа создаётся внутри неё.'
);
const removal = shallowRef<{ kind: 'institution' | 'shoot' | 'group'; ids: string[]; names: string[] } | null>(null);
function askRemove(kind: 'shoot' | 'group', ids: string[]): void {
  const items: { id: string; name: string }[] = (kind === 'shoot' ? snapshot.value?.shoots.items : snapshot.value?.groups.items) ?? [];
  const targets = items.filter((item) => ids.includes(item.id));
  if (!canManage.value || !targets.length) return;
  clearNotices();
  removal.value = { kind, ids: targets.map((item) => item.id), names: targets.map((item) => item.name) };
}
function askRemoveInstitution(): void {
  if (!canManage.value || !snapshot.value) return;
  clearNotices();
  removal.value = { kind: 'institution', ids: [snapshot.value.id], names: [snapshot.value.name] };
}
const removingInstitution = shallowRef(false);
async function confirmRemove(): Promise<void> {
  const target = removal.value;
  if (!target) return;
  if (target.kind !== 'institution') {
    try {
      result.value = bulkNotice('Удалено', await remove(target.kind, target.ids));
    } finally {
      removal.value = null;
    }
    return;
  }
  removingInstitution.value = true;
  try {
    await structureApi.remove('institution', props.institutionId);
    await router.push('/cabinet/institutions');
  } catch (cause) {
    actionError.value = target.names[0] + ': ' + structureRemovalError(cause);
  } finally {
    removingInstitution.value = false;
    removal.value = null;
  }
}
</script>
<template>
  <MfBreadcrumbs :items="[{ title: 'Учреждения', to: '/cabinet/institutions' }, { title: snapshot?.name ?? 'Учреждение' }]" />
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
    <h1 class="institution-title">{{ snapshot?.name ?? 'Учреждение' }}</h1>
    <p class="mf-muted">Реквизиты, назначения, съёмки и группы учреждения</p>
    <div class="mf-actions mt-5">
      <v-btn v-if="canManage" variant="outlined" prepend-icon="mdi-pencil-outline" :disabled="disabled" @click="editInstitution"
        >Изменить учреждение</v-btn
      >
      <v-btn variant="outlined" prepend-icon="mdi-refresh" :disabled="loading || removing" @click="reload()">Обновить</v-btn>
      <v-btn
        v-if="canManage"
        variant="text"
        color="error"
        prepend-icon="mdi-delete-outline"
        :disabled="disabled"
        @click="askRemoveInstitution"
        >Удалить учреждение</v-btn
      >
    </div>
  </header>
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка учреждения" class="mb-5" />
  <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
  <v-alert v-if="actionError && !error" type="error" variant="tonal" role="alert" class="mb-5">{{ actionError }}</v-alert>
  <v-alert v-if="notice && !error" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <UiBulkNotice v-if="result && !error" :notice="result" class="mb-5" data-testid="structure-removal" />
  <template v-if="snapshot && !error">
    <InstitutionOverview :institution="snapshot" />
    <div class="institution-widgets">
      <StructureWidget
        title="Съёмки"
        icon="mdi-camera-outline"
        create-label="Новая съёмка"
        data-testid="institution-shoots"
        :columns="shootColumns"
        :rows="shootRows"
        :initial-sort="{ key: 'date', direction: 'desc' }"
        :actions="canManage"
        actions-width="184px"
        :note="firstPage(snapshot.shoots)"
        :disabled="disabled"
        :can-manage="canManage"
        empty-title="Съёмок пока нет"
        :empty-text="
          canManage ? 'Добавьте съёмку — затем в ней появятся группы.' : 'Организатор ещё не запланировал съёмки этого учреждения.'
        "
        @create="editShoot()"
        @remove="askRemove('shoot', $event)"
      >
        <template #cell-name="{ row }">
          <RouterLink v-if="canManage" :to="shootPath(row.id)" class="institution-link">{{ row.name }}</RouterLink>
          <span v-else>{{ row.name }}</span>
        </template>
        <template #actions="{ row }">
          <div class="institution-row-actions">
            <v-btn
              v-tooltip="'Кадры'"
              icon="mdi-image-multiple-outline"
              variant="text"
              density="compact"
              :to="shootPath(row.id, '/photos')"
              :aria-label="'Кадры: ' + row.name"
            />
            <v-btn
              v-tooltip="'Условия продажи'"
              icon="mdi-tag-outline"
              variant="text"
              density="compact"
              :to="shootPath(row.id, '/conditions')"
              :aria-label="'Условия продажи: ' + row.name"
            />
            <v-btn
              v-tooltip="'Изменить'"
              icon="mdi-pencil-outline"
              variant="text"
              density="compact"
              :disabled="disabled"
              :aria-label="'Изменить ' + row.name"
              @click="editShoot(row.id)"
            />
            <v-btn
              v-tooltip="'Удалить'"
              icon="mdi-delete-outline"
              variant="text"
              density="compact"
              color="error"
              :disabled="disabled"
              :aria-label="'Удалить ' + row.name"
              @click="askRemove('shoot', [row.id])"
            />
          </div>
        </template>
      </StructureWidget>
      <StructureWidget
        title="Группы"
        icon="mdi-account-group-outline"
        create-label="Новая группа"
        data-testid="institution-groups"
        :columns="groupColumns"
        :rows="groupRows"
        :filter="activeShootFilter"
        :initial-sort="{ key: 'name', direction: 'asc' }"
        :actions-width="canManage ? '140px' : '64px'"
        :note="firstPage(snapshot.groups)"
        :disabled="disabled || shootsLoading"
        :can-manage="canManage"
        :can-create="snapshot.shoots.meta.total > 0"
        empty-title="Групп пока нет"
        :empty-text="groupsEmptyText"
        @create="editGroup()"
        @remove="askRemove('group', $event)"
      >
        <template #tools>
          <v-select
            v-model="shootFilter"
            :items="shootFilterItems"
            label="Съёмка"
            aria-label="Съёмка"
            density="compact"
            hide-details
            class="institution-shoot-filter"
          />
        </template>
        <template #cell-name="{ row }">
          <span class="institution-group">
            <RouterLink
              v-if="canManage && group(row.id)"
              :to="shootPath(group(row.id)!.shootId, '/photos?group=' + encodeURIComponent(row.id))"
              class="institution-link"
              >{{ row.name }}</RouterLink
            >
            <span v-else>{{ row.name }}</span>
            <small v-if="group(row.id)?.groupKind === 'staff'">сотрудники</small>
          </span>
        </template>
        <template #cell-closesAt="{ row }">{{ row.closesAt ? moscowDate.format(new Date(String(row.closesAt))) : 'Не указано' }}</template>
        <template #actions="{ row }">
          <div class="institution-row-actions">
            <v-btn
              v-tooltip="'Ссылка и сроки'"
              icon="mdi-link-variant"
              variant="text"
              density="compact"
              :to="'/cabinet/links?group=' + encodeURIComponent(row.id)"
              :aria-label="'Ссылка и сроки: ' + row.name"
            />
            <template v-if="canManage">
              <v-btn
                v-tooltip="'Изменить'"
                icon="mdi-pencil-outline"
                variant="text"
                density="compact"
                :disabled="disabled || shootsLoading"
                :aria-label="'Изменить ' + row.name"
                @click="editGroup(row.id)"
              />
              <v-btn
                v-tooltip="'Удалить'"
                icon="mdi-delete-outline"
                variant="text"
                density="compact"
                color="error"
                :disabled="disabled"
                :aria-label="'Удалить ' + row.name"
                @click="askRemove('group', [row.id])"
              />
            </template>
          </div>
        </template>
      </StructureWidget>
    </div>
  </template>
  <StructureRemoveDialog
    :kind="removal?.kind ?? null"
    :names="removal?.names ?? []"
    :busy="removing || removingInstitution"
    @confirm="confirmRemove"
    @close="removal = null"
  />
  <AdminDialog
    :open="!!draft"
    :title="title"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    :can-reset="!draft?.pending"
    :fields-disabled="!!draft?.pending"
    @close="close"
    @save="save"
    @reset="refresh"
  >
    <StructureFields
      v-if="draft"
      v-model="draft.fields"
      :parent-id="draft.parentId"
      :kind="draft.kind"
      :existing="!!draft.id"
      :errors="errors"
      :shoots="draft.kind === 'group' ? shootItems : undefined"
      @update:parent-id="$event && editor.setParent($event)"
    />
  </AdminDialog>
</template>
<style scoped>
.institution-title {
  overflow-wrap: anywhere;
}
.institution-widgets {
  display: grid;
  gap: var(--mf-space-5);
  margin-top: var(--mf-space-5);
}
.institution-row-actions {
  display: flex;
  align-items: center;
  gap: 2px;
}
.institution-link {
  color: inherit;
  text-underline-offset: 3px;
}
.institution-group {
  display: grid;
  justify-items: start;
}
.institution-group small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
}
.institution-shoot-filter {
  width: 260px;
  max-width: 100%;
}
@media (max-width: 600px) {
  .institution-shoot-filter {
    flex: 1 1 100%;
    width: auto;
  }
}
</style>
