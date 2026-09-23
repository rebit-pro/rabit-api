<script setup lang="ts">
import { computed } from 'vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import MfDistribution from '@/components/viz/MfDistribution.vue';
import { groupStateSegments } from '../../ui/groupStates';
import type { InstitutionDetail } from '../model';
const props = defineProps<{ institution: InstitutionDetail }>();
/** The responsible staff by their names (DS-11); an assignment without a name still says someone is assigned. */
const people = computed(() => [
  { key: 'curator', role: 'Куратор', id: props.institution.curatorId, name: props.institution.curatorName, none: 'Не назначен' },
  { key: 'head', role: 'Руководитель учреждения', id: props.institution.headId, name: props.institution.headName, none: 'Не назначен' }
]);
const states = computed(() => {
  const counts = props.institution.groups.meta.summary?.byState;
  return counts && props.institution.groups.meta.total ? groupStateSegments(counts) : null;
});
</script>
<template>
  <section class="institution-overview" aria-label="Реквизиты и назначения" data-testid="institution-overview">
    <h2>Об учреждении</h2>
    <dl class="institution-facts">
      <div class="institution-address">
        <dt>Адрес</dt>
        <dd>{{ institution.address || 'Адрес не указан' }}</dd>
      </div>
      <div v-for="person in people" :key="person.key" :data-testid="'institution-' + person.key">
        <dt>{{ person.role }}</dt>
        <dd v-if="person.id === null" class="institution-none">{{ person.none }}</dd>
        <dd v-else class="institution-person">
          <MfAvatar :seed="String(person.id)" :name="person.name" :size="24" decorative />
          <span>{{ person.name ?? 'Имя не указано' }}</span>
        </dd>
      </div>
    </dl>
    <MfDistribution
      v-if="states"
      class="institution-states"
      title="Группы по состоянию приёма"
      :segments="states"
      :unit-forms="['группы', 'групп', 'групп']"
    />
  </section>
  <section class="institution-summary" aria-label="Финансовая сводка" data-testid="institution-summary">
    <h2>Финансовая сводка</h2>
    <p v-if="institution.summary.availability === 'unavailable'" class="mf-muted" role="status">Финансовая сводка пока недоступна.</p>
  </section>
</template>
<style scoped>
.institution-overview,
.institution-summary {
  padding: var(--mf-space-6);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface);
}
.institution-summary {
  margin-top: var(--mf-space-4);
}
.institution-overview h2,
.institution-summary h2 {
  font-size: var(--mf-text-lg);
}
.institution-summary p {
  margin-top: var(--mf-space-3);
}
.institution-facts {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: var(--mf-space-6);
  margin-top: var(--mf-space-5);
}
.institution-facts dt {
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
}
.institution-facts dd {
  margin: var(--mf-space-2) 0 0;
  white-space: pre-wrap;
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
.institution-states {
  margin-top: var(--mf-space-6);
}
@media (max-width: 800px) {
  .institution-facts {
    grid-template-columns: 1fr 1fr;
    gap: var(--mf-space-5);
  }
  .institution-address {
    grid-column: 1 / -1;
  }
}
@media (max-width: 600px) {
  .institution-overview,
  .institution-summary {
    padding: var(--mf-space-5);
  }
  .institution-facts {
    grid-template-columns: 1fr;
  }
}
</style>
