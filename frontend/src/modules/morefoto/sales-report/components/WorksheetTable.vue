<script setup lang="ts">
import { computed } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow } from '../../ui/table-types';
import { money, reportOrderStatus } from '../rules';
import { useLocalTable } from '../useLocalTable';
import {
  worksheetCells,
  worksheetFileContext,
  worksheetColumns,
  worksheetOrderName,
  type WorksheetColumn,
  type WorksheetGroup,
  type WorksheetOrder
} from '../worksheet';

const props = defineProps<{
  group: WorksheetGroup;
  columns: WorksheetColumn[];
  final: boolean;
  items?: WorksheetOrder[];
  reference?: boolean;
}>();
const items = computed(() => props.items ?? props.group.orders);
const physical = computed(() =>
  worksheetColumns({ ...props.group, orders: items.value }, props.columns, props.reference).map((column) => ({
    ...column,
    slot: 'cell-' + column.key
  }))
);
const byId = computed(
  () =>
    new Map(
      items.value.map((order) => [
        order.id,
        {
          order,
          name: worksheetOrderName(order),
          status: reportOrderStatus(order).label,
          cells: worksheetCells(order, props.reference),
          electronic: order.lines.filter((line) => line.kind !== 'physical'),
          outside: props.final && !props.reference ? order.lines.filter((line) => line.kind === 'physical' && !line.selected) : []
        }
      ])
    )
);
const columns = computed<UiTableColumn[]>(() => [
  {
    key: 'number',
    label: '№',
    type: 'number',
    sortable: true,
    mobile: true,
    width: '5%'
  },
  { key: 'name', label: 'Ф.И. ребёнка / заказ', primary: true, sortable: true },
  ...physical.value.map((column) => ({
    key: column.key,
    label: column.name + (column.format ? ' · ' + column.format : '')
  })),
  {
    key: 'electronic',
    label: props.final ? 'Электронная версия (текущие покупки)' : 'Электронная версия'
  },
  {
    key: 'total',
    label: props.reference ? 'Сумма заказа' : props.final ? 'Итого, печать' : 'Итого',
    type: 'money',
    sortable: true,
    mobile: true
  }
]);
const table = useLocalTable(
  () =>
    items.value.map((order, index) => {
      const row: UiTableRow = {
        id: order.id,
        number: index + 1,
        name: worksheetOrderName(order),
        total: props.reference ? order.total : order.includedTotal,
        electronic: ''
      };
      return row;
    }),
  columns.value,
  { key: 'number', direction: 'asc' }
);
const detail = (id: string) => byId.value.get(id)!;
</script>

<template>
  <UiDataTable
    title="Заказы ведомости"
    label-key="name"
    density="compact"
    :columns="columns"
    :rows="table.visible.value"
    :total="table.total.value"
    :page="table.page.value"
    :page-size="table.pageSize.value"
    :sort="table.sort.value"
    :selected="[]"
    :selectable="false"
    :actions="false"
    auto-pager
    empty-title="Оплаченного состава нет"
    empty-description="Неоплаченные и поздние заказы показаны отдельно ниже."
    data-testid="worksheet-orders"
    @sort="table.order"
    @page="table.page.value = $event"
    @page-size="table.resize"
  >
    <template #cell-name="{ row }">
      <div class="worksheet-name">
        <strong>{{ detail(row.id).name }}</strong>
        <span class="mf-muted">{{ detail(row.id).order.number }} · коды: {{ detail(row.id).order.childCodes.join(', ') }}</span>
        <span v-if="detail(row.id).order.ambiguous" class="worksheet-explanation">Несколько детей: ФИО относится к заказу целиком.</span>
        <span v-if="reference" class="mf-muted">{{ detail(row.id).status }}</span>
        <details v-if="detail(row.id).outside.length">
          <summary>Печать вне выбранной версии</summary>
          <p v-for="line in detail(row.id).outside" :key="line.id">{{ line.productName }} — {{ line.prints }} отп.</p>
        </details>
      </div>
    </template>
    <template v-for="column in physical" :key="column.key" #[column.slot]="{ row }">
      <div v-for="line in detail(row.id).cells[column.key]" :key="line.id" class="worksheet-file">
        <div v-for="(file, index) in line.files" :key="index">
          <strong>{{ file.filename ?? 'Исходное имя недоступно' }}</strong> — {{ line.prints }} отп.
          <span class="mf-muted worksheet-meta">{{ line.quantity }} × {{ line.printCount }}</span>
          <span v-if="file.path" class="worksheet-path">{{ file.path }}</span>
          <span v-if="file.printsInFile !== null" class="mf-muted worksheet-meta">В общем файле: {{ file.printsInFile }} отп.</span>
        </div>
        <span v-if="!line.files.length">Состав файла недоступен</span>
        <details>
          <summary>Цена покупки</summary>
          <p>Единица: {{ money(line.unitPrice) }}. Учтена скидка: {{ money(line.discount) }}. Позиция: {{ money(line.total) }}.</p>
        </details>
      </div>
    </template>
    <template #cell-electronic="{ row }">
      <div v-for="line in detail(row.id).electronic" :key="line.id" class="worksheet-file">
        <strong>{{ line.productName }}</strong> — {{ line.quantity }}
        {{ line.kind === 'bundle' ? 'компл.' : 'шт.' }}
        <span v-if="line.coveredByGift" class="mf-muted">Подарок, не продажа</span>
        <p v-for="(file, index) in line.files" :key="index">
          {{ file.filename ?? 'Исходное имя недоступно' }}
          <span v-if="worksheetFileContext(file)" class="mf-muted worksheet-meta">{{ worksheetFileContext(file) }}</span>
        </p>
        <p v-if="!line.files.length" class="mf-muted">Готовые файлы пока недоступны</p>
      </div>
      <p v-if="detail(row.id).order.giftCodes.length" class="mf-muted">
        Подарочные комплекты: {{ detail(row.id).order.giftCodes.join(', ') }}. Не увеличивают продажи.
      </p>
      <span v-if="!detail(row.id).electronic.length && !detail(row.id).order.giftCodes.length">—</span>
    </template>
    <template #cell-total="{ row }">
      <strong data-testid="worksheet-order-total">{{ money(Number(row.total)) }}</strong>
      <span v-if="final && !reference && detail(row.id).order.total !== detail(row.id).order.includedTotal" class="mf-muted worksheet-meta">
        Весь заказ: {{ money(detail(row.id).order.total) }}
      </span>
      <span v-if="!reference && detail(row.id).order.discount > 0" class="mf-muted worksheet-meta">
        Учтена скидка: {{ money(detail(row.id).order.discount) }}
      </span>
    </template>
  </UiDataTable>
</template>

<style scoped>
.worksheet-name,
.worksheet-file {
  display: grid;
  gap: var(--mf-space-1);
  padding-block: var(--mf-space-2);
  overflow-wrap: anywhere;
}
.worksheet-name .mf-muted,
.worksheet-meta,
.worksheet-path,
.worksheet-explanation {
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-regular);
}
.worksheet-meta,
.worksheet-path {
  display: block;
  white-space: normal;
  overflow-wrap: anywhere;
}
.worksheet-path {
  color: var(--mf-color-text-secondary);
}
.worksheet-explanation {
  color: var(--mf-color-text);
}
summary {
  min-height: 44px;
  padding-block: var(--mf-space-2);
  color: var(--mf-color-link);
  cursor: pointer;
  font-size: var(--mf-text-sm);
}
.worksheet-file p {
  margin: 0;
}
</style>
