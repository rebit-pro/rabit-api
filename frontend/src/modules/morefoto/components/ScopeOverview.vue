<script setup lang="ts">
import SettlementTotals from '../settlement/components/SettlementTotals.vue';
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useCabinetScope } from '../composables/useCabinetScope';
import { isStaffRole, roleHeadings } from '../types';
import DashboardBoard from '../dashboard/components/DashboardBoard.vue';

const auth = useAuthStore();
const { scope, loading, error, reload } = useCabinetScope();
const heading = computed(() => (isStaffRole(auth.user?.role) ? roleHeadings[auth.user.role] : 'Личный кабинет'));
const teacher = computed(() => auth.user?.role === 'teacher');
const institutions = computed(
  () =>
    scope.value?.institutions.map((institution) => ({
      ...institution,
      groups: scope.value?.groups.filter((group) => group.institutionId === institution.id) ?? []
    })) ?? []
);
</script>

<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">MORE FOTO</p>
    <h1>{{ heading }}</h1>
    <p class="mf-muted">Здравствуйте, {{ auth.user?.name }}. Здесь собраны доступные вам учреждения и группы.</p>
  </header>
  <div v-if="loading" aria-label="Загрузка кабинета" aria-busy="true" class="mf-card-grid">
    <v-skeleton-loader v-for="index in 2" :key="index" type="article" />
  </div>
  <v-alert v-else-if="error" type="error" variant="tonal" role="alert">
    {{ error }}
    <div class="mt-3">
      <v-btn variant="outlined" @click="reload">Повторить загрузку</v-btn>
    </div>
  </v-alert>
  <template v-else-if="scope">
    <section v-if="!scope.institutions.length" class="mf-panel mf-empty">
      <v-icon icon="mdi-folder-outline" size="40" color="primary" />
      <h2 class="mt-4">Пока нет назначенных учреждений</h2>
      <p class="mf-muted mt-2">Организатор выдаст доступ, когда назначит вас на съёмку.</p>
    </section>
    <DashboardBoard v-else-if="teacher" :scope="scope" teacher />
    <section v-else class="mf-card-grid" aria-label="Доступные учреждения">
      <article v-for="institution in institutions" :key="institution.id" class="mf-panel mf-institution-card">
        <v-icon icon="mdi-home-city-outline" color="primary" size="30" />
        <h2>{{ institution.name }}</h2>
        <p class="mf-muted">{{ institution.address }}</p>
        <SettlementTotals v-if="scope.totals?.[institution.id]" :totals="scope.totals[institution.id]!" compact />
        <p class="mf-card-count">Групп: {{ institution.groups.length }}</p>
        <v-btn :to="'/cabinet/institutions/' + institution.id" variant="outlined" color="primary">Открыть учреждение</v-btn>
      </article>
    </section>
  </template>
</template>
