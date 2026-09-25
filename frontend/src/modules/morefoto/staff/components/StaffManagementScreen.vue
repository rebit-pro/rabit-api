<script setup lang="ts">
import { computed, shallowRef, useTemplateRef } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import { roleLabels } from '../../types';
import StaffFields from './StaffFields.vue';
import StaffPerson from './StaffPerson.vue';
import { useStaffEditor } from '../useStaffEditor';
import { useStaffManagement } from '../useStaffManagement';
import type { AccountStatus, StaffInvitation, StaffSort, StaffSummary } from '../model';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import { staffApi, staffError } from '../api';
import { formatMoment } from '../../handoff/display';
import MfStatus from '@/components/status/MfStatus.vue';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import MfDistribution from '@/components/viz/MfDistribution.vue';
import { plural } from '@/components/viz/measures';
import { CHART_CATEGORY, ROLE_PASTEL } from '../../ui/chartPalette';
import { avatarSeed } from '@/components/avatar/avatar';
import type { AvatarRef } from '@/api/auth';
import { useAuthStore } from '@/stores/auth';
import AvatarEditor from '../../avatar/AvatarEditor.vue';
import { avatarApi } from '../../avatar/api';
import { accountStatusTone } from '../../ui/statusTone';
import type { StatusTone } from '@/components/status/tones';
const {
  snapshot,
  loading,
  error,
  filters,
  sort,
  pageSize,
  selected: selection,
  reload,
  setSort,
  setPageSize,
  staff,
  remove,
  page
} = useStaffManagement();
const auth = useAuthStore();
const notice = shallowRef('');
const editor = useStaffEditor(async () => {
  notice.value = 'Изменения сохранены. Активные сессии затронутых сотрудников завершены.';
  await reload();
});
const statusLabels: Record<AccountStatus, string> = {
  active: 'Активен',
  pending: 'Ожидает регистрации',
  blocked: 'Доступ отключён'
};
const statusIcons: Record<AccountStatus, string> = {
  pending: 'mdi-account-clock-outline',
  active: 'mdi-account-check-outline',
  blocked: 'mdi-account-lock-outline'
};
const summary = computed(() => snapshot.value?.meta.summary ?? null);
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Сотрудник', sortable: true, primary: true },
  { key: 'role', label: 'Роль', sortable: true, mobile: true },
  { key: 'assignments', label: 'Назначения', type: 'number', sortable: true, mobile: true },
  { key: 'status', label: 'Статус', sortable: true, mobile: true }
];
const byId = computed(() => new Map((snapshot.value?.items ?? []).map((item) => [String(item.id), item])));
const rows = computed<UiTableRow[]>(() =>
  (snapshot.value?.items ?? []).map((item) => ({
    id: String(item.id),
    name: item.name,
    role: roleLabels[item.role],
    assignments: item.assignmentCount,
    status: statusLabels[item.accountStatus]
  }))
);
const selfId = computed(() => String(auth.user?.id ?? ''));
const table = useTemplateRef<{ focusRow: (id?: string) => void }>('table');
const removeIds = shallowRef<string[]>([]);
const removing = shallowRef(false);
const removal = shallowRef<{ tone: 'success' | 'warning'; text: string; failures: string[] } | null>(null);
const removeTargets = computed(() => staff(removeIds.value));
const removeSkipsSelf = computed(() => selection.value.includes(selfId.value));
const skippedSelf = shallowRef(false);
const showRemove = computed({
  get: () => removeIds.value.length > 0,
  set: (open: boolean) => {
    if (!open && !removing.value) removeIds.value = [];
  }
});
function askRemove(ids: string[]): void {
  removal.value = null;
  skippedSelf.value = ids.includes(selfId.value);
  removeIds.value = ids.filter((id) => id !== selfId.value);
}
async function confirmRemove(): Promise<void> {
  const ids = removeIds.value;
  removing.value = true;
  try {
    const result = await remove(ids);
    const text = 'Удалено: ' + result.removed + ' из ' + ids.length + '.';
    removal.value = {
      tone: result.failed.length ? 'warning' : 'success',
      text: result.failed.length ? text : text + ' Их доступ к кабинету закрыт, назначения сняты.',
      failures: result.failed.map((item) => item.name + ': ' + item.reason)
    };
  } finally {
    removing.value = false;
    removeIds.value = [];
  }
}
function editRow(id: string): void {
  const item = byId.value.get(id);
  if (item) edit(item);
}
function statusTone(id: string): StatusTone {
  const item = byId.value.get(id);
  return item ? accountStatusTone[item.accountStatus] : 'neutral';
}
function invitationNote(id: string): string {
  const item = byId.value.get(id);
  return item?.accountStatus === 'pending' && item.invitation ? invitationShort(item.invitation) : '';
}
function changeSort(next: UiTableSort): void {
  setSort(next as StaffSort);
}
const roleSegments = computed(() =>
  (Object.keys(roleLabels) as (keyof typeof roleLabels)[]).map((role) => ({
    key: role,
    label: roleLabels[role],
    value: summary.value?.byRole[role] ?? 0,
    pastel: ROLE_PASTEL[role]
  }))
);
function toggleStatus(status: AccountStatus): void {
  filters.accountStatus = filters.accountStatus === status ? null : status;
  void reload(1);
}
const statusItems = [
  { title: 'Все статусы', value: null },
  ...(Object.entries(statusLabels) as [AccountStatus, string][]).map(([value, title]) => ({ value, title }))
];
const roleItems = [{ title: 'Все роли', value: null }, ...Object.entries(roleLabels).map(([value, title]) => ({ value, title }))];
const title = computed(() => (editor.draft.value?.id ? 'Редактирование сотрудника' : 'Новый сотрудник'));
const selected = shallowRef<StaffSummary | null>(null);
const resending = shallowRef(false);
const invitationNotice = shallowRef('');
const invitationError = shallowRef('');
const shortDate = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', timeZone: 'Europe/Moscow' });
function invitationShort(invitation: StaffInvitation): string {
  return invitation.state === 'expired' ? 'приглашение истекло' : 'приглашение отправлено ' + shortDate.format(new Date(invitation.sentAt));
}
function invitationText(invitation: StaffInvitation | null | undefined): string {
  if (!invitation) return 'Приглашение ещё не отправлялось.';
  if (invitation.state === 'expired') return 'Приглашение истекло ' + formatMoment(invitation.expiresAt) + '. Отправьте новое.';
  return 'Приглашение отправлено ' + formatMoment(invitation.sentAt) + ', ссылка действует до ' + formatMoment(invitation.expiresAt) + '.';
}
async function resend(): Promise<void> {
  const item = selected.value;
  if (!item || resending.value) return;
  resending.value = true;
  invitationNotice.value = '';
  invitationError.value = '';
  try {
    selected.value = { ...item, invitation: await staffApi.resendInvitation(item.id) };
    invitationNotice.value = 'Приглашение отправлено повторно. Прежняя ссылка больше не работает.';
    await reload();
  } catch (cause) {
    invitationError.value = staffError(cause);
  } finally {
    resending.value = false;
  }
}
async function avatarChanged(avatar: AvatarRef | null): Promise<void> {
  const item = selected.value;
  if (!item) return;
  selected.value = { ...item, avatar };
  if (item.id === auth.user?.id) await auth.reloadProfile();
  await reload();
}
function edit(item?: StaffSummary): void {
  notice.value = '';
  removal.value = null;
  invitationNotice.value = '';
  invitationError.value = '';
  selected.value = item ?? null;
  void editor.open(item);
}
</script>
<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ДОСТУП К КАБИНЕТУ</p>
    <h1>Сотрудники</h1>
    <p class="mf-muted">Роли и назначения на реальные учреждения и группы</p>
    <div class="mf-actions mt-5">
      <v-btn prepend-icon="mdi-account-plus-outline" :disabled="loading" @click="edit()">Добавить сотрудника</v-btn>
      <v-btn variant="outlined" :disabled="loading" @click="reload()">Обновить</v-btn>
    </div>
  </header>
  <section v-if="summary" class="staff-summary mb-5" aria-label="Сотрудники по статусу учётки" data-testid="staff-tiles">
    <div class="staff-tiles">
      <MfStatTile
        v-for="status in ['pending', 'active', 'blocked'] as AccountStatus[]"
        :key="status"
        :label="statusLabels[status]"
        :value="summary.byAccountStatus[status]"
        :unit="plural(summary.byAccountStatus[status], ['сотрудник', 'сотрудника', 'сотрудников'])"
        :pastel="CHART_CATEGORY.staff"
        :icon="statusIcons[status]"
        selectable
        :active="filters.accountStatus === status"
        @select="toggleStatus(status)"
      />
    </div>
    <div class="mf-panel">
      <MfDistribution title="Сотрудники по ролям" :segments="roleSegments" :unit-forms="['сотрудника', 'сотрудников', 'сотрудников']" />
    </div>
  </section>
  <form class="staff-filters mb-5" aria-label="Фильтры сотрудников" @submit.prevent="reload(1)">
    <v-text-field v-model="filters.q" label="Имя или email" clearable hide-details />
    <v-select v-model="filters.role" :items="roleItems" label="Роль" aria-label="Роль" hide-details />
    <v-select v-model="filters.accountStatus" :items="statusItems" label="Статус" aria-label="Статус" hide-details />
    <v-btn type="submit" variant="outlined" :disabled="loading">Найти</v-btn>
  </form>
  <v-progress-linear v-if="loading && snapshot" indeterminate aria-label="Загрузка сотрудников" class="mb-5" />
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <v-alert v-if="removal" :type="removal.tone" variant="tonal" role="status" class="mb-5" data-testid="staff-removal">
    {{ removal.text }}
    <ul v-if="removal.failures.length" class="staff-removal-failures">
      <li v-for="failure in removal.failures" :key="failure">{{ failure }}</li>
    </ul>
  </v-alert>
  <section aria-label="Список сотрудников">
    <UiDataTable
      ref="table"
      title="Сотрудники"
      label-key="name"
      :columns="columns"
      :rows="rows"
      :total="snapshot?.meta.total ?? 0"
      :page="page"
      :page-size="pageSize"
      :sort="sort"
      :selected="selection"
      :loading="loading && !snapshot"
      :error="error"
      empty-title="Сотрудники не найдены"
      empty-description="Измените фильтры или добавьте сотрудника."
      @sort="changeSort"
      @page="reload($event)"
      @page-size="setPageSize"
      @select="selection = $event"
      @retry="reload()"
    >
      <template #selection>
        <div class="staff-bulk" data-testid="staff-bulk">
          <p>Выбрано: {{ selection.length }}</p>
          <v-btn
            color="error"
            variant="outlined"
            density="compact"
            prepend-icon="mdi-delete-outline"
            aria-label="Удалить выбранных"
            :disabled="loading || removing || (removeSkipsSelf && selection.length === 1)"
            @click="askRemove(selection)"
            ><span class="staff-bulk__wide">Удалить выбранных</span><span class="staff-bulk__narrow">Удалить</span></v-btn
          >
          <v-btn variant="text" density="compact" aria-label="Снять выбор" @click="selection = []"
            ><span class="staff-bulk__wide">Снять выбор</span><v-icon class="staff-bulk__narrow" icon="mdi-close"
          /></v-btn>
        </div>
      </template>
      <template #cell-name="{ row }">
        <StaffPerson v-if="byId.get(row.id)" :item="byId.get(row.id)!" @open="edit" />
      </template>
      <template #cell-status="{ row }">
        <span class="staff-status"
          ><MfStatus :tone="statusTone(row.id)">{{ row.status }}</MfStatus
          ><small v-if="invitationNote(row.id)">{{ invitationNote(row.id) }}</small></span
        >
      </template>
      <template #actions="{ row }">
        <div class="staff-actions">
          <v-btn variant="text" density="compact" :aria-label="'Изменить сотрудника ' + row.name" @click="editRow(row.id)">Изменить</v-btn>
          <v-btn
            v-if="row.id !== selfId"
            icon="mdi-delete-outline"
            variant="text"
            density="compact"
            color="error"
            :aria-label="'Удалить сотрудника ' + row.name"
            :disabled="removing"
            @click="askRemove([row.id])"
          />
        </div>
      </template>
    </UiDataTable>
  </section>
  <v-dialog v-model="showRemove" max-width="520" aria-labelledby="staff-remove-title" @after-leave="table?.focusRow()">
    <v-card class="morefoto-app mf-panel staff-remove-dialog" data-testid="staff-remove-dialog">
      <h2 id="staff-remove-title">
        {{ removeTargets.length === 1 ? 'Удалить сотрудника?' : 'Удалить сотрудников: ' + removeTargets.length + '?' }}
      </h2>
      <ul class="staff-remove-names">
        <li v-for="item in removeTargets.slice(0, 10)" :key="item.id">{{ item.name }} · {{ item.email }}</li>
        <li v-if="removeTargets.length > 10">и ещё {{ removeTargets.length - 10 }}</li>
      </ul>
      <p v-if="skippedSelf" class="staff-remove-self">Вашу учётку удалить нельзя, она не входит в список.</p>
      <p>
        Доступ к кабинету закроется сразу, назначения на учреждения и группы будут сняты. История действий сохранится. Вернуть сотрудника
        можно, добавив его заново по тому же email: он получит новое приглашение.
      </p>
      <div class="mf-actions">
        <v-btn color="error" :loading="removing" @click="confirmRemove">Удалить</v-btn>
        <v-btn variant="outlined" :disabled="removing" @click="showRemove = false">Отмена</v-btn>
      </div>
    </v-card>
  </v-dialog>
  <AdminDialog
    :open="!!editor.draft.value"
    :title="title"
    :busy="editor.busy.value"
    :error="editor.error.value"
    :restored="false"
    footer-hint="Изменение роли, активности или назначений завершает старые сессии сотрудника."
    @close="editor.close"
    @save="editor.save"
    @reset="editor.refresh"
  >
    <AvatarEditor
      v-if="selected"
      class="mb-5"
      :seed="avatarSeed(selected.id, selected.email)"
      :name="selected.name"
      :email="selected.email"
      :avatar="selected.avatar"
      :save="(file: File) => avatarApi.save(selected!.id, file)"
      :remove="() => avatarApi.remove(selected!.id)"
      @changed="avatarChanged"
    />
    <div v-if="selected?.accountStatus === 'pending'" class="staff-invitation" data-testid="staff-invitation">
      <p>{{ invitationText(selected.invitation) }}</p>
      <v-alert v-if="invitationNotice" type="success" variant="tonal" role="status">{{ invitationNotice }}</v-alert>
      <v-alert v-if="invitationError" type="error" variant="tonal" role="alert">{{ invitationError }}</v-alert>
      <v-btn variant="outlined" prepend-icon="mdi-email-fast-outline" :loading="resending" @click="resend"
        >Отправить приглашение повторно</v-btn
      >
    </div>
    <StaffFields
      v-if="editor.draft.value && editor.options.value"
      v-model="editor.draft.value"
      :options="editor.options.value"
      :replacements="editor.replacements.value"
    />
  </AdminDialog>
