<script setup lang="ts">
import { shallowRef, useTemplateRef } from 'vue';
import AccessShell from './AccessShell.vue';
import { accessApi } from '@/api/auth';
import { authErrorText } from '@/api/authErrors';
import { isMockApiEnabled } from '@/mocks/config';

const form = useTemplateRef('form');
const email = shallowRef('');
const busy = shallowRef(false);
const sent = shallowRef(false);
const error = shallowRef('');
const rules = [
  (value: string) => value.trim().length > 0 || 'Введите email',
  (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) || 'Проверьте email'
];

async function submit(): Promise<void> {
  if (busy.value) return;
  const result = await form.value?.validate();
  if (!result?.valid) return;
  busy.value = true;
  error.value = '';
  try {
    // The demo has no mail delivery; the answer must look the same as in the cabinet.
    if (!isMockApiEnabled) await accessApi.requestPasswordReset(email.value.trim());
    sent.value = true;
  } catch (cause) {
    error.value = authErrorText(cause, 'Не удалось отправить запрос. Проверьте соединение и повторите.');
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AccessShell title="Восстановление доступа" description="Укажите email, на который выдан доступ к кабинету.">
    <div v-if="sent" class="mt-6" data-testid="recover-sent">
      <v-alert type="success" variant="tonal" role="status">
        Если адрес зарегистрирован, мы отправили письмо со ссылкой. Ссылка для нового пароля действует 60 минут, приглашение — 7 дней.
        Проверьте и папку «Спам».
      </v-alert>
      <p v-if="isMockApiEnabled" class="mf-muted mt-4">В демонстрационной версии письма не отправляются.</p>
      <v-btn to="/login" variant="outlined" block class="mt-5">Вернуться ко входу</v-btn>
    </div>
    <v-form v-else ref="form" class="mt-6 mf-form-fields" novalidate :disabled="busy" @submit.prevent="submit">
      <v-alert v-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
      <v-text-field v-model="email" label="Email" aria-label="Email" type="email" autocomplete="username" :rules="rules" required />
      <v-btn type="submit" block :loading="busy">Отправить ссылку</v-btn>
      <v-btn to="/login" variant="text" block>Вернуться ко входу</v-btn>
    </v-form>
  </AccessShell>
</template>
