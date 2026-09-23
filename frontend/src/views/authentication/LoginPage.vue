<script setup lang="ts">
import AuthLogin from './authForms/AuthLogin.vue';
import MfLogo from '@/components/brand/MfLogo.vue';
</script>

<template>
  <main class="login-page">
    <div class="login-stack">
      <MfLogo variant="stacked" :size="48" class="login-logo" />
      <v-card tag="section" aria-labelledby="login-title" elevation="0" class="login-card">
        <h1 id="login-title" class="login-title">Вход в кабинет</h1>
        <p class="login-description">Введите email и пароль от личного кабинета</p>
        <AuthLogin />
      </v-card>
      <svg class="login-wave" viewBox="0 0 120 12" aria-hidden="true" focusable="false">
        <path d="M2 8 C 22 1, 38 13, 60 6 S 98 1, 118 8" fill="none" stroke-width="2" stroke-linecap="round" />
      </svg>
    </div>
  </main>
</template>

<style scoped lang="scss">
.login-page {
  display: grid;
  place-items: center;
  min-height: 100vh;
  min-height: 100svh;
  padding: var(--mf-space-6) var(--mf-space-4);
  background: linear-gradient(180deg, var(--mf-color-primary-soft), var(--mf-color-surface) 70%);
}

.login-stack {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-6);
  width: 100%;
  max-width: 440px;
}

.login-card {
  padding: var(--mf-space-8) var(--mf-space-6) var(--mf-space-6);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-lg);
  box-shadow: var(--mf-shadow-sm);
}

.login-title {
  color: var(--mf-color-text);
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-2xl);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-tight);
  line-height: var(--mf-leading-tight);
  overflow-wrap: anywhere;
}

.login-description {
  margin-top: var(--mf-space-3);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-base);
  line-height: var(--mf-leading-normal);
}

.login-wave {
  width: 120px;
  height: 12px;
  stroke: var(--mf-color-accent);
}

@media (max-width: 479px) {
  .login-card {
    padding: var(--mf-space-6) var(--mf-space-4) var(--mf-space-4);
  }

  .login-title {
    font-size: 1.625rem;
  }
}
</style>
