<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue';
import type { UiDensity } from '../types';
import type { UiTableKind } from '../table-types';
import { useTableExample } from '../composables/useTableExample';
import UiDataTable from './UiDataTable.vue';
import UiTableCell from './UiTableCell.vue';
import UiClearButton from './UiClearButton.vue';
const props = defineProps<{ kind: UiTableKind; density: UiDensity }>();
const title = props.kind === 'institutions' ? 'Учреждения' : 'Заказы';
const {
  columns,
  allRows,
  rows,
  filtered,
  search,
  status,
  statusItems,
  from,
  to,
  dateError,
  hasFilters,
  selected,
  page,
  pageSize,
  sort,
  mode,
  notice,
  removeIds,
  openedId,
  opened,
  clearFilters,
  setSort,
  setPageSize,
  load,
  fail,
  empty,
  reset,
  requestRemove,
  remove
} = useTableExample(props.kind);
const table = useTemplateRef<{ focusRow: (id?: string) => void }>('table');
const returnId = ref<string>();
const showCard = computed({
  get: () => !!openedId.value,
  set: (value) => {
    if (!value) openedId.value = null;
  }
});
const showRemove = computed({
  get: () => !!removeIds.value.length,
  set: (value) => {
    if (!value) removeIds.value = [];
  }
});
function open(id: string) {
  returnId.value = id;
  openedId.value = id;
}
function askRemove(ids: string[]) {
  returnId.value = ids[0];
  requestRemove(ids);
}
function returnFocus() {
  table.value?.focusRow(returnId.value);
}
</script>
<template>
  <section class="mf-panel ui-table-example" :aria-labelledby="'ui-table-title-' + kind" :data-testid="'ui-table-' + kind">
    <header class="ui-table-example-heading">
      <div>
        <h2 :id="'ui-table-title-' + kind">{{ title }}</h2>
        <p class="mf-muted">Вымышленные данные · {{ allRows.length }} записей в образце</p>
      </div>
      <v-btn variant="text" density="compact" @click="reset">Восстановить 50 записей</v-btn>
    </header>
    <div class="ui-example-actions" aria-label="Состояния списка">
      <v-btn variant="outlined" density="compact" @click="load">Показать загрузку</v-btn>
      <v-btn variant="outlined" density="compact" @click="fail">Ошибка загрузки</v-btn>
      <v-btn variant="outlined" density="compact" @click="empty">Показать пустой список</v-btn>
    </div>
    <form class="ui-table-filters" novalidate role="search" :aria-label="'Поиск: ' + title" @submit.prevent>
      <v-text-field
        :model-value="search"
        :label="kind === 'institutions' ? 'Название или код' : 'Номер или учреждение'"
        aria-label="Поиск в списке"
        density="compact"
        clearable
        class="ui-table-search"
        @update:model-value="search = $event ?? ''"
      >
        <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить поиск в списке" /></template>
      </v-text-field>
      <v-select
        v-model="status"
        :items="statusItems"
        label="Статус"
        aria-label="Статус списка"
        density="compact"
        clearable
        class="ui-table-status-filter"
      >
        <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить статус списка" /></template>
      </v-select>
      <v-text-field
        :model-value="from"
        type="date"
        label="Дата с"
        aria-label="Дата с"
        density="compact"
        class="ui-table-date-filter"
        @update:model-value="from = $event ?? ''"
      />
      <v-text-field
        :model-value="to"
        type="date"
        label="Дата по"
        aria-label="Дата по"
        density="compact"
        :error-messages="dateError"
        :aria-invalid="!!dateError || undefined"
        class="ui-table-date-filter"
        @update:model-value="to = $event ?? ''"
      />
      <v-btn variant="text" density="compact" :disabled="!hasFilters" @click="clearFilters">Сбросить фильтры</v-btn>
    </form>
    <p class="ui-table-result" data-testid="ui-table-result" role="status">
      Найдено: {{ filtered.length }} · Выбрано: {{ selected.length }}<span v-if="selected.length"> · на всех страницах</span>
    </p>
    <p v-if="notice" class="ui-table-notice" role="status">{{ notice }}</p>
    <div v-if="selected.length" class="ui-table-bulk">
      <p>Выбрано записей: {{ selected.length }}. Действие применится ко всем выбранным страницам.</p>
      <v-btn color="error" variant="outlined" density="compact" :disabled="mode !== 'ready'" @click="askRemove(selected)"
        >Удалить выбранные</v-btn
      >
      <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
    </div>
    <UiDataTable
      ref="table"
      :title="title"
      :columns="columns"
      :rows="rows"
      :total="filtered.length"
      :page="page"
      :page-size="pageSize"
      :sort="sort"
      :selected="selected"
      :loading="mode === 'loading'"
      :error="mode === 'error' ? 'Не удалось загрузить демонстрационный список.' : ''"
      :density="density"
      :empty-title="hasFilters && allRows.length ? 'Ничего не найдено' : 'Список пока пуст'"
      :empty-description="
        hasFilters && allRows.length
          ? 'Измените условия поиска или сбросьте фильтры.'
          : 'Восстановите демонстрационные записи кнопкой над списком.'
      "
      @sort="setSort"
      @page="page = $event"
      @page-size="setPageSize"
      @select="selected = $event"
      @open="open"
      @remove="askRemove([$event])"
      @retry="load"
    />
    <v-dialog v-model="showCard" max-width="620" :aria-labelledby="'ui-card-title-' + kind" @after-leave="returnFocus">
      <v-card class="morefoto-app mf-panel ui-table-dialog">
        <template v-if="opened">
          <h2 :id="'ui-card-title-' + kind">{{ opened.name }}</h2>
          <p class="mf-muted">Демонстрационная карточка · {{ opened.id }}</p>
          <dl>
            <div v-for="column in columns" :key="column.key">
              <dt>{{ column.label }}</dt>
              <dd><UiTableCell :column="column" :value="opened[column.key]" /></dd>
            </div>
          </dl>
        </template>
        <v-btn variant="outlined" @click="showCard = false">Закрыть карточку</v-btn>
      </v-card>
    </v-dialog>
    <v-dialog v-model="showRemove" max-width="500" :aria-labelledby="'ui-remove-title-' + kind" @after-leave="returnFocus">
      <v-card class="morefoto-app mf-panel ui-table-dialog">
        <h2 :id="'ui-remove-title-' + kind">Удалить записи из образца?</h2>
        <p>Выбрано: {{ removeIds.length }}. Это вымышленные записи; их можно восстановить кнопкой над списком.</p>
        <div class="ui-example-actions">
          <v-btn color="error" @click="remove">Подтвердить удаление записей</v-btn>
          <v-btn variant="outlined" @click="showRemove = false">Отмена</v-btn>
        </div>
      </v-card>
    </v-dialog>
  </section>
