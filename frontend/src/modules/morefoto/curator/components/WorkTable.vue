<script setup lang="ts">
import { computed } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import { clampTablePage, sortTableRows } from '../../ui/table-values';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
const props = defineProps<{ title: string; rows: UiTableRow[]; columns: UiTableColumn[]; page: number; size: number; sort: UiTableSort }>();
const emit = defineEmits<{ page: [value: number]; size: [value: number]; sort: [value: UiTableSort]; open: [id: string] }>();
const actual = computed(() => clampTablePage(props.page, props.rows.length, props.size));
const rows = computed(() =>
  sortTableRows(props.rows, props.columns, props.sort).slice((actual.value - 1) * props.size, actual.value * props.size)
);
</script>
<template>
  <section class="mf-panel mt-6" :class="{ 'work-orders': columns.some((c) => c.key === 'settlement') }">
    <UiDataTable
      :title="title"
      :rows="rows"
      :columns="columns"
      :total="props.rows.length"
      :page="actual"
      :page-size="size"
      :sort="sort"
      :selected="[]"
      :selectable="false"
      :removable="false"
      empty-title="Записей не найдено"
      empty-description="Проверьте фильтры. Здесь появятся заказы и обращения вашей области."
      @page="emit('page', $event)"
      @page-size="emit('size', $event)"
      @sort="emit('sort', $event)"
      ><template #actions="{ row }"
        ><v-btn variant="text" density="compact" :aria-label="'Открыть ' + row.number" :data-open-id="row.id" @click="emit('open', row.id)"
          >Открыть</v-btn
        ></template
      ></UiDataTable
    >
  </section>
</template>

<style scoped>
.work-orders :deep(table) {
  table-layout: auto;
}
.work-orders :deep(.ui-table-desktop) {
  overflow-x: auto;
}
.work-orders :deep(th) {
  overflow-wrap: normal;
}
.work-orders :deep(.ui-table-sort) {
  overflow-wrap: normal;
}
.work-orders :deep(th:last-child),
.work-orders :deep(td:last-child) {
  width: 90px;
}
.work-orders :deep(th:nth-child(4)),
.work-orders :deep(td:nth-child(4)) {
  min-width: 132px;
}
.work-orders :deep(td:last-child .v-btn) {
  min-width: 0;
  padding-inline: 6px;
}
.work-orders :deep(td:last-child .v-btn__content) {
  white-space: nowrap;
}
</style>
