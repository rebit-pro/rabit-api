<script setup lang="ts">
import { computed, nextTick, shallowRef, useTemplateRef } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import DemoAccounts from '@/modules/morefoto/components/DemoAccounts.vue';
import UiClearButton from '@/modules/morefoto/ui/components/UiClearButton.vue';

const auth = useAuthStore();
const route = useRoute();
const form = useTemplateRef('loginForm');
const errorMessage = useTemplateRef<{ $el: HTMLElement }>('errorMessage');
const email = shallowRef('');
const password = shallowRef('');
const showPassword = shallowRef(false);
const submitting = shallowRef(false);
const apiError = shallowRef('');
const attempted = shallowRef(false);
const emailEdited = shallowRef(false);
const passwordEdited = shallowRef(false);
const emailRules = [
  (value: string) => value.trim().length > 0 || 'Введите email',
  (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) || 'Проверьте email'
];
const passwordRules = [(value: string) => value.length > 0 || 'Введите пароль'];
const emailInvalid = computed(() => (attempted.value || emailEdited.value) && emailRules.some((rule) => rule(email.value) !== true));
const passwordInvalid = computed(
  () => (attempted.value || passwordEdited.value) && passwordRules.some((rule) => rule(password.value) !== true)
);
function updateEmail(value: string | null) {
  email.value = value ?? '';
  emailEdited.value = true;
}

function fillDemo(value: { email: string; password: string }): void {
  email.value = value.email;
  password.value = value.password;
  apiError.value = '';
}
async function submit(): Promise<void> {
  if (submitting.value) return;
  submitting.value = true;
  attempted.value = true;
  apiError.value = '';
  try {
    const result = await form.value?.validate();
    if (!result?.valid) {
      submitting.value = false;
      await nextTick();
      form.value?.$el.querySelector('[aria-invalid="true"]')?.focus();
      return;
    }
    await auth.login(email.value.trim(), password.value);
  } catch (error) {
    const response = error as { response?: { data?: { message?: string } } };
    apiError.value = response.response?.data?.message ?? 'Не удалось войти. Попробуйте ещё раз.';
  } finally {
    submitting.value = false;
  }
  if (apiError.value) {
    await nextTick();
    errorMessage.value?.$el.focus();
  }
}
</script>
<template>
  <v-alert v-if="route.query.reason === 'session-expired'" type="info" variant="tonal" class="mb-4">
    Сессия истекла. Войдите снова, чтобы продолжить.
  </v-alert>
  <v-form ref="loginForm" class="mt-6 mf-form-fields" @submit.prevent="submit" :disabled="submitting" novalidate>
    <v-text-field
      :model-value="email"
      label="Email"
      aria-label="Email"
      type="email"
      autocomplete="username"
      :rules="emailRules"
      :aria-invalid="emailInvalid || undefined"
      data-testid="login-email"
      required
      clearable
      @update:model-value="updateEmail"
    >
      <template #clear="{ props: clearProps }"
        ><UiClearButton v-bind="clearProps" label="Очистить email для входа" :disabled="submitting"
      /></template>
    </v-text-field>
    <v-text-field
      v-model="password"
      label="Пароль"
      aria-label="Пароль"
      autocomplete="current-password"
      :type="showPassword ? 'text' : 'password'"
      :rules="passwordRules"
      :aria-invalid="passwordInvalid || undefined"
      @update:model-value="passwordEdited = true"
      data-testid="login-password"
      required
    >
      <template #append-inner>
        <v-btn
          type="button"
          :icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
          :aria-pressed="showPassword"
          variant="text"
          color="secondary"
          :disabled="submitting"
          @click="showPassword = !showPassword"
        />
      </template>
    </v-text-field>
    <v-alert v-if="apiError" ref="errorMessage" type="error" variant="tonal" data-testid="login-api-error" role="alert" tabindex="-1">{{
      apiError
    }}</v-alert>
    <v-btn type="submit" color="primary" block :loading="submitting" :disabled="submitting" data-testid="login-submit">Войти</v-btn>
  </v-form>
  <DemoAccounts v-if="isMockApiEnabled" :disabled="submitting" @select="fillDemo" />
</template>
