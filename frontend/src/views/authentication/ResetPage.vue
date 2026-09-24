<script setup lang="ts">
import { shallowRef, useTemplateRef } from 'vue';
import { useRoute } from 'vue-router';
import AccessShell from './AccessShell.vue';
import AccessLinkProblem from './authForms/AccessLinkProblem.vue';
import NewPasswordFields from './authForms/NewPasswordFields.vue';
import { accessApi } from '@/api/auth';
import { apiErrorCode, authErrorText } from '@/api/authErrors';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();
const token = String(route.params.token ?? '');
const form = useTemplateRef('form');
const problem = shallowRef('');
const password = shallowRef('');
const confirmation = shallowRef('');
const busy = shallowRef(false);
const error = shallowRef('');

async function submit(): Promise<void> {
  if (busy.value) return;
  const result = await form.value?.validate();
  if (!result?.valid) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.startSession(await accessApi.confirmPasswordReset(token, password.value));
  } catch (cause) {
    const code = apiErrorCode(cause);
    if (code?.startsWith('LINK_')) problem.value = code;
    else error.value = authErrorText(cause, 'Не удалось сохранить пароль. Попробуйте ещё раз.');
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <AccessShell
    title="Новый пароль"
    description="После сохранения прежний пароль перестанет действовать, а кабинет откроется на этом устройстве."
  >
    <AccessLinkProblem v-if="problem" :code="problem" purpose="reset" />
    <v-form v-else ref="form" class="mt-6 mf-form-fields" novalidate :disabled="busy" @submit.prevent="submit">
      <v-alert v-if="error" type="error" variant="tonal" role="alert" data-testid="access-error">{{ error }}</v-alert>
      <NewPasswordFields v-model:password="password" v-model:confirmation="confirmation" :disabled="busy" />
      <v-btn type="submit" block :loading="busy">Сохранить пароль и войти</v-btn>
    </v-form>
  </AccessShell>
</template>
