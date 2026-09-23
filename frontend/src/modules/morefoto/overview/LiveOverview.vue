<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import MfDistribution from '@/components/viz/MfDistribution.vue';
import MfTimeline from '@/components/viz/MfTimeline.vue';
import MfQueue, { type QueueItem } from '@/components/viz/MfQueue.vue';
import MfStatus from '@/components/status/MfStatus.vue';
import MfEmptyState from '@/components/states/MfEmptyState.vue';
import { plural } from '@/components/viz/measures';
import { toneOf } from '@/components/status/tones';
import { isStaffRole, roleHeadings } from '../types';
import { CHART_CATEGORY } from '../ui/chartPalette';
import { groupStateTone, linkStatus, staffRequestTone } from '../ui/statusTone';
import { requestStatus } from '../handoff/display';
import { useLiveOverview } from './useLiveOverview';

const auth = useAuthStore();
const role = computed(() => (isStaffRole(auth.user?.role) ? auth.user.role : null));
const { overview, loading, error, reload } = useLiveOverview(() => role.value);
const heading = computed(() => (role.value ? roleHeadings[role.value] : 'Обзор'));
const groups = ['группа', 'группы', 'групп'] as const;

const scope = computed(() => (overview.value?.kind === 'scope' ? overview.value : null));
const teacher = computed(() => (overview.value?.kind === 'teacher' ? overview.value : null));
const queue = computed<QueueItem[]>(
  () =>
    scope.value?.queue.map((group) => {
      const status = linkStatus(group);
      return {
        id: group.groupId,
        title: group.name,
        subtitle: group.institutionName + ' · ' + group.shootName,
        status: { tone: status.tone, label: status.text },
        note: { tone: 'neutral', label: 'кадров ' + group.photoCount },
        to: '/cabinet/links'
      };
    }) ?? []
);
const stateSegments = computed(() => {
  const counts = scope.value?.counters.byState ?? { preparing: 0, open: 0, closed: 0 };
  return [
    // Two neutral states would merge in one bar: here «готовятся» is the work in progress (info).
    { key: 'preparing', label: 'Готовятся', value: counts.preparing, tone: 'info' as const },
    { key: 'open', label: 'Приём открыт', value: counts.open, tone: groupStateTone.open },
    { key: 'closed', label: 'Приём завершён', value: counts.closed, tone: groupStateTone.closed }
  ];
});
const finance = ['Оплачено', 'Подтверждённые возвраты', 'Итого после возвратов'];
</script>

