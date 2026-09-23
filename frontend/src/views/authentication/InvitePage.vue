<script setup lang="ts">
import { onMounted, shallowRef, useTemplateRef } from 'vue';
import { useRoute } from 'vue-router';
import AccessShell from './AccessShell.vue';
import AccessLinkProblem from './authForms/AccessLinkProblem.vue';
import NewPasswordFields from './authForms/NewPasswordFields.vue';
import { accessApi, type InvitationPreview } from '@/api/auth';
import { apiErrorCode, authErrorText } from '@/api/authErrors';
import { useAuthStore } from '@/stores/auth';
import { formatMoment } from '@/modules/morefoto/handoff/display';

const route = useRoute();
const auth = useAuthStore();
const token = String(route.params.token ?? '');
const form = useTemplateRef('form');
const preview = shallowRef<InvitationPreview | null>(null);
const problem = shallowRef('');
const loading = shallowRef(true);
const password = shallowRef('');
const confirmation = shallowRef('');
const busy = shallowRef(false);
const error = shallowRef('');

onMounted(async () => {
  try {
    preview.value = await accessApi.invitation(token);
  } catch (cause) {
    problem.value = apiErrorCode(cause) ?? 'LINK_NOT_FOUND';
  } finally {
    loading.value = false;
  }
});

async function submit(): Promise<void> {
  if (busy.value) return;
  const result = await form.value?.validate();
  if (!result?.valid) return;
  busy.value = true;
  error.value = '';
  try {
    await auth.startSession(await accessApi.acceptInvitation(token, password.value));
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
  <AccessShell title="Приглашение в кабинет" description="Задайте пароль — после этого кабинет откроется сразу.">
    <v-progress-linear v-if="loading" indeterminate class="mt-6" aria-label="Проверяем ссылку" />
    <AccessLinkProblem v-else-if="problem" :code="problem" purpose="invite" />
    <v-form v-else ref="form" class="mt-6 mf-form-fields" novalidate :disabled="busy" @submit.prevent="submit">
      <dl class="access-facts" data-testid="invitation-preview">
        <div>
          <dt>Сотрудник</dt>
          <dd>{{ preview?.name }}</dd>
        </div>
        <div>
          <dt>Email</dt>
          <dd>{{ preview?.maskedEmail }}</dd>
        </div>
        <div>
          <dt>Ссылка действует до</dt>
          <dd>{{ formatMoment(preview?.expiresAt) }}</dd>
        </div>
      </dl>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" data-testid="access-error">{{ error }}</v-alert>
      <NewPasswordFields v-model:password="password" v-model:confirmation="confirmation" :disabled="busy" />
      <v-btn type="submit" block :loading="busy">Задать пароль и войти</v-btn>
    </v-form>
  </AccessShell>
</template>

<style scoped>
.access-facts {
  display: grid;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-surface-2);
  font-size: var(--mf-text-md);
}
.access-facts div {
  display: flex;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.access-facts dt {
  color: var(--mf-color-text-secondary);
}
.access-facts dd {
  margin: 0;
  font-weight: var(--mf-weight-medium);
  text-align: right;
  overflow-wrap: anywhere;
}
</style>
