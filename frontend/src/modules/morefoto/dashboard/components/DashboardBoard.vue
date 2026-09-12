<script setup lang="ts">
import { computed } from 'vue';
import type { ScopeSnapshot } from '../../types';
import { filterGroups, sumGroups, visibleRequests } from '../rules';
import { useDashboardFilters } from '../useDashboardFilters';
import DashboardFilters from './DashboardFilters.vue';
import DashboardGroups from './DashboardGroups.vue';
import TeacherRequests from './TeacherRequests.vue';
import CuratorContact from './CuratorContact.vue';
import SettlementTotals from '../../settlement/components/SettlementTotals.vue';
const props = defineProps<{
  readonly scope: ScopeSnapshot;
  readonly institutionId?: string;
  readonly teacher?: boolean;
}>();
const { filters, patch, reset } = useDashboardFilters();
const assigned = computed(() => props.scope.groups.filter((g) => !props.institutionId || g.institutionId === props.institutionId));
const shoots = computed(() => props.scope.shoots.filter((s) => !props.institutionId || s.institutionId === props.institutionId));
const groups = computed(() => filterGroups(assigned.value, filters.value));
const totals = computed(() => (props.scope.groupTotals ? sumGroups(groups.value, props.scope.groupTotals) : null));
const active = computed(() => !!(filters.value.q || filters.value.shoot || filters.value.state));
const requests = computed(() => visibleRequests(props.scope.dashboard?.requests ?? [], groups.value, filters.value.shoot));
const returnQuery = computed(() => ({
  ...filters.value,
  origin: props.institutionId ?? ''
}));
const institutions = computed(() => props.scope.institutions.filter((i) => !props.institutionId || i.id === props.institutionId));
</script>
<template>
  <div data-testid="dashboard-board">
    <section class="mf-panel">
      <h2>{{ teacher ? 'Работа с группами' : 'Сводка по группам' }}</h2>
      <p class="mf-muted mt-2">
        {{
          teacher
            ? 'Проверьте передачу ссылок, сроки и ответы куратора по спискам сотрудников.'
            : 'Выберите съёмку или состояние приёма, чтобы сравнить группы.'
        }}
      </p>
      <DashboardFilters :filters="filters" :shoots="shoots" @patch="patch" @reset="reset" />
      <p role="status" class="mf-muted">Показано групп: {{ groups.length }} из {{ assigned.length }}</p>
      <template v-if="totals">
        <h3 class="mt-5">
          {{ active ? 'Итог по выбранным группам' : 'Итог учреждения' }}
        </h3>
        <SettlementTotals :totals="totals" compact />
        <p class="mf-muted">
          Только оплаченные заказы. Подтверждённые возвраты вычтены; возвраты в обработке и ошибки итог не меняют. Заказы сотрудников
          включены один раз.
        </p>
      </template>
      <p v-if="!shoots.length" class="mt-5">Съёмки пока не созданы.</p>
      <p v-else-if="!assigned.length" class="mt-5">Группы пока не назначены. Обратитесь к организатору.</p>
      <div v-else-if="!groups.length" class="mt-5">
        <h3>Группы не найдены</h3>
        <p class="mf-muted mt-2">Измените поиск или сбросьте фильтры.</p>
      </div>
    </section>
    <DashboardGroups :scope="scope" :groups="groups" :return-query="returnQuery" />
    <TeacherRequests v-if="teacher && assigned.length" :scope="scope" :requests="requests" :shoot="filters.shoot" />
    <CuratorContact
      v-for="institution in institutions"
      :key="institution.id"
      :institution-name="institution.name"
      :contact="scope.dashboard?.curators[institution.id]"
    />
  </div>
</template>
