<script setup lang="ts">
import { computed } from 'vue';
import type { ChildSales } from '../api';
import { reportChildren } from '../rules';
import ReportChildrenTable from './ReportChildrenTable.vue';

const props = defineProps<{ items: ChildSales[]; includeUnordered: boolean }>();
const emit = defineEmits<{ 'update:includeUnordered': [value: boolean] }>();
const withoutOrders = computed(() => props.items.filter((child) => child.status === 'none').length);
const visible = computed(() => reportChildren(props.items, props.includeUnordered));
const emptyTitle = computed(() => (props.items.length ? 'Заказов пока нет' : 'В группе пока нет детей'));
const emptyDescription = computed(() =>
  props.items.length ? 'Строки без заказа доступны в полном списке.' : 'Дети появятся после разметки фотографий группы.'
);
</script>

<template>
  <section class="report-children-section" aria-labelledby="report-children-title">
    <div class="report-children-section__heading">
      <div>
        <h2 id="report-children-title">Дети группы</h2>
        <p class="mf-muted" data-testid="report-without-orders">Нет заказа: {{ withoutOrders }}. Итоги включают всю группу.</p>
      </div>
      <v-checkbox
        :model-value="includeUnordered"
        label="Показать полный список"
        color="primary"
        hide-details
        density="compact"
        data-testid="report-all-children"
        @update:model-value="emit('update:includeUnordered', $event === true)"
      />
    </div>
    <ReportChildrenTable :key="String(includeUnordered)" :items="visible" :empty-title="emptyTitle" :empty-description="emptyDescription" />
  </section>
</template>

<style scoped>
.report-children-section {
  display: grid;
  gap: var(--mf-space-3);
  margin-bottom: var(--mf-space-6);
}
.report-children-section__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.report-children-section h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.report-children-section .mf-muted {
  margin: var(--mf-space-1) 0 0;
  font-size: var(--mf-text-sm);
}
</style>
