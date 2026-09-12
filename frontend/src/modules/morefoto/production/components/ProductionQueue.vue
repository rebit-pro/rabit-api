<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { printCount } from '../rules';
import { productionState } from '../display';
import { formatMoment } from '../../handoff/display';
import type { ProductionGroup } from '../types';
const props = defineProps<{ groups: ProductionGroup[] }>();
const route = useRoute(),
  router = useRouter();
const value = (key: string) => (typeof route.query[key] === 'string' ? (route.query[key] as string) : '');
function filter(key: string, value: string | null) {
  void router.replace({ query: { ...route.query, [key]: value || undefined, ...(key === 'institution' ? { shoot: undefined } : {}) } });
}
const institutions = computed(() => [
  ...new Map(props.groups.map((g) => [g.group.institutionId, { title: g.institutionName, value: g.group.institutionId }])).values()
]);
const shoots = computed(() => [
  ...new Map(
    props.groups
      .filter((g) => !value('institution') || g.group.institutionId === value('institution'))
      .map((g) => [g.group.shootId, { title: g.shootName, value: g.group.shootId }])
  ).values()
]);
const states = [
  'Подготовка группы',
  'Приём открыт',
  'Можно сформировать',
  'Нет позиций для печати',
  'Состав изменился',
  'Задание подготовлено',
  'Комплектация',
  'Пакеты скомплектованы'
];
const visible = computed(() =>
  props.groups.filter(
    (g) =>
      (!value('institution') || g.group.institutionId === value('institution')) &&
      (!value('shoot') || g.group.shootId === value('shoot')) &&
      (!value('state') || productionState(g) === value('state'))
  )
);
</script>
<template>
  <section class="mf-panel production-filters" aria-label="Фильтры производства">
    <v-select
      label="Учреждение"
      aria-label="Учреждение"
      :model-value="value('institution') || null"
      placeholder="Все учреждения"
      :items="institutions"
      clearable
      hide-details
      @update:model-value="filter('institution', $event)"
    />
    <v-select
      label="Съёмка"
      aria-label="Съёмка"
      :model-value="value('shoot') || null"
      placeholder="Все съёмки"
      :items="shoots"
      clearable
      hide-details
      @update:model-value="filter('shoot', $event)"
    />
    <v-select
      label="Состояние производства"
      aria-label="Состояние производства"
      :model-value="value('state') || null"
      placeholder="Все состояния"
      :items="states"
      clearable
      hide-details
      @update:model-value="filter('state', $event)"
    />
  </section>
  <p class="mf-muted" role="status">Групп: {{ visible.length }}</p>
  <section v-if="!visible.length" class="mf-panel">
    <h2>Группы не найдены</h2>
    <p class="mt-3">Измените фильтры или проверьте назначенные учреждения.</p>
    <v-btn class="mt-4" variant="outlined" @click="router.replace({ query: {} })">Сбросить фильтры</v-btn>
  </section>
  <div class="production-groups">
    <article
      v-for="item in visible"
      :key="item.group.id"
      class="mf-panel production-group"
      :data-testid="'production-group-' + item.group.id"
    >
      <p class="mf-eyebrow">{{ item.institutionName }}</p>
      <h2>{{ item.group.name }}</h2>
      <p class="mf-muted">{{ item.shootName }} · {{ item.group.kind === 'staff' ? 'Сотрудники' : 'Родители' }}</p>
      <v-chip class="my-4" :color="item.plan.closed ? 'primary' : 'secondary'">{{ productionState(item) }}</v-chip>
      <p>
        Отпечатков: <strong>{{ printCount(item.plan.rows) }}</strong> · заказов:
        <strong>{{ new Set(item.plan.rows.map((r) => r.orderId)).size }}</strong>
      </p>
      <p class="mf-muted mt-2">Закрытие: {{ formatMoment(item.plan.closesAt) }}</p>
      <p v-if="item.plan.excluded.length" class="mt-2">Требуют внимания: {{ item.plan.excluded.length }}</p>
      <p v-if="item.job" class="mt-2">{{ item.job.number }} · версия {{ item.job.versions.slice(-1)[0]?.number }}</p>
      <v-btn
        class="mt-5"
        variant="outlined"
        :to="{ path: '/cabinet/production/' + item.group.id, query: route.query }"
        :aria-label="'Открыть производство: ' + item.group.name"
        >Открыть производство</v-btn
      >
    </article>
  </div>
</template>
