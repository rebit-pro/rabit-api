<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { isStaffRole, roleLabels } from '../types';
import { cabinetNavigation } from '../layouts/navigation';

// First screen after an accepted invitation: the same sections as the menu, so the hints never promise a hidden screen.
const auth = useAuthStore();
const role = computed(() => (isStaffRole(auth.user?.role) ? auth.user.role : null));
const sections = computed(() =>
  cabinetNavigation({ role: role.value, permissions: auth.user?.permissions ?? [], demo: isMockApiEnabled }).flatMap((group) => group.items)
);
</script>

<template>
  <header class="mf-page-heading">
    <h1>Добро пожаловать{{ auth.user?.name ? ', ' + auth.user.name : '' }}!</h1>
    <p class="mf-muted">
      Пароль сохранён, кабинет открыт.<template v-if="role"> Ваша роль — {{ roleLabels[role].toLowerCase() }}.</template>
    </p>
  </header>
  <section class="mf-panel mf-profile" aria-labelledby="welcome-next">
    <h2 id="welcome-next">Что дальше</h2>
    <p class="mf-muted mt-2">Эти разделы доступны вашей роли, они же всегда есть в меню кабинета.</p>
    <ul class="welcome-sections">
      <li v-for="section in sections" :key="section.to">
        <router-link :to="section.to" class="welcome-section">
          <v-icon :icon="section.icon" size="20" aria-hidden="true" />
          <span>{{ section.title }}</span>
          <v-icon icon="mdi-chevron-right" size="20" aria-hidden="true" class="welcome-arrow" />
        </router-link>
      </li>
    </ul>
    <div class="welcome-actions">
      <v-btn :to="auth.homePath">Начать работу</v-btn>
      <v-btn to="/cabinet/profile" variant="outlined">Профиль</v-btn>
    </div>
  </section>
</template>

<style scoped>
.welcome-sections {
  display: grid;
  margin: var(--mf-space-5) 0 0;
  padding: 0;
  list-style: none;
  border-top: 1px solid var(--mf-color-border);
}
.welcome-section {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  min-height: var(--mf-touch-size);
  padding: var(--mf-space-2) 0;
  border-bottom: 1px solid var(--mf-color-border);
  color: var(--mf-color-text);
  font-weight: var(--mf-weight-medium);
  text-decoration: none;
}
.welcome-section:hover,
.welcome-section:focus-visible {
  color: var(--mf-color-link);
}
.welcome-arrow {
  margin-left: auto;
  color: var(--mf-color-text-secondary);
}
.welcome-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-3);
  margin-top: var(--mf-space-6);
}
</style>
