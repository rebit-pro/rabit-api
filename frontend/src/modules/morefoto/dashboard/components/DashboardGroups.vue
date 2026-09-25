<script setup lang="ts">
import { computed } from 'vue';
import type { ScopeSnapshot, Group } from '../../types';
import { sumGroups } from '../rules';
import { money } from '../../commerce/money';
import GroupSummary from './GroupSummary.vue';
const props = defineProps<{
  readonly scope: ScopeSnapshot;
  readonly groups: Group[];
  readonly returnQuery: Record<string, string>;
}>();
const shoots = computed(() =>
  props.scope.shoots
    .filter((s) => props.groups.some((g) => g.shootId === s.id))
    .map((s) => {
      const groups = props.groups.filter((g) => g.shootId === s.id);
      return {
        ...s,
        groups,
        totals: props.scope.groupTotals ? sumGroups(groups, props.scope.groupTotals) : null
      };
    })
);
</script>
<template>
  <section v-for="shoot in shoots" :key="shoot.id" class="dashboard-shoot" :data-testid="'shoot-summary-' + shoot.id">
    <header class="dashboard-shoot__heading">
      <div>
        <p class="mf-muted">
          {{ scope.institutions.find((i) => i.id === shoot.institutionId)?.name }}
        </p>
        <h2>{{ shoot.name }}</h2>
        <p v-if="shoot.date" class="mf-muted mt-2">Дата съёмки: {{ shoot.date.split('-').reverse().join('.') }}</p>
      </div>
      <p v-if="shoot.totals" class="dashboard-shoot__total">
        По показанным группам<br /><strong>Оплаченных заказов: {{ shoot.totals.paidCount }} · {{ money(shoot.totals.net) }}</strong>
      </p>
    </header>
    <div class="dashboard-groups">
      <GroupSummary
        v-for="group in shoot.groups"
        :key="group.id"
        :group="group"
        :work="scope.dashboard?.groups[group.id]"
        :totals="scope.groupTotals?.[group.id]"
        :group-to="{ path: '/cabinet/groups/' + group.id, query: returnQuery }"
      />
    </div>
  </section>
</template>
<style scoped>
.dashboard-shoot {
  margin-top: 32px;
}
.dashboard-shoot__heading {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: space-between;
  align-items: end;
  margin-bottom: 16px;
}
.dashboard-shoot__heading > div {
  min-width: 0;
}
.dashboard-shoot__heading h2 {
  overflow-wrap: anywhere;
  margin-top: 6px;
}
.dashboard-shoot__total {
  color: var(--mf-color-text-secondary);
  line-height: 1.8;
}
.dashboard-shoot__total strong {
  color: var(--mf-color-text);
}
.dashboard-groups {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}
@media (max-width: 1100px) {
  .dashboard-groups {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