<template>
  <header class="mf-page-heading">
    <h1>{{ heading }}</h1>
    <p class="mf-muted">Здравствуйте, {{ auth.user?.name }}. Здесь главное на сегодня по вашей области.</p>
  </header>
  <div v-if="loading && !overview" class="overview-tiles" aria-busy="true" aria-label="Загрузка обзора">
    <MfStatTile v-for="index in 4" :key="index" label="Загрузка" icon="mdi-timer-sand" loading />
  </div>
  <v-alert v-else-if="error" type="error" variant="tonal" role="alert">
    {{ error }}
    <div class="mt-3"><v-btn variant="outlined" @click="reload">Повторить</v-btn></div>
  </v-alert>
  <template v-else-if="scope">
    <section class="overview-tiles" aria-label="Ссылки и сроки групп" data-testid="overview-links">
      <MfStatTile
        label="Ждут проверки"
        :value="scope.waiting"
        :unit="plural(scope.waiting, groups)"
        :pastel="CHART_CATEGORY.groups"
        icon="mdi-clipboard-check-outline"
        to="/cabinet/links"
        :hint="scope.waiting ? 'проверьте фото, цены и списки' : ''"
      />
      <MfStatTile
        label="Готовы к передаче"
        :value="scope.counters.prepared"
        :unit="plural(scope.counters.prepared, groups)"
        :pastel="CHART_CATEGORY.groups"
        icon="mdi-send-check-outline"
        to="/cabinet/links"
      />
      <MfStatTile
        label="Приём открыт"
        :value="scope.counters.byState.open"
        :unit="plural(scope.counters.byState.open, groups)"
        :pastel="CHART_CATEGORY.groups"
        icon="mdi-link-variant"
        to="/cabinet/links"
      />
      <MfStatTile
        label="Закрываются за 3 дня"
        :value="scope.counters.closingSoon"
        :unit="plural(scope.counters.closingSoon, groups)"
        :pastel="CHART_CATEGORY.groups"
        icon="mdi-timer-sand"
        to="/cabinet/links"
        :hint="scope.counters.closingSoon ? 'напомните родителям о сроке' : ''"
        :hint-tone="scope.counters.closingSoon ? 'warning' : 'neutral'"
      />
    </section>
    <div class="overview-columns">
      <MfQueue
        class="mf-panel"
        title="Готовятся к передаче"
        :items="queue"
        :total="scope.counters.byState.preparing"
        all-to="/cabinet/links"
        empty-text="Все группы уже переданы родителям."
      />
      <section class="mf-panel" aria-label="Состояние приёма">
        <MfDistribution title="Группы по состоянию приёма" :segments="stateSegments" :unit-forms="['группы', 'групп', 'групп']" />
      </section>
    </div>
    <section class="overview-finance" aria-labelledby="overview-finance-title">
      <h2 id="overview-finance-title">Финансы</h2>
      <div class="overview-tiles">
        <MfStatTile v-for="label in finance" :key="label" :label="label" icon="mdi-cash" unavailable />
      </div>
    </section>
  </template>
  <template v-else-if="teacher">
    <MfEmptyState
      v-if="!teacher.groups.length"
      title="Групп пока нет"
      text="Организатор назначит вас ответственным за группу — она появится здесь."
      icon="mdi-account-group-outline"
      :category="CHART_CATEGORY.groups"
    />
    <section v-else class="overview-groups" aria-label="Мои группы">
      <article v-for="group in teacher.groups" :key="group.groupId" class="mf-panel overview-group" data-testid="overview-group">
        <header class="overview-group__header">
          <div>
            <h2>{{ group.name }}</h2>
            <p class="mf-muted">{{ group.institutionName }} · {{ group.shootName }}</p>
          </div>
          <MfStatus :tone="linkStatus(group).tone">{{ linkStatus(group).text }}</MfStatus>
        </header>
        <MfTimeline
          :sent-at="group.sentAt"
          :closes-at="group.closesAt"
          :delivery-at="group.deliveryAt"
          :now="teacher.referenceNow"
          :timezone="group.timezone"
        />
        <p class="mf-muted">Кадров {{ group.photoCount }} · Детей {{ group.childCount }}</p>
        <v-btn to="/cabinet/links" variant="outlined" class="overview-group__action">Ссылка и сроки</v-btn>
      </article>
    </section>
    <section v-if="teacher.requests" class="mf-panel overview-requests" aria-labelledby="overview-requests-title">
      <h2 id="overview-requests-title">Мои списки сотрудников</h2>
      <p class="overview-requests__pills">
        <MfStatus v-for="(count, status) in teacher.requests" :key="status" :tone="toneOf(staffRequestTone, status)"
          >{{ requestStatus[status] }}: {{ count }}</MfStatus
        >
      </p>
      <v-btn to="/cabinet/staff-requests" variant="text" color="primary">Открыть списки</v-btn>
    </section>
  </template>
</template>

<style scoped>
.overview-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
  gap: var(--mf-space-4);
}
.overview-columns {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
  gap: var(--mf-space-5);
  margin-top: var(--mf-space-6);
}
.overview-finance {
  display: grid;
  gap: var(--mf-space-3);
  margin-top: var(--mf-space-6);
}
.overview-finance h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.overview-groups {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr));
  gap: var(--mf-space-5);
}
.overview-group {
  display: grid;
  gap: var(--mf-space-4);
}
.overview-group__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.overview-group__header h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.overview-group__action {
  justify-self: start;
}
.overview-requests {
  display: grid;
  gap: var(--mf-space-3);
  margin-top: var(--mf-space-5);
}
.overview-requests > .v-btn {
  justify-self: start;
}
.overview-requests__pills {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
  margin: 0;
}
@media (max-width: 959px) {
  .overview-columns {
    grid-template-columns: 1fr;
  }
}
</style>
