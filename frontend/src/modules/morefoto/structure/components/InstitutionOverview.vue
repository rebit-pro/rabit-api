<script setup lang="ts">
import { computed } from 'vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import { plural } from '@/components/viz/measures';
import type { InstitutionDetail } from '../model';
const props = defineProps<{ institution: InstitutionDetail }>();
/** The responsible staff by their names (DS-11), in one line with the address (#92). */
const people = computed(() => [
  { key: 'curator', role: 'Куратор', id: props.institution.curatorId, name: props.institution.curatorName },
  { key: 'head', role: 'Руководитель', id: props.institution.headId, name: props.institution.headName }
]);
const byState = computed(() => props.institution.groups.meta.summary?.byState ?? { preparing: 0, open: 0, closed: 0 });
const shoots = computed(() => props.institution.shoots.meta.total);
const groups = computed(() => props.institution.groups.meta.total);
</script>
<template>
  <section class="institution-overview" aria-label="Сводка учреждения" data-testid="institution-overview">
    <dl class="institution-facts mf-panel">
      <div class="institution-address">
        <dt>
          <v-icon icon="mdi-map-marker-outline" size="18" aria-hidden="true" />
          Адрес
        </dt>
        <dd>{{ institution.address || 'Адрес не указан' }}</dd>
      </div>
      <div v-for="person in people" :key="person.key" :data-testid="'institution-' + person.key">
        <dt>{{ person.role }}</dt>
        <dd v-if="person.id === null" class="institution-none">Не назначен</dd>
        <dd v-else class="institution-person">
          <MfAvatar :seed="String(person.id)" :name="person.name" :size="24" decorative />
          <span>{{ person.name ?? 'Имя не указано' }}</span>
        </dd>
      </div>
    </dl>
    <div class="institution-tiles">
      <MfStatTile
        label="Съёмки"
        :value="shoots"
        :unit="plural(shoots, ['съёмка', 'съёмки', 'съёмок'])"
        icon="mdi-camera-outline"
        :pastel="3"
      />
      <MfStatTile label="Группы готовятся" :value="byState.preparing" :unit="'из ' + groups" icon="mdi-progress-clock" :pastel="5" />
      <MfStatTile
        label="Приём открыт"
        :value="byState.open"
        :unit="plural(byState.open, ['группа', 'группы', 'групп'])"
        icon="mdi-cart-outline"
        :pastel="2"
      />
      <MfStatTile label="Финансы" icon="mdi-receipt-text-outline" unavailable />
    </div>
  </section>
</template>
<style scoped>
.institution-overview {
  display: grid;
  gap: var(--mf-space-4);
}
.institution-facts {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: var(--mf-space-5);
  padding: var(--mf-space-4) var(--mf-space-5);
}
.institution-facts dt {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
}
.institution-facts dd {
  margin: var(--mf-space-1) 0 0;
  overflow-wrap: anywhere;
}
.institution-person {
  display: flex;
  align-items: center;
  gap: var(--mf-space-2);
}
.institution-none {
  color: var(--mf-color-text-secondary);
}
.institution-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr));
  gap: var(--mf-space-4);
}
@media (max-width: 800px) {
  .institution-facts {
    grid-template-columns: 1fr 1fr;
  }
  .institution-address {
    grid-column: 1 / -1;
  }
}
</style>
