<script setup lang="ts">
import { demoAccounts, demoPassword } from '../mocks/fixtures';
import { roleLabels } from '../types';
import { galleryLinks } from '../gallery/links';

defineProps<{ disabled: boolean }>();
defineEmits<{ select: [value: { email: string; password: string }] }>();
const accounts = demoAccounts.filter((account) => account.id !== 105);
</script>

<template>
  <details class="demo-accounts">
    <summary>Демонстрационные доступы</summary>
    <p class="mf-muted mt-3">Выберите роль — поля заполнятся тестовыми данными.</p>
    <div class="demo-roles">
      <v-btn
        v-for="account in accounts"
        :key="account.id"
        variant="outlined"
        color="primary"
        :disabled="disabled"
        @click="$emit('select', { email: account.email, password: demoPassword })"
      >
        {{ roleLabels[account.role] }}
      </v-btn>
    </div>
    <p class="mf-muted mt-5 mb-2">Галереи без входа</p>
    <div class="demo-roles">
      <v-btn :to="'/g/' + galleryLinks['sun-stars']" variant="text" color="primary" :disabled="disabled">Галерея группы</v-btn
      ><v-btn :to="'/g/' + galleryLinks['school-1a']" variant="text" color="primary" :disabled="disabled">Галерея школы</v-btn>
    </div>
    <v-btn to="/demo/ui" variant="outlined" class="mt-4" :disabled="disabled">Образцы полей и кнопок</v-btn>
  </details>
</template>

<style scoped>
.demo-accounts {
  margin-top: 24px;
  font-size: 14px;
}
.demo-accounts summary {
  cursor: pointer;
  color: rgb(var(--v-theme-primary));
  padding: 12px 0;
}
.demo-roles {
  display: grid;
  gap: 8px;
  margin-top: 12px;
}
</style>
