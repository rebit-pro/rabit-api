<script setup lang="ts">
import { shallowRef, useTemplateRef } from 'vue';
import NewPasswordFields from '@/views/authentication/authForms/NewPasswordFields.vue';
import { accessApi } from '@/api/auth';
import { authErrorText } from '@/api/authErrors';
import { isMockApiEnabled } from '@/mocks/config';

const form = useTemplateRef('form');
const current = shallowRef('');
const password = shallowRef('');
const confirmation = shallowRef('');
const busy = shallowRef(false);
const done = shallowRef(false);
const error = shallowRef('');

async function submit(): Promise<void> {
  if (busy.value) return;
  const result = await form.value?.validate();
  if (!result?.valid) return;
  busy.value = true;
  done.value = false;
  error.value = '';
  try {
    if (!isMockApiEnabled) await accessApi.changePassword(current.value, password.value);
    done.value = true;
    // v-form reset() would set the models to null; the rules expect strings.
    current.value = '';
    password.value = '';
    confirmation.value = '';
    form.value?.resetValidation();
  } catch (cause) {
    error.value = authErrorText(cause, 'Не удалось сменить пароль. Попробуйте ещё раз.');
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <section class="mf-panel mf-profile mt-6" aria-labelledby="security-title" data-testid="profile-security">
    <h2 id="security-title">Безопасность</h2>
    <p class="mf-muted mt-2">
      Смена пароля не завершает текущую сессию. Забыли пароль — выйдите и восстановите доступ по ссылке на странице входа.
    </p>
    <v-alert v-if="done" type="success" variant="tonal" role="status" class="mt-4" data-testid="security-done">Пароль изменён.</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-4" data-testid="security-error">{{ error }}</v-alert>
    <v-form ref="form" class="mf-form-fields mt-4 security-form" novalidate :disabled="busy" @submit.prevent="submit">
      <v-text-field
        v-model="current"
        label="Текущий пароль"
        aria-label="Текущий пароль"
        type="password"
        autocomplete="current-password"
        :rules="[(value: string) => value.length > 0 || 'Введите текущий пароль']"
        required
      />
      <NewPasswordFields v-model:password="password" v-model:confirmation="confirmation" :disabled="busy" />
      <v-btn type="submit" :loading="busy" class="security-submit">Сменить пароль</v-btn>
    </v-form>
  </section>
</template>

<style scoped>
.security-form {
  max-width: 480px;
}
.security-submit {
  justify-self: start;
}
</style>
