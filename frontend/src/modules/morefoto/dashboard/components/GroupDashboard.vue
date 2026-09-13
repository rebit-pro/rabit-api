<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCabinetScope } from '../../composables/useCabinetScope';
import GroupSummary from './GroupSummary.vue';
import TeacherRequests from './TeacherRequests.vue';
import CuratorContact from './CuratorContact.vue';
import { visibleRequests } from '../rules';
const route = useRoute(),
  auth = useAuthStore();
const { scope, loading, error, reload } = useCabinetScope();
const group = computed(() => scope.value?.groups.find((g) => g.id === route.params.groupId));
const institution = computed(() => scope.value?.institutions.find((i) => i.id === group.value?.institutionId));
const teacher = computed(() => auth.user?.role === 'teacher');
const back = computed(() => ({
  path:
    !teacher.value && typeof route.query.origin === 'string' && scope.value?.institutions.some((i) => i.id === route.query.origin)
      ? '/cabinet/institutions/' + route.query.origin
      : '/cabinet/overview',
  query: Object.fromEntries(['q', 'shoot', 'state'].filter((k) => typeof route.query[k] === 'string').map((k) => [k, route.query[k]]))
}));
const requests = computed(() => (group.value ? visibleRequests(scope.value?.dashboard?.requests ?? [], [group.value]) : []));
</script>
<template>
  <RouterLink :to="back" class="mf-back">← К сводке</RouterLink>
  <p v-if="loading" role="status" class="mt-6">Загружаем группу…</p>
  <v-alert v-else-if="error" role="alert" type="error" variant="tonal" class="mt-6"
    >{{ error }}<v-btn variant="text" @click="reload">Повторить загрузку</v-btn></v-alert
  >
  <section v-else-if="!group" class="mf-panel mt-6">
    <h1>Группа недоступна</h1>
    <p class="mf-muted mt-3">Проверьте ссылку или обратитесь к организатору за доступом.</p>
  </section>
  <template v-else-if="scope">
    <header class="mf-page-heading">
      <p class="mf-eyebrow">{{ institution?.name }}</p>
      <h1>{{ group.name }}</h1>
      <p class="mf-muted">{{ group.shootName }}</p>
    </header>
    <GroupSummary
      :key="group.id"
      :group="group"
      :work="scope.dashboard?.groups[group.id]"
      :totals="scope.groupTotals?.[group.id]"
      :teacher="teacher"
      detail
    />
    <p v-if="scope.groupTotals" class="mf-muted mt-4">
      Учитываются оплаченные заказы и подтверждённые возвраты. Полный возврат уменьшает сумму; заказ остаётся в количестве оплаченных.
    </p>
    <TeacherRequests v-if="teacher" :requests="requests" :scope="scope" :shoot="group.shootId" />
    <CuratorContact v-if="institution" :institution-name="institution.name" :contact="scope.dashboard?.curators[institution.id]" />
  </template>
</template>
