<script setup lang="ts">
import AuthLogin from './authForms/AuthLogin.vue';
import AccessShell from './AccessShell.vue';
</script>

<template>
  <AccessShell title="Вход в кабинет" description="Введите email и пароль от личного кабинета">
    <AuthLogin />
  </AccessShell>
</template>
