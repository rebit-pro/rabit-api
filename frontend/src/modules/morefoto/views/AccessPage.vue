<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
const auth = useAuthStore();
</script>
<template>
  <main class="mf-access">
    <section class="mf-panel">
      <h1>{{ auth.user?.role ? 'Недостаточно прав' : 'Доступ к кабинету не назначен' }}</h1>
      <p class="mf-muted mt-4">Обратитесь к организатору MoreFoto, чтобы уточнить доступ к этому разделу.</p>
      <v-btn v-if="auth.user?.role" :to="auth.homePath" color="primary" class="mt-6 mr-3">{{
        auth.homePath === '/cabinet/overview' ? 'К обзору' : 'В кабинет'
      }}</v-btn>
      <v-btn variant="outlined" color="primary" class="mt-6" @click="auth.logout()">Выйти</v-btn>
    </section>
  </main>
</template>
