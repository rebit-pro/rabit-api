<script setup lang="ts">
import { computed } from 'vue';
import type { WorkFilters } from '../types';
import type { ScopeSnapshot } from '../../types';
import { paymentLabels, productionLabels } from '../../orders/formatters';
import { supportTopics } from '../../orders/delivery/rules';
import { supportStatuses } from '../rules';
const props = defineProps<{ filters: WorkFilters; scope: ScopeSnapshot; cases: boolean }>();
const emit = defineEmits<{ patch: [value: Partial<WorkFilters>]; reset: [] }>();
const shoots = computed(() =>
  props.scope.shoots.filter((s) => !props.filters.institution || s.institutionId === props.filters.institution)
);
const groups = computed(() =>
  props.scope.groups.filter(
    (g) =>
      (!props.filters.institution || g.institutionId === props.filters.institution) &&
      (!props.filters.shoot || g.shootId === props.filters.shoot)
  )
);
const options = (labels: Record<string, string>) => [
  { title: 'Все', value: '' },
  ...Object.entries(labels).map(([value, title]) => ({ title, value }))
];
</script>
<template>
  <section class="mf-panel work-filters" aria-label="Фильтры">
    <v-text-field
      :model-value="filters.query"
      :label="cases ? 'Поиск обращений' : 'Поиск заказов'"
      :aria-label="cases ? 'Поиск обращений' : 'Поиск заказов'"
      :hint="cases ? 'Номер, вопрос, код или email для ответа' : 'Номер, покупатель, контакт или код снимка'"
      persistent-hint
      @update:model-value="emit('patch', { query: $event ?? '' })"
    />
    <div class="work-filter-grid">
      <v-select
        :model-value="filters.institution"
        label="Учреждение"
        aria-label="Учреждение"
        :items="[{ id: '', name: 'Все учреждения' }, ...scope.institutions]"
        item-title="name"
        item-value="id"
        @update:model-value="emit('patch', { institution: $event, shoot: '', group: '' })"
      />
      <v-select
        :model-value="filters.shoot"
        label="Съёмка"
        aria-label="Съёмка"
        :items="[{ id: '', name: 'Все съёмки' }, ...shoots]"
        item-title="name"
        item-value="id"
        @update:model-value="emit('patch', { shoot: $event, group: '' })"
      />
      <v-select
        :model-value="filters.group"
        label="Группа"
        aria-label="Группа"
        :items="[{ id: '', name: 'Все группы' }, ...groups]"
        item-title="name"
        item-value="id"
        @update:model-value="emit('patch', { group: $event })"
      />
      <template v-if="cases"
        ><v-select
          :model-value="filters.status"
          label="Состояние обращения"
          aria-label="Состояние обращения"
          :items="options(supportStatuses)"
          @update:model-value="emit('patch', { status: $event })" /><v-select
          :model-value="filters.topic"
          label="Тема обращения"
          aria-label="Тема обращения"
          :items="[{ title: 'Все темы', value: '' }, ...supportTopics]"
          @update:model-value="emit('patch', { topic: $event })"
      /></template>
      <template v-else
        ><v-select
          :model-value="filters.payment"
          label="Оплата"
          aria-label="Оплата"
          :items="options(paymentLabels)"
          @update:model-value="emit('patch', { payment: $event })" /><v-select
          :model-value="filters.production"
          label="Производство"
          aria-label="Производство"
          :items="options(productionLabels)"
          @update:model-value="emit('patch', { production: $event })"
      /></template>
      <v-select
        v-if="!cases"
        :model-value="filters.settlement ?? ''"
        label="Возвраты и решения"
        aria-label="Возвраты и решения"
        :items="
          [
            'Все',
            'Без возврата',
            'Возврат обрабатывается',
            'Частичный возврат',
            'Полный возврат',
            'Поздняя: нужна проверка',
            'Поздняя: исполнить',
            'Поздняя: вернуть'
          ].map((title) => ({ title, value: title === 'Все' ? '' : title }))
        "
        @update:model-value="emit('patch', { settlement: $event })"
      />
      <v-text-field
        :model-value="filters.dateFrom"
        label="Создан с (МСК)"
        type="date"
        @update:model-value="emit('patch', { dateFrom: $event })"
      /><v-text-field
        :model-value="filters.dateTo"
        label="Создан по (МСК)"
        type="date"
        @update:model-value="emit('patch', { dateTo: $event })"
      />
    </div>
    <v-checkbox
      v-if="!cases"
      :model-value="!!filters.late"
      label="Только поздние оплаты"
      hide-details
      @update:model-value="emit('patch', { late: $event ? 'yes' : '' })"
    />
    <div class="mf-actions"><v-btn variant="text" @click="emit('reset')">Сбросить фильтры</v-btn></div>
    <p v-if="filters.dateFrom && filters.dateTo && filters.dateFrom > filters.dateTo" role="alert">
      Начало периода позже окончания. Измените даты.
    </p>
  </section>
</template>
