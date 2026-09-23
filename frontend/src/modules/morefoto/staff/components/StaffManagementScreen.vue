<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import { roleLabels } from '../../types';
import StaffFields from './StaffFields.vue';
import { useStaffEditor } from '../useStaffEditor';
import { useStaffManagement } from '../useStaffManagement';
import type { AccountStatus, StaffSummary } from '../model';
import MfStatus from '@/components/status/MfStatus.vue';
import { accountStatusTone } from '../../ui/statusTone';
const { snapshot, loading, error, filters, reload, page, pages } = useStaffManagement();
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
const activeItems = [
  { title: 'Все статусы', value: null },
  { title: 'Доступ включён', value: true },
  { title: 'Доступ отключён', value: false }
];
const roleItems = [{ title: 'Все роли', value: null }, ...Object.entries(roleLabels).map(([value, title]) => ({ value, title }))];
const title = computed(() => (editor.draft.value?.id ? 'Редактирование сотрудника' : 'Новый сотрудник'));
function edit(item?: StaffSummary): void {
  notice.value = '';
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
  <form class="staff-filters mb-5" aria-label="Фильтры сотрудников" @submit.prevent="reload(1)">
    <v-text-field v-model="filters.q" label="Имя или email" clearable hide-details />
    <v-select v-model="filters.role" :items="roleItems" label="Роль" hide-details />
    <v-select v-model="filters.active" :items="activeItems" label="Доступ" hide-details />
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
          ><strong>{{ item.name }}</strong
          ><small>{{ item.email }}</small></span
        >
        <span><small>Роль</small>{{ roleLabels[item.role] }}</span>
        <span><small>Назначения</small>{{ item.assignmentCount }}</span>
        <MfStatus :tone="accountStatusTone[item.accountStatus]">{{ statusLabels[item.accountStatus] }}</MfStatus>
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
    <StaffFields
      v-if="editor.draft.value && editor.options.value"
      v-model="editor.draft.value"
      :options="editor.options.value"
      :replacements="editor.replacements.value"
    />
  </AdminDialog>
</template>
<style scoped>
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
  grid-template-columns: minmax(190px, 2fr) minmax(120px, 1fr) 100px minmax(140px, auto) 24px;
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
.staff-person {
  overflow-wrap: anywhere;
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
