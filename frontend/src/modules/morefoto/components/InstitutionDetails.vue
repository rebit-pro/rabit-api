<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useCabinetScope } from '../composables/useCabinetScope';
import DashboardBoard from '../dashboard/components/DashboardBoard.vue';

const route = useRoute();
const { scope, loading, error, reload } = useCabinetScope();
const institution = computed(() => scope.value?.institutions.find((item) => item.id === route.params.institutionId));
</script>

<template>
  <RouterLink to="/cabinet/overview" class="mf-back">← К обзору</RouterLink>
  <v-skeleton-loader v-if="loading" type="article, list-item-two-line" class="mt-6" />
  <v-alert v-else-if="error" type="error" variant="tonal" class="mt-6">
    {{ error }} <v-btn variant="text" @click="reload">Повторить загрузку</v-btn>
  </v-alert>
  <section v-else-if="!institution" class="mf-panel mf-empty mt-6">
    <h1>Учреждение недоступно</h1>
    <p class="mf-muted mt-3">Проверьте ссылку или обратитесь к организатору за доступом.</p>
  </section>
  <template v-else>
    <header class="mf-page-heading">
      <p class="mf-eyebrow">УЧРЕЖДЕНИЕ</p>
      <h1>{{ institution.name }}</h1>
      <p class="mf-muted">{{ institution.address }}</p>
    </header>
    <DashboardBoard v-if="scope" :scope="scope" :institution-id="institution.id" />
  </template>
</template>
