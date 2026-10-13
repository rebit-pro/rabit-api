<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import ShootTable from './ShootTable.vue';
import StructureSearch from './StructureSearch.vue';
import { useStructurePage } from '../useStructurePage';
import type { Shoot } from '../model';
const auth = useAuthStore();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, error, query, reload, page, pageSize, resize } = useStructurePage({ kind: 'shoot' });
const shoots = computed(() => (snapshot.value?.items ?? []) as Shoot[]);
const emptyText = computed(() =>
  query.value.trim()
    ? 'По этому началу названия ничего не найдено.'
    : canManage.value
      ? 'Съёмки добавляются на странице учреждения.'
      : 'Организатор ещё не добавил съёмки или не назначил вам учреждения.'
);
</script>
<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
    <h1>Съёмки</h1>
    <p class="mf-muted">Все съёмки ваших учреждений в одном списке — от новых к прошлым</p>
  </header>
  <StructureSearch
    v-model="query"
    label="Поиск по началу названия съёмки"
    accessible-name="Поиск съёмок"
    :loading="loading"
    @search="reload(1)"
    @refresh="reload()"
  />
  <ShootTable
    :shoots="shoots"
    :total="snapshot?.meta.total ?? 0"
    :page="page"
    :page-size="pageSize"
    :loading="loading && !snapshot"
    :error="error"
    :can-manage="canManage"
    :empty-description="emptyText"
    @page="reload($event)"
    @page-size="resize"
    @retry="reload()"
  />
</template>
