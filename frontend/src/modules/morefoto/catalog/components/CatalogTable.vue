<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import { clampTablePage, sortTableRows } from '../../ui/table-values';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import type { CatalogProduct } from '../api';
import type { ProductKind } from '../../commerce/types';
import { money } from '../../commerce/money';
/** The catalogue as one compact table: the whole loaded list is filtered, sorted and paged here (#92 DEC-06). */
const props = withDefaults(defineProps<{ products: CatalogProduct[]; disabled: boolean; editable?: boolean }>(), { editable: true });
const emit = defineEmits<{ edit: [product: CatalogProduct]; remove: [ids: string[]]; activate: [ids: string[], active: boolean] }>();
const selected = defineModel<string[]>('selected', { default: () => [] });
const kindLabels: Record<ProductKind, string> = { physical: 'Печать', digital: 'Электронный', bundle: 'Комплект' };
// The kind is the only product field that tells a print from a file; a calendar or a magnet is a print too.
const kindIcons: Record<ProductKind, string> = {
  physical: 'mdi-printer-outline',
  digital: 'mdi-cloud-download-outline',
  bundle: 'mdi-package-variant-closed'
};
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Продукция', sortable: true, primary: true, width: '34%' },
  { key: 'kind', label: 'Тип', sortable: true, mobile: true, width: '11%' },
  { key: 'price', label: 'Цена', type: 'money', sortable: true, mobile: true, width: '11%' },
  { key: 'discount', label: 'Сотрудникам', sortable: true, width: '13%' },
  {
    key: 'status',
    label: 'Статус',
    type: 'status',
    sortable: true,
    mobile: true,
    width: '13%',
    statuses: { active: { label: 'В продаже', tone: 'success' }, off: { label: 'Отключено', tone: 'neutral' } }
  }
];
const query = shallowRef('');
const kind = shallowRef<ProductKind | null>(null);
const status = shallowRef<'active' | 'off' | null>(null);
const page = shallowRef(1);
const pageSize = shallowRef(10);
const sort = shallowRef<UiTableSort>({ key: 'name', direction: 'asc' });
const filtered = computed(() => !!(query.value.trim() || kind.value || status.value));
const byId = computed(() => new Map(props.products.map((product) => [product.id, product])));
const rows = computed<UiTableRow[]>(() => {
  const needle = query.value.trim().toLocaleLowerCase('ru');
  return props.products
    .filter(
      (product) =>
        (!kind.value || product.kind === kind.value) &&
        (!status.value || (product.active ? 'active' : 'off') === status.value) &&
        product.name.toLocaleLowerCase('ru').includes(needle)
    )
    .map((product) => ({
      id: product.id,
      name: product.name,
      kind: kindLabels[product.kind],
      price: product.price,
      discount: product.staffDiscount ? '−50%' : '—',
      status: product.active ? 'active' : 'off'
    }));
});
const pageRows = computed(() =>
  sortTableRows(rows.value, columns, sort.value).slice((page.value - 1) * pageSize.value, page.value * pageSize.value)
);
watch([query, kind, status, pageSize, sort], () => {
  page.value = 1;
});
watch(
  () => rows.value.length,
  (total) => {
    page.value = clampTablePage(page.value, total, pageSize.value);
  }
);
const kindItems = [
  { title: 'Все типы', value: null },
  ...(Object.entries(kindLabels) as [ProductKind, string][]).map(([value, title]) => ({ value, title }))
];
const statusItems = [
  { title: 'Все статусы', value: null },
  { title: 'В продаже', value: 'active' },
  { title: 'Отключено', value: 'off' }
];
function details(id: string): string {
  const product = byId.value.get(id);
  if (!product) return '';
  const facts = [product.format, product.unit].filter(Boolean).join(' · ');
  return product.description ? (facts ? facts + ' — ' : '') + product.description : facts;
}
function edit(id: string): void {
  const product = byId.value.get(id);
  if (product) emit('edit', product);
}
</script>
<template>
  <form class="catalog-filters mb-4" aria-label="Фильтры каталога" @submit.prevent>
    <v-text-field
      :model-value="query"
      label="Название"
      aria-label="Название"
      prepend-inner-icon="mdi-magnify"
      clearable
      hide-details
      density="compact"
      @update:model-value="query = $event ?? ''"
    />
    <v-select v-model="kind" :items="kindItems" label="Тип" aria-label="Тип" hide-details density="compact" />
    <v-select v-model="status" :items="statusItems" label="Статус" aria-label="Статус" hide-details density="compact" />
  </form>
  <UiDataTable
    title="Продукция"
    label-key="name"
    density="compact"
    actions-width="168px"
    :columns="columns"
    :rows="pageRows"
    :total="rows.length"
    :page="page"
    :page-size="pageSize"
    :sort="sort"
    :selected="selected"
    :selectable="editable"
    :actions="editable"
    :empty-title="filtered ? 'Ничего не найдено' : 'Ассортимент пуст'"
    :empty-description="filtered ? 'Измените или очистите фильтры.' : editable ? 'Добавьте первую продукцию.' : 'Для группы нет продукции.'"
    @sort="sort = $event"
    @page="page = $event"
    @page-size="pageSize = $event"
    @select="selected = $event"
  >
    <template #selection>
      <div class="catalog-bulk" data-testid="catalog-bulk">
        <p>Выбрано: {{ selected.length }}</p>
        <v-btn
          variant="outlined"
          density="compact"
          prepend-icon="mdi-eye-off-outline"
          :disabled="disabled"
          @click="emit('activate', selected, false)"
          >Снять с продажи</v-btn
        >
        <v-btn
          variant="outlined"
          density="compact"
          prepend-icon="mdi-eye-outline"
          :disabled="disabled"
          @click="emit('activate', selected, true)"
          >Вернуть в продажу</v-btn
        >
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
    <template #cell-name="{ row }">
      <span class="catalog-name">
        <span class="catalog-icon" :class="'catalog-icon--' + byId.get(row.id)?.kind" aria-hidden="true"
          ><v-icon :icon="kindIcons[byId.get(row.id)?.kind ?? 'physical']" size="20"
        /></span>
        <span class="catalog-name-text">
          <strong>{{ row.name }}</strong>
          <small v-if="details(row.id)" :title="details(row.id)">{{ details(row.id) }}</small>
        </span>
      </span>
    </template>
    <template #cell-price="{ row }">{{ money(Number(row.price)) }}</template>
    <template #actions="{ row }">
      <div class="catalog-actions">
        <v-btn variant="text" density="compact" :disabled="disabled" :aria-label="'Изменить ' + row.name" @click="edit(row.id)"
          >Изменить</v-btn
        >
        <v-btn
          icon="mdi-delete-outline"
          variant="text"
          density="compact"
          color="error"
          :disabled="disabled"
          :aria-label="'Удалить ' + row.name"
          @click="emit('remove', [row.id])"
        />
      </div>
    </template>
  </UiDataTable>
</template>
<style scoped>
.catalog-filters {
  display: grid;
  grid-template-columns: minmax(220px, 2fr) minmax(150px, 1fr) minmax(150px, 1fr);
  gap: 12px;
}
.catalog-name {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  min-width: 0;
}
.catalog-icon {
  display: inline-grid;
  place-items: center;
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-primary-soft);
  color: var(--mf-color-primary);
}
.catalog-icon--digital {
  background: var(--mf-tone-info-bg);
}
.catalog-icon--bundle {
  background: var(--mf-tone-success-bg);
}
.catalog-name-text {
  display: grid;
  min-width: 0;
}
.catalog-name-text strong {
  font-weight: 600;
}
.catalog-name-text small {
  overflow: hidden;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.catalog-actions,
.catalog-bulk {
  display: flex;
  align-items: center;
  gap: var(--mf-space-1);
}
.catalog-bulk {
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
@media (max-width: 760px) {
  .catalog-filters {
    grid-template-columns: 1fr;
  }
}
</style>
