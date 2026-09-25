<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import type { UiDensity } from '../types';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../table-types';
import { tableCellText, tablePageCount } from '../table-values';
import UiTableCell from './UiTableCell.vue';
import UiTableRowActions from './UiTableRowActions.vue';
import MfEmptyState from '@/components/states/MfEmptyState.vue';
import { plural } from '@/components/viz/measures';
const props = withDefaults(
  defineProps<{
    title: string;
    columns: UiTableColumn[];
    rows: UiTableRow[];
    total: number;
    page: number;
    pageSize: number;
    sort: UiTableSort;
    selected: string[];
    selectable?: boolean;
    removable?: boolean;
    loading?: boolean;
    error?: string;
    density?: UiDensity;
    emptyTitle?: string;
    emptyDescription?: string;
    /** Column whose value names a row for assistive labels; the row id by default. */
    labelKey?: string;
  }>(),
  { selectable: true, removable: true, density: 'comfortable', emptyTitle: 'Список пока пуст', emptyDescription: 'Здесь появятся записи.' }
);
const emit = defineEmits<{
  sort: [sort: UiTableSort];
  page: [page: number];
  pageSize: [size: number];
  select: [ids: string[]];
  open: [id: string];
  remove: [id: string];
  retry: [];
}>();
const root = useTemplateRef<HTMLElement>('root');
const pages = computed(() => tablePageCount(props.total, props.pageSize));
const selectedOnPage = computed(() => props.rows.filter((row) => props.selected.includes(row.id)).length);
const allSelected = computed(() => props.rows.length > 0 && selectedOnPage.value === props.rows.length);
const primary = computed(() => props.columns.find((column) => column.primary) ?? props.columns[0]);
const mobileColumns = computed(() => props.columns.filter((column) => column.mobile && column !== primary.value));
const extraColumns = computed(() => props.columns.filter((column) => !column.mobile && column !== primary.value));
const sortable = computed(() =>
  props.columns.filter((column) => column.sortable).map((column) => ({ title: column.label, value: column.key }))
);
const range = computed(() =>
  props.total ? (props.page - 1) * props.pageSize + 1 + '–' + Math.min(props.page * props.pageSize, props.total) : '0'
);
const summary = computed(() => props.title + ' · ' + props.total + ' ' + plural(props.total, ['запись', 'записи', 'записей']));
function sortIcon(key: string): string {
  if (props.sort.key !== key) return 'mdi-swap-vertical';
  return props.sort.direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down';
}
function rowLabel(row: UiTableRow): string {
  return props.labelKey ? String(row[props.labelKey] ?? row.id) : row.id;
}
function toggle(id: string) {
  emit('select', props.selected.includes(id) ? props.selected.filter((selected) => selected !== id) : [...props.selected, id]);
}
function togglePage() {
  const pageIds = props.rows.map((row) => row.id);
  emit('select', allSelected.value ? props.selected.filter((id) => !pageIds.includes(id)) : [...new Set([...props.selected, ...pageIds])]);
}
function sortBy(key: string) {
  emit('sort', { key, direction: props.sort.key === key && props.sort.direction === 'asc' ? 'desc' : 'asc' });
}
function focusRow(id?: string) {
  const buttons = Array.from(root.value?.querySelectorAll<HTMLElement>('[data-open-id]') ?? []).filter((el) => el.getClientRects().length);
  (buttons.find((el) => el.dataset.openId === id) ?? buttons[0] ?? root.value)?.focus();
}
defineExpose({ focusRow });
</script>
<template>
  <div
    ref="root"
    class="ui-data-table"
    :class="'ui-data-table--' + density"
    tabindex="-1"
    :aria-label="title"
    :aria-busy="loading"
    data-testid="ui-data-table"
  >
    <div v-if="loading" class="ui-table-state" role="status">
      <v-progress-circular indeterminate color="primary" />
      <p>Загружаем {{ title.toLowerCase() }}…</p>
    </div>
    <v-alert v-else-if="error" type="error" variant="tonal" role="alert">
      {{ error }}
      <div class="mt-3"><v-btn variant="outlined" @click="$emit('retry')">Повторить загрузку списка</v-btn></div>
    </v-alert>
    <MfEmptyState v-else-if="!rows.length" :title="emptyTitle" :text="emptyDescription" icon="mdi-text-box-search-outline" />
    <template v-else>
      <!-- One fixed-height bar for both states: selecting a row never pushes the table down. -->
      <div class="ui-table-toolbar" :class="{ 'ui-table-toolbar--selected': selected.length }" data-testid="ui-table-toolbar">
        <slot v-if="selected.length" name="selection" :selected="selected"
          ><p>Выбрано: {{ selected.length }}</p></slot
        >
        <p v-else class="ui-table-summary">{{ summary }}</p>
      </div>
      <div class="ui-table-mobile-tools">
        <v-select
          :model-value="sort.key"
          label="Сортировать по"
          aria-label="Сортировать по"
          :items="sortable"
          density="compact"
          @update:model-value="sortBy"
        />
        <v-btn
          variant="outlined"
          density="compact"
          :aria-label="sort.direction === 'asc' ? 'Изменить на убывание' : 'Изменить на возрастание'"
          @click="$emit('sort', { ...sort, direction: sort.direction === 'asc' ? 'desc' : 'asc' })"
          >{{ sort.direction === 'asc' ? 'По возрастанию ↑' : 'По убыванию ↓' }}</v-btn
        >
        <v-checkbox-btn
          v-if="selectable"
          :model-value="allSelected"
          :indeterminate="selectedOnPage > 0 && !allSelected"
          label="Выбрать все на странице"
          @update:model-value="togglePage"
        />
      </div>
      <div class="ui-table-desktop">
        <table>
          <caption class="ui-table-caption">
            {{
              summary
            }}
          </caption>
          <thead>
            <tr>
              <th v-if="selectable" class="ui-table-check" scope="col">
                <v-checkbox-btn
                  v-if="selectable"
                  :model-value="allSelected"
                  :indeterminate="selectedOnPage > 0 && !allSelected"
                  aria-label="Выбрать все на странице"
                  @update:model-value="togglePage"
                />
              </th>
              <th
                v-for="column in columns"
                :key="column.key"
                scope="col"
                :class="{ 'ui-table-number': column.type === 'number' || column.type === 'money' }"
                :aria-sort="
                  column.sortable ? (sort.key === column.key ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none') : undefined
                "
              >
                <button
                  v-if="column.sortable"
                  class="ui-table-sort"
                  :class="{ 'ui-table-sort--active': sort.key === column.key }"
                  type="button"
                  :aria-label="'Сортировать: ' + column.label"
                  @click="sortBy(column.key)"
                >
                  {{ column.label }}
                  <v-icon :icon="sortIcon(column.key)" size="16" class="ui-table-sort-icon" aria-hidden="true" />
                </button>
                <span v-else>{{ column.label }}</span>
              </th>
              <th scope="col">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" :data-row-id="row.id" :class="{ 'ui-table-selected': selected.includes(row.id) }">
              <td v-if="selectable" class="ui-table-check">
                <v-checkbox-btn
                  v-if="selectable"
                  :model-value="selected.includes(row.id)"
                  :aria-label="'Выбрать ' + rowLabel(row)"
                  @update:model-value="toggle(row.id)"
                />
              </td>
              <td
                v-for="column in columns"
                :key="column.key"
                :data-column="column.key"
                :class="{ 'ui-table-number': column.type === 'number' || column.type === 'money', 'ui-table-primary': column.primary }"
              >
                <slot :name="'cell-' + column.key" :row="row"><UiTableCell :value="row[column.key]" :column="column" /></slot>
              </td>
              <td>
                <slot name="actions" :row="row"
                  ><UiTableRowActions :row="row" :removable="removable" @open="$emit('open', row.id)" @remove="$emit('remove', row.id)"
                /></slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="ui-table-mobile">
        <article
          v-for="row in rows"
          :key="row.id"
          :data-row-id="row.id"
          class="ui-table-card"
          :class="{ 'ui-table-selected': selected.includes(row.id) }"
        >
          <div class="ui-table-card-title">
            <v-checkbox-btn
              v-if="selectable"
              :model-value="selected.includes(row.id)"
              :aria-label="'Выбрать ' + rowLabel(row)"
              @update:model-value="toggle(row.id)"
            />
            <h3>
              <slot v-if="primary" :name="'cell-' + primary.key" :row="row">{{ tableCellText(row[primary.key], primary) }}</slot
              ><template v-else>{{ row.id }}</template>
            </h3>
          </div>
          <dl>
            <div v-for="column in mobileColumns" :key="column.key">
              <dt>{{ column.label }}</dt>
              <dd :data-column="column.key">
                <slot :name="'cell-' + column.key" :row="row"><UiTableCell :value="row[column.key]" :column="column" /></slot>
              </dd>
            </div>
          </dl>
          <details v-if="extraColumns.length">
            <summary :aria-label="'Дополнительные сведения ' + rowLabel(row)">Дополнительные сведения</summary>
            <dl>
              <div v-for="column in extraColumns" :key="column.key">
                <dt>{{ column.label }}</dt>
                <dd>
                  <slot :name="'cell-' + column.key" :row="row"><UiTableCell :value="row[column.key]" :column="column" /></slot>
                </dd>
              </div>
            </dl>
          </details>
          <slot name="actions" :row="row"
            ><UiTableRowActions :row="row" :removable="removable" @open="$emit('open', row.id)" @remove="$emit('remove', row.id)"
          /></slot>
        </article>
      </div>
      <nav class="ui-table-pagination" :aria-label="'Страницы: ' + title">
        <p data-testid="ui-table-range" role="status">{{ range }} из {{ total }}</p>
        <v-select
          :model-value="pageSize"
          :items="[10, 25, 50]"
          label="Строк на странице"
          aria-label="Строк на странице"
          density="compact"
          class="ui-table-page-size"
          @update:model-value="$emit('pageSize', $event)"
        />
        <div class="ui-table-page-actions">
          <v-btn
            icon="mdi-chevron-left"
            variant="outlined"
            aria-label="Предыдущая страница"
            :disabled="page <= 1"
            @click="$emit('page', page - 1)"
          />
          <span>Страница {{ page }} из {{ pages }}</span>
          <v-btn
            icon="mdi-chevron-right"
            variant="outlined"
            aria-label="Следующая страница"
            :disabled="page >= pages"
            @click="$emit('page', page + 1)"
          />
        </div>
      </nav>
    </template>
  </div>
</template>
<style scoped>
.ui-data-table {
  min-width: 0;
}
table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  text-align: left;
  font-size: var(--mf-text-small);
  line-height: 1.5;
}
.ui-table-caption {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
.ui-table-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-2) var(--mf-space-3);
  /* Touch compact buttons are 44px: the bar keeps one height with or without a selection. */
  min-height: 56px;
  margin-bottom: var(--mf-space-3);
  padding: 6px var(--mf-space-3);
  border-radius: var(--mf-radius-sm);
  font-size: var(--mf-text-small);
  line-height: 1.5;
  transition: background-color 0.15s ease;
}
.ui-table-toolbar--selected {
  background: var(--mf-color-selected);
}
.ui-table-summary {
  color: var(--mf-color-text-secondary);
}
th,
td {
  padding: 4px 8px;
  border-bottom: 1px solid var(--mf-color-border);
  overflow-wrap: anywhere;
  vertical-align: middle;
}
th {
  height: var(--mf-table-header);
  border-bottom: 2px solid var(--mf-color-border);
  background: var(--mf-color-surface-2);
  color: var(--mf-color-text);
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
}
td {
  height: var(--mf-table-row);
}
.ui-data-table--compact td {
  height: var(--mf-table-row-compact);
  padding-block: 0;
}
thead .ui-table-check {
  padding-block: 0;
}
th {
  padding-block: 0;
}
.ui-table-check {
  width: 52px;
  padding: 4px;
}
th:last-child,
td:last-child {
  width: 176px;
}
.ui-table-primary {
  font-weight: 600;
}
.ui-table-number {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.ui-table-selected {
  background: var(--mf-color-selected);
}
.ui-table-sort {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 44px;
  text-align: inherit;
  font: inherit;
  color: inherit;
}
.ui-table-sort-icon {
  flex-shrink: 0;
  color: var(--mf-color-text-tertiary);
  opacity: 0.7;
  transition: opacity 0.15s ease;
}
.ui-table-sort:hover .ui-table-sort-icon,
.ui-table-sort:focus-visible .ui-table-sort-icon {
  opacity: 1;
}
.ui-table-sort--active {
  color: var(--mf-color-primary);
}
.ui-table-sort--active .ui-table-sort-icon {
  color: var(--mf-color-primary);
  opacity: 1;
}
.ui-table-state {
  display: grid;
  gap: 16px;
  justify-items: center;
  text-align: center;
  padding: 32px 16px;
}
.ui-table-pagination {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 16px;
  font-size: var(--mf-text-small);
}
.ui-table-page-size {
  width: 160px;
  max-width: 100%;
}
.ui-table-page-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.ui-table-mobile,
.ui-table-mobile-tools {
  display: none;
}
@media (max-width: 1099px) {
  .ui-table-desktop {
    display: none;
  }
  .ui-table-mobile-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
  }
  .ui-table-mobile-tools > .v-input {
    flex: 1 1 200px;
  }
  .ui-table-mobile {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
  }
  .ui-table-card {
    padding: 16px;
    border: 1px solid var(--mf-color-border);
    border-radius: 8px;
    min-width: 0;
  }
  .ui-table-card-title {
    display: flex;
    align-items: start;
    gap: 8px;
  }
  .ui-table-card h3 {
    font-size: 1rem;
    line-height: 1.5;
    padding-block: 8px;
    overflow-wrap: anywhere;
    min-width: 0;
  }
  .ui-table-card-title > .v-selection-control {
    flex: 0 0 44px;
  }
  .ui-table-card dl {
    display: grid;
    gap: 12px;
    margin-block: 16px;
    font-size: var(--mf-text-small);
    line-height: 1.5;
  }
  .ui-table-card dl > div {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
    gap: 12px;
  }
  .ui-table-card dt {
    min-width: 0;
    overflow-wrap: anywhere;
    color: var(--mf-color-text-secondary);
  }
  .ui-table-card dd {
    margin: 0;
    overflow-wrap: anywhere;
  }
  summary {
    min-height: 44px;
    padding-block: 12px;
    color: var(--mf-color-link);
    cursor: pointer;
    font-size: var(--mf-text-small);
    overflow-wrap: anywhere;
  }
}
</style>
