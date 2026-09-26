<script setup lang="ts">
import { onMounted, shallowRef } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { useAuthStore } from '@/stores/auth';
import { legalApi } from '../api';
import type { LegalDocument } from '../types';
import ConsentField from './ConsentField.vue';
// Staff invited before the consent existed, or facing a version that requires it again, accept it once here.
const auth = useAuthStore();
const pending = shallowRef<LegalDocument[]>([]);
const accepted = shallowRef(false);
const busy = shallowRef(false);
const error = shallowRef('');
onMounted(async () => {
  if (isMockApiEnabled) return;
  try {
    pending.value = await legalApi.pending();
  } catch {
    pending.value = [];
  }
});
async function accept(): Promise<void> {
  if (!accepted.value) {
    error.value = 'Отметьте согласие, чтобы продолжить работу в кабинете.';
    return;
  }
  busy.value = true;
  error.value = '';
  try {
    pending.value = await legalApi.accept(pending.value.map(({ code, version }) => ({ code, version })));
  } catch {
    error.value = 'Не удалось сохранить согласие. Обновите страницу и попробуйте ещё раз.';
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <v-dialog :model-value="pending.length > 0" persistent max-width="520" content-class="morefoto-app">
    <v-card class="mf-panel" data-testid="staff-consent-dialog">
      <h2 class="mb-3">Согласие на обработку персональных данных</h2>
      <p class="mf-muted mb-4">
        Для работы в кабинете нужно ваше согласие на обработку персональных данных. Без него доступ в кабинет не предоставляется.
      </p>
      <ConsentField
        v-for="document in pending"
        :key="document.code"
        v-model="accepted"
        :document="document"
        name="staff-consent"
        before="Даю"
        link="согласие на обработку персональных данных"
        :error-messages="error"
        :disabled="busy"
      />
      <div class="staff-consent__actions">
        <v-btn variant="text" color="secondary" :disabled="busy" @click="auth.logout()">Выйти</v-btn>
        <v-btn color="primary" :loading="busy" data-testid="staff-consent-accept" @click="accept">Принять</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.staff-consent__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-3);
  justify-content: flex-end;
  margin-top: var(--mf-space-4);
}
</style>
