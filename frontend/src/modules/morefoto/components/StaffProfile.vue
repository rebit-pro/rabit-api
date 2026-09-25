<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { isStaffRole, roleLabels } from '../types';
import ChangePasswordForm from './ChangePasswordForm.vue';
import AvatarEditor from '../avatar/AvatarEditor.vue';
import { avatarApi } from '../avatar/api';
import { avatarSeed } from '@/components/avatar/avatar';
import SupportContactCard from './SupportContactCard.vue';

const auth = useAuthStore();
const role = computed(() => (isStaffRole(auth.user?.role) ? roleLabels[auth.user.role] : 'Доступ не назначен'));
const seed = computed(() => avatarSeed(auth.user?.id, auth.user?.email));
</script>

<template>
  <header class="mf-page-heading">
    <h1>Профиль</h1>
    <p class="mf-muted">Учётная запись и доступ к кабинету</p>
  </header>
  <section class="mf-panel mf-profile">
    <h2>Данные учётной записи</h2>
    <AvatarEditor
      class="mt-5"
      :seed="seed"
      :name="auth.user?.name"
      :email="auth.user?.email"
      :avatar="auth.user?.avatar"
      :save="avatarApi.saveMine"
      :remove="avatarApi.removeMine"
      @changed="auth.reloadProfile()"
    />
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
  <section class="mf-panel mf-profile mt-6" aria-labelledby="profile-help-title">
    <h2 id="profile-help-title">Помощь</h2>
    <p class="mf-muted mt-2">Если нужен доступ к учреждению или группе либо что-то не работает — напишите организатору.</p>
    <SupportContactCard v-if="auth.user?.support" :contact="auth.user.support" class="mt-4" />
    <p v-else class="mf-muted mt-2">Контакт организатора пока не указан.</p>
  </section>
</template>
