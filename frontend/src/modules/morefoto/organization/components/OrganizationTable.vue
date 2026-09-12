<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import UiClearButton from '../../ui/components/UiClearButton.vue';
import { clampTablePage, sortTableRows } from '../../ui/table-values';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
const props = withDefaults(defineProps<{ title: string; rows: UiTableRow[]; columns: UiTableColumn[]; empty: string; action?: string }>(), {
  action: 'Открыть'
});
defineEmits<{ open: [id: string] }>();
const query = shallowRef('');
const page = shallowRef(1);
const pageSize = shallowRef(10);
const sort = shallowRef<UiTableSort>({ key: 'name', direction: 'asc' });
const filtered = computed(() =>
  props.rows.filter((row) =>
    props.columns.some((column) =>
      String(row[column.key] ?? '')
        .toLocaleLowerCase('ru')
        .includes(query.value.trim().toLocaleLowerCase('ru'))
    )
  )
);
const sorted = computed(() => sortTableRows(filtered.value, props.columns, sort.value));
const pageRows = computed(() => sorted.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value));
watch([query, pageSize, sort], () => {
  page.value = 1;
});
watch(
  () => filtered.value.length,
  (total) => {
    page.value = clampTablePage(page.value, total, pageSize.value);
  }
);
</script>
<template>
  <section class="mf-panel org-table-panel">
    <div class="org-table-tools">
      <h2>{{ title }}</h2>
      <v-text-field
        v-model="query"
        :label="'Поиск: ' + title.toLowerCase()"
        :aria-label="'Поиск: ' + title.toLowerCase()"
        prepend-inner-icon="mdi-magnify"
        clearable
        density="compact"
        @update:model-value="query = $event ?? ''"
      >
        <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить поиск" /></template>
      </v-text-field>
    </div>
    <UiDataTable
      :title="title"
      :columns="columns"
      :rows="pageRows"
      :total="filtered.length"
      :page="page"
      :page-size="pageSize"
      :sort="sort"
      :selected="[]"
      :selectable="false"
      :removable="false"
      :empty-title="query ? 'Ничего не найдено' : empty"
      :empty-description="query ? 'Измените или очистите поиск.' : 'Добавьте первую запись кнопкой выше.'"
      @sort="sort = $event"
      @page="page = $event"
      @page-size="pageSize = $event"
    >
      <template #actions="{ row }">
        <div class="mf-actions">
          <v-btn
            variant="text"
            density="compact"
            :aria-label="action + ' ' + row.name"
            :data-open-id="row.id"
            @click="$emit('open', row.id)"
            >{{ action }}</v-btn
          >
          <v-btn
            v-if="row.galleryToken"
            :to="'/g/' + row.galleryToken"
            variant="outlined"
            density="compact"
            :aria-label="'Галерея: ' + row.name"
            >Галерея</v-btn
          >
        </div>
      </template>
    </UiDataTable>
  </section>
</template>
<style scoped>
.org-table-tools {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}
.org-table-tools > .v-input {
  flex: 0 1 380px;
  width: 100%;
}
.org-table-panel {
  min-width: 0;
}
@media (max-width: 599px) {
  .org-table-tools > .v-input {
    flex-basis: 100%;
  }
}
</style>