</template>
<style scoped>
.ui-table-example {
  display: grid;
  gap: var(--mf-space-4);
  min-width: 0;
}
.ui-table-example-heading {
  display: flex;
  align-items: start;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--mf-space-4);
}
.ui-table-example-heading p {
  margin-top: 8px;
  font-size: var(--mf-text-small);
}
.ui-table-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: start;
  gap: 12px;
}
.ui-table-search {
  flex: 1 1 240px !important;
}
.ui-table-status-filter {
  flex: 1 1 180px !important;
}
.ui-table-date-filter {
  flex: 0 1 170px !important;
  min-width: 150px;
}
.ui-table-result,
.ui-table-notice,
.ui-table-bulk {
  font-size: var(--mf-text-small);
  line-height: 1.6;
}
.ui-table-notice {
  color: var(--mf-color-link);
}
.ui-table-bulk {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: var(--mf-color-selected);
  border-radius: 8px;
}
.ui-table-dialog {
  display: grid;
  gap: 16px;
  padding: 24px;
}
.ui-table-dialog dl {
  display: grid;
  gap: 16px;
}
.ui-table-dialog dl > div {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
  gap: 12px;
}
.ui-table-dialog dt {
  color: var(--mf-color-text-secondary);
}
.ui-table-dialog dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.ui-table-dialog p {
  line-height: 1.6;
}
@media (max-width: 767px) {
  .ui-table-filters > .v-input {
    flex: 1 1 100% !important;
  }
  .ui-table-dialog {
    padding: 16px;
  }
  .ui-table-dialog dl > div {
    grid-template-columns: minmax(0, 1fr);
    gap: 4px;
  }
}
</style>
