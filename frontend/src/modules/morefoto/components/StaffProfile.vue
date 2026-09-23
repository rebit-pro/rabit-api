<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { isStaffRole, roleLabels } from '../types';
import ChangePasswordForm from './ChangePasswordForm.vue';

const auth = useAuthStore();
const role = computed(() => (isStaffRole(auth.user?.role) ? roleLabels[auth.user.role] : 'Доступ не назначен'));
</script>

<template>
  <header class="mf-page-heading">
    <h1>Профиль</h1>
    <p class="mf-muted">Учётная запись и доступ к кабинету</p>
  </header>
  <section class="mf-panel mf-profile">
    <h2>Данные учётной записи</h2>
    <dl>
      <div>
        <dt>Имя</dt>
        <dd>{{ auth.user?.name }}</dd>
      </div>
      <div>
        <dt>Email</dt>
        <dd>{{ auth.user?.email }}</dd>
      </div>
      <div>
        <dt>Роль</dt>
        <dd>{{ role }}</dd>
      </div>
    </dl>
    <p class="mf-muted">Для изменения учётных данных или назначений обратитесь к организатору.</p>
    <v-btn variant="outlined" color="primary" class="mt-6" @click="auth.logout()">Выйти из кабинета</v-btn>
  </section>
  <ChangePasswordForm />
</template>
