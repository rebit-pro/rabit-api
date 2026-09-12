<script setup lang="ts">
import { currentBuyer, financials, settlementLabel } from '../../settlement/rules';
import SettlementTotals from '../../settlement/components/SettlementTotals.vue';
import { computed, shallowRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCurator } from '../useCurator';
import { useCaseEditor } from '../useCaseEditor';
import { useWorkFilters } from '../useWorkFilters';
import { matchesOrder, matchesCase, supportStatuses } from '../rules';
import { calendarDays, moscowInput } from '../../handoff/rules';
import { paymentLabels, productionLabels } from '../../orders/formatters';
import { supportTopics } from '../../orders/delivery/rules';
import type { UiTableColumn, UiTableRow } from '../../ui/table-types';
import type { CaseCommand } from '../types';
import WorkFilters from './WorkFilters.vue';
import WorkTable from './WorkTable.vue';
import StaffOrderDetails from './StaffOrderDetails.vue';
import CaseDetails from './CaseDetails.vue';
import CaseEditor from './CaseEditor.vue';
import '../curator.css';
const route = useRoute(),
  router = useRouter(),
  { data, loading, error, reload } = useCurator(),
  filters = useWorkFilters(),
  notice = shallowRef('');
const editor = useCaseEditor(() => {
  notice.value = 'Изменения сохранены.';
  void reload();
});
const { command, busy, error: saveError, errors, restored } = editor;
const cases = computed(() => route.path.startsWith('/cabinet/support'));
const detail = computed(() => (cases.value ? route.params.ticketId : route.params.orderId));
const selectedOrder = computed(() => data.value?.orders.find((o) => o.id === route.params.orderId));
const selectedCase = computed(() => data.value?.cases.find((c) => c.request.id === route.params.ticketId));
const title = computed(() => (cases.value ? (detail.value ? 'Обращение' : 'Обращения') : detail.value ? 'Карточка заказа' : 'Заказы'));
const path = computed(() => (cases.value ? '/cabinet/support' : '/cabinet/orders'));
const rows = computed<UiTableRow[]>(() =>
  cases.value
    ? (data.value?.cases
        .filter((c) => matchesCase(c, filters.filters.value))
        .map((c) => ({
          id: c.request.id,
          number: c.request.number,
          group: c.order.groupName,
          subject: supportTopics.find((t) => t.value === c.request.topic)?.title ?? c.request.topic,
          state: c.request.status,
          createdAt: new Date(Date.parse(c.request.createdAt) + 3 * 3600000).toISOString(),
          orderNumber: c.order.number
        })) ?? [])
    : (data.value?.orders
        .filter((o) => matchesOrder(o, filters.filters.value))
        .map((o) => ({
          id: o.id,
          number: o.number,
          group: o.groupName,
          buyer: currentBuyer(o).name,
          payment: o.paymentStatus,
          production: o.productionStatus,
          total: o.quote.total,
          balance: financials([o]).net,
          settlement: settlementLabel(o),
          createdAt: new Date(Date.parse(o.createdAt) + 3 * 3600000).toISOString()
        })) ?? [])
);
const statuses = (labels: Record<string, string>) =>
  Object.fromEntries(
    Object.entries(labels).map(([key, label]) => [
      key,
      { label, tone: key === 'paid' || key === 'resolved' ? ('success' as const) : ('neutral' as const) }
    ])
  );
