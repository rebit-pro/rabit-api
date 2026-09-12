<script setup lang="ts">
import type { PhotoShoot } from '../../organization/types';
import type { DashboardFilters } from '../types';
defineProps<{
  readonly filters: DashboardFilters;
  readonly shoots: PhotoShoot[];
}>();
const emit = defineEmits<{
  patch: [value: Partial<DashboardFilters>];
  reset: [];
}>();
const states = [
  { title: 'Все состояния', value: '' },
  { title: 'Подготовка', value: 'preparing' },
  { title: 'Приём открыт', value: 'open' },
  { title: 'Приём закрыт', value: 'closed' }
];
</script>
<template>
  <section class="dash-filters" aria-label="Фильтры групп">
    <v-select
      :model-value="filters.shoot"
      label="Съёмка"
      aria-label="Съёмка"
      :items="[
        { title: 'Все съёмки', value: '' },
        ...shoots.map((s) => ({
          title: s.name + (s.date ? ' · ' + s.date.split('-').reverse().join('.') : ''),
          value: s.id
        }))
      ]"
      @update:model-value="emit('patch', { shoot: $event })"
    />
    <v-text-field
      :model-value="filters.q"
      label="Поиск группы"
      aria-label="Поиск группы"
      clearable
      prepend-inner-icon="mdi-magnify"
      @update:model-value="emit('patch', { q: $event ?? '' })"
    />
    <v-select
      :model-value="filters.state"
      :items="states"
      label="Состояние приёма"
      aria-label="Состояние приёма"
      @update:model-value="emit('patch', { state: $event })"
    />
    <v-btn variant="text" @click="emit('reset')">Сбросить</v-btn>
  </section>
</template>
<style scoped>
.dash-filters {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
  gap: 16px;
  align-items: start;
  margin: 24px 0 8px;
}
.dash-filters > * {
  min-width: 0;
}
@media (max-width: 1200px) {
  .dash-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .dash-filters {
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }
  .dash-filters > .v-btn {
    justify-self: start;
  }
}
</style>