</template>
<style scoped>
.staff-status {
  display: grid;
  justify-items: start;
  gap: var(--mf-space-1);
}
.staff-status small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.staff-invitation {
  display: grid;
  gap: var(--mf-space-3);
  justify-items: start;
  margin-bottom: var(--mf-space-5);
  padding: var(--mf-space-4);
  border: 1px solid var(--mf-tone-pending-border);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-tone-pending-bg);
}
.staff-summary {
  display: grid;
  gap: var(--mf-space-4);
}
.staff-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
  gap: var(--mf-space-4);
}
.staff-filters {
  display: grid;
  grid-template-columns: minmax(220px, 2fr) minmax(150px, 1fr) minmax(170px, 1fr) auto;
  gap: 12px;
  align-items: center;
}
.staff-actions {
  display: flex;
  align-items: center;
  gap: var(--mf-space-1);
}
.staff-bulk {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
}
.staff-bulk__narrow {
  display: none;
}
.staff-remove-self {
  color: var(--mf-color-text-secondary);
}
.staff-removal-failures {
  margin: var(--mf-space-2) 0 0 var(--mf-space-5);
}
.staff-remove-dialog {
  display: grid;
  gap: 16px;
  padding: 24px;
}
.staff-remove-dialog p {
  line-height: 1.6;
}
.staff-remove-names {
  display: grid;
  gap: 4px;
  margin-left: var(--mf-space-5);
  overflow-wrap: anywhere;
}
@media (max-width: 760px) {
  .staff-filters {
    grid-template-columns: 1fr;
  }
  .staff-bulk {
    gap: var(--mf-space-2);
  }
  .staff-bulk__wide {
    display: none;
  }
  .staff-bulk__narrow {
    display: inline;
  }
  .staff-remove-dialog {
    padding: 16px;
  }
}
</style>