const columns = computed<UiTableColumn[]>(() => [
  { key: 'number', label: cases.value ? 'Обращение' : 'Заказ', primary: true, sortable: true },
  { key: 'group', label: 'Группа', mobile: true, sortable: true },
  ...(cases.value
    ? [
        { key: 'subject', label: 'Тема', mobile: true },
        { key: 'state', label: 'Состояние', type: 'status' as const, statuses: statuses(supportStatuses), mobile: true, sortable: true },
        { key: 'orderNumber', label: 'Заказ', sortable: true }
      ]
    : [
        { key: 'buyer', label: 'Покупатель', mobile: true, sortable: true },
        { key: 'payment', label: 'Оплата', type: 'status' as const, statuses: statuses(paymentLabels), mobile: true, sortable: true },
        { key: 'production', label: 'Производство', type: 'status' as const, statuses: statuses(productionLabels), sortable: true },
        { key: 'total', label: 'Сумма', type: 'money' as const, mobile: true, sortable: true },
        { key: 'settlement', label: 'Возврат / решение', mobile: true }
      ]),
  { key: 'createdAt', label: 'Создан', type: 'date', sortable: true }
]);
function open(id: string) {
  void router.push({ path: path.value + '/' + id, query: route.query });
}
function edit(action: CaseCommand['action']) {
  if (!selectedCase.value) return;
  const id = selectedCase.value.request.id;
  editor.open(
    () => {
      const current = data.value?.cases.find((c) => c.request.id === id);
      if (!current) throw new Error('Обращение больше недоступно. Закройте редактор и обновите список.');
      const reference = Math.max(Date.parse(current.order.period.closesAt ?? '') || 0, Date.parse(data.value!.now));
      return {
        kind: 'case',
        action,
        requestId: crypto.randomUUID(),
        orderId: current.order.id,
        supportId: id,
        revision: current.request.revision ?? 1,
        groupRevision: current.order.period.revision,
        status: 'in-progress',
        comment: '',
        closesAt: moscowInput(calendarDays(new Date(reference).toISOString(), 1)),
        reason: '',
        confirmed: false
      };
    },
    id + ':' + action
  );
}
function change(patch: Partial<CaseCommand>) {
  if (command.value) Object.assign(command.value, patch);
}
</script>
<template>
  <header class="work-heading">
    <div>
      <p class="mf-eyebrow">СОПРОВОЖДЕНИЕ СЪЁМОК</p>
      <h1>{{ title }}</h1>
      <p class="mf-muted">
        {{ cases ? 'Вопросы родителей, ответы и согласованные сроки.' : 'Заказы вашей области: оплата, состав и получение.' }}
      </p>
    </div>
    <v-btn variant="outlined" :loading="loading" @click="reload">Обновить</v-btn>
  </header>
  <p v-if="notice" role="status" class="work-notice">{{ notice }}</p>
  <v-alert v-if="error" type="error" variant="tonal">{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert>
  <p v-if="loading && !data" role="status">Загружаем данные…</p>
  <template v-if="data"
    ><template v-if="detail"
      ><v-btn :to="{ path, query: route.query }" variant="text" prepend-icon="mdi-arrow-left" class="mb-5">{{
        cases ? 'К списку обращений' : 'К списку заказов'
      }}</v-btn
      ><CaseDetails v-if="cases && selectedCase" :item="selectedCase" @reply="edit('reply')" @extend="edit('extend')" /><StaffOrderDetails
        v-else-if="!cases && selectedOrder"
        :order="selectedOrder"
      /><v-alert v-else type="warning" variant="tonal">Запись не найдена или недоступна в вашей области.</v-alert></template
    >
    <template v-else
      ><WorkFilters
        :filters="filters.filters.value"
        :scope="data.scope"
        :cases="cases"
        @patch="filters.patch"
        @reset="filters.reset" /><SettlementTotals
        v-if="!cases"
        :totals="financials(data.orders.filter((o) => matchesOrder(o, filters.filters.value)))" /><WorkTable
        :title="title"
        :rows="rows"
        :columns="columns"
        :page="filters.page.value"
        :size="filters.size.value"
        :sort="filters.sort.value"
        @page="filters.setPage"
        @size="filters.setSize"
        @sort="filters.setSort"
        @open="open" /></template
  ></template>
  <CaseEditor
    :command="command"
    :item="selectedCase"
    :busy="busy"
    :error="saveError"
    :errors="errors"
    :restored="restored"
    @change="change"
    @close="editor.close"
    @reset="editor.reset"
    @save="editor.save"
  />
</template>
