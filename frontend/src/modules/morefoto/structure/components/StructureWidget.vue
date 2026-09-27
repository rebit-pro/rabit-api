<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import { clampTablePage, sortTableRows } from '../../ui/table-values';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
/** One compact table of the institution page (shoots or groups) with its own add button and bulk removal (#92). */
const props = withDefaults(
  defineProps<{
    title: string;
    icon: string;
    createLabel: string;
    columns: UiTableColumn[];
    rows: UiTableRow[];
    emptyTitle: string;
    emptyText: string;
    disabled: boolean;
    /** Selection, removal and the add button are the organizer's; others only read. */
    canManage: boolean;
    canCreate?: boolean;
    initialSort: UiTableSort;
    actions?: boolean;
    actionsWidth?: string;
    /** A muted line under the heading, e.g. that only the first page came from the server. */
    note?: string;
    /** The filter the rows came through: a new filter starts from the first page. */
    filter?: string | null;
  }>(),
  { canCreate: true, actions: true, actionsWidth: '176px', note: '' }
);
const emit = defineEmits<{ create: []; remove: [ids: string[]] }>();
const page = shallowRef(1);
const pageSize = shallowRef(10);
const sort = shallowRef<UiTableSort>(props.initialSort);
const selected = shallowRef<string[]>([]);
const pageRows = computed(() =>
  sortTableRows(props.rows, props.columns, sort.value).slice((page.value - 1) * pageSize.value, page.value * pageSize.value)
);
watch([pageSize, sort, () => props.filter], () => {
  page.value = 1;
});
watch(
  () => props.rows,
  (rows) => {
    page.value = clampTablePage(page.value, rows.length, pageSize.value);
    // Removed or filtered-out rows leave the selection: the bulk action never touches a row that is not shown.
    const ids = new Set(rows.map((row) => row.id));
    selected.value = selected.value.filter((id) => ids.has(id));
  }
);
</script>
<template>
  <section class="mf-panel structure-widget" :aria-label="title">
    <header class="structure-widget__head">
      <h2>
        <v-icon :icon="icon" size="22" aria-hidden="true" /> {{ title }}
        <span class="structure-widget__count" :aria-label="'всего ' + rows.length">{{ rows.length }}</span>
      </h2>
      <div class="structure-widget__tools">
        <slot name="tools" />
        <v-btn v-if="canManage && canCreate" prepend-icon="mdi-plus" :disabled="disabled" @click="emit('create')">{{ createLabel }}</v-btn>
      </div>
    </header>
    <p v-if="note" class="mf-muted structure-widget__note">{{ note }}</p>
    <UiDataTable
      :title="title"
      label-key="name"
      density="compact"
      auto-pager
      :actions="actions"
      :actions-width="actionsWidth"
      :columns="columns"
      :rows="pageRows"
      :total="rows.length"
      :page="page"
      :page-size="pageSize"
      :sort="sort"
      :selected="selected"
      :selectable="canManage"
      :empty-title="emptyTitle"
      :empty-description="emptyText"
      @sort="sort = $event"
      @page="page = $event"
      @page-size="pageSize = $event"
      @select="selected = $event"
    >
      <template #selection>
        <div class="structure-widget__bulk">
          <p>Выбрано: {{ selected.length }}</p>
          <v-btn
            color="error"
            variant="outlined"
            density="compact"
            prepend-icon="mdi-delete-outline"
            :disabled="disabled"
            @click="emit('remove', selected)"
            >Удалить</v-btn
          >
          <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
        </div>
      </template>
      <template v-for="column in columns" #[`cell-${column.key}`]="{ row }" :key="column.key">
        <slot :name="'cell-' + column.key" :row="row" />
      </template>
      <template #actions="{ row }"><slot name="actions" :row="row" /></template>
    </UiDataTable>
  </section>
</template>
<style scoped>
.structure-widget {
  min-width: 0;
  padding: var(--mf-space-5);
}
.structure-widget__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-3);
  margin-bottom: var(--mf-space-2);
}
.structure-widget__head h2 {
  display: flex;
  align-items: center;
  gap: var(--mf-space-2);
  font-size: var(--mf-text-lg);
}
.structure-widget__count {
  padding: 0 8px;
  border-radius: 999px;
  background: var(--mf-color-surface-2);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 600;
}
.structure-widget__note {
  margin-bottom: var(--mf-space-2);
  font-size: var(--mf-text-sm);
}
.structure-widget__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-3);
}
.structure-widget__bulk {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
}
@media (max-width: 600px) {
  .structure-widget {
    padding: var(--mf-space-4);
  }
  .structure-widget__tools {
    width: 100%;
  }
}
</style>
