<script setup lang="ts">
import AuthLogin from './authForms/AuthLogin.vue';
</script>

<template>
  <v-sheet tag="main" theme="MoreFotoTheme" color="background" class="login-page">
    <v-card tag="section" aria-labelledby="login-title" elevation="0" class="login-card">
      <h1 id="login-title" class="login-title">Вход в MoreFoto</h1>
      <p class="login-description">Введите email и пароль от личного кабинета</p>
      <AuthLogin />
    </v-card>
  </v-sheet>
</template>

<style scoped lang="scss">
.login-page {
  display: grid;
  place-items: center;
  min-height: 100vh;
  min-height: 100svh;
  padding: 24px 16px;
}

.login-card {
  width: 100%;
  max-width: 440px;
  padding: var(--mf-space-6);
  border: 1px solid #dce4ea;
  border-radius: 16px;
}

.login-title {
  color: #1e1e1e;
  font-size: 1.75rem;
  overflow-wrap: anywhere;
  font-weight: 600;
  line-height: 1.3;
}

.login-description {
  margin-top: 12px;
  color: #5e6872;
  font-size: var(--mf-text-control);
  line-height: 1.5;
}

@media (max-width: 479px) {
  .login-card {
    padding: var(--mf-space-4);
  }

  .login-title {
    font-size: 1.625rem;
  }
}
</style>
