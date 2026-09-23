<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import { roleLabels } from '../../types';
import StaffFields from './StaffFields.vue';
import { useStaffEditor } from '../useStaffEditor';
import { useStaffManagement } from '../useStaffManagement';
import type { AccountStatus, StaffInvitation, StaffSummary } from '../model';
import { staffApi, staffError } from '../api';
import { formatMoment } from '../../handoff/display';
import MfStatus from '@/components/status/MfStatus.vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
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
const { snapshot, loading, error, filters, reload, page, pages } = useStaffManagement();
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
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка сотрудников" class="mb-5" />
  <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <section v-if="snapshot && !error" aria-label="Список сотрудников">
    <p class="mf-muted mb-4">Всего: {{ snapshot.meta.total }}</p>
    <v-card variant="outlined" class="staff-table">
      <div v-if="!snapshot.items.length" class="staff-empty">
        <v-icon icon="mdi-account-search-outline" size="36" />
        <p>Сотрудники не найдены</p>
      </div>
      <button
        v-for="item in snapshot.items"
        :key="item.id"
        type="button"
        class="staff-row"
        :aria-label="'Редактировать сотрудника ' + item.name"
        @click="edit(item)"
      >
        <span class="staff-person"
          ><MfAvatar
            :seed="avatarSeed(item.id, item.email)"
            :name="item.name"
            :email="item.email"
            :size="32"
            :src="item.avatar?.thumbUrl"
            decorative
          /><span class="staff-person__text"
            ><strong>{{ item.name }}</strong
            ><small>{{ item.email }}</small></span
          ></span
        >
        <span><small>Роль</small>{{ roleLabels[item.role] }}</span>
        <span><small>Назначения</small>{{ item.assignmentCount }}</span>
        <span class="staff-status"
          ><MfStatus :tone="accountStatusTone[item.accountStatus]">{{ statusLabels[item.accountStatus] }}</MfStatus
          ><small v-if="item.accountStatus === 'pending' && item.invitation">{{ invitationShort(item.invitation) }}</small></span
        >
        <v-icon icon="mdi-chevron-right" aria-hidden="true" />
      </button>
    </v-card>
    <nav v-if="pages > 1" class="mf-actions mt-5" aria-label="Страницы сотрудников">
      <v-btn variant="outlined" :disabled="loading || page === 1" @click="reload(page - 1)">Предыдущая</v-btn>
      <span>Страница {{ page }} из {{ pages }}</span>
      <v-btn variant="outlined" :disabled="loading || page === pages" @click="reload(page + 1)">Следующая</v-btn>
    </nav>
  </section>
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
.staff-table {
  overflow: hidden;
}
.staff-row {
  width: 100%;
  display: grid;
  /* Every row is its own grid: fixed tracks keep the columns aligned when a status carries the invitation date. */
  grid-template-columns: minmax(190px, 2fr) minmax(120px, 1fr) 100px 210px 24px;
  gap: 16px;
  align-items: center;
  padding: 18px 20px;
  border: 0;
  border-bottom: 1px solid var(--mf-color-border);
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}
.staff-row:last-child {
  border-bottom: 0;
}
.staff-row:hover,
.staff-row:focus-visible {
  background: var(--mf-color-bg);
  outline: none;
}
.staff-row span {
  display: grid;
  gap: 2px;
}
.staff-row small {
  color: var(--mf-color-text-secondary);
  font-size: 12px;
}
.staff-row .staff-person {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  overflow-wrap: anywhere;
}
.staff-row .staff-person__text {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.staff-empty {
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 48px 20px;
  color: var(--mf-color-text-secondary);
}
@media (max-width: 760px) {
  .staff-filters {
    grid-template-columns: 1fr;
  }
  .staff-row {
    grid-template-columns: 1fr auto;
    gap: 12px;
  }
  .staff-row > span:not(.staff-person) {
    display: none;
  }
  .staff-row .v-chip {
    grid-column: 1;
    justify-self: start;
  }
  .staff-row > .v-icon {
    grid-row: 1 / span 2;
    grid-column: 2;
  }
}
</style>
