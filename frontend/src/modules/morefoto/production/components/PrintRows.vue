<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableSort } from '../../ui/table-types';
import type { RunRow } from '../types';
const props = defineProps<{ rows: RunRow[] }>();
const page = ref(1),
  size = ref(10),
  sort = ref<UiTableSort>({ key: 'photo', direction: 'asc' });
const columns: UiTableColumn[] = [
  { key: 'photo', label: 'Кадр', primary: true, sortable: true },
  { key: 'order', label: 'Заказ', mobile: true, sortable: true },
  { key: 'format', label: 'Формат', mobile: true },
  { key: 'units', label: 'Единиц × отпечатков', mobile: true },
  { key: 'total', label: 'Всего', type: 'number', mobile: true },
  { key: 'prior', label: 'Запущено ранее', type: 'number', mobile: true },
  { key: 'next', label: 'Новая печать', type: 'number', mobile: true },
  { key: 'child', label: 'Код покупателя', mobile: true },
  { key: 'source', label: 'Исходная группа и код', mobile: true }
];
const mapped = computed(() =>
  props.rows.map((r) => ({
    id: r.key,
    order: r.orderNumber,
    photo: r.photoCode,
    format: r.format,
    units: r.quantity + ' × ' + r.perUnit,
    total: r.prints,
    prior: r.prior,
    next: r.next,
    child: r.childCode,
    source: r.sourceGroupName + ' · ' + r.sourceChildCode,
    orderId: r.orderId
  }))
);
const sorted = computed(() =>
  [...mapped.value].sort(
    (a, b) =>
      String(a[sort.value.key as 'photo']).localeCompare(String(b[sort.value.key as 'photo']), 'ru', { numeric: true }) *
      (sort.value.direction === 'asc' ? 1 : -1)
  )
);
watch(
  () => [props.rows, size.value],
  () => {
    page.value = 1;
  }
);
</script>
<template>
  <UiDataTable
    title="Позиции печати"
    :columns="columns"
    :rows="sorted.slice((page - 1) * size, page * size)"
    :total="rows.length"
    :page="page"
    :page-size="size"
    :sort="sort"
    :selected="[]"
    :selectable="false"
    :removable="false"
    empty-title="Физических позиций нет"
    empty-description="Электронные файлы не включаются в печать."
    @page="page = $event"
    @page-size="size = $event"
    @sort="sort = $event"
  >
    <template #actions="{ row }"
      ><v-btn size="small" variant="text" :to="'/cabinet/orders/' + row.orderId" :aria-label="'Заказ ' + row.order"
        >К заказу</v-btn
      ></template
    >
  </UiDataTable>
</template>
