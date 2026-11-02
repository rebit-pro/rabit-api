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
  <v-dialog
    :model-value="pending.length > 0"
    persistent
    max-width="520"
    content-class="morefoto-app"
    aria-labelledby="staff-consent-title"
    aria-describedby="staff-consent-description"
  >
    <v-card class="mf-panel staff-consent" data-testid="staff-consent-dialog">
      <h2 id="staff-consent-title" class="staff-consent__title">Согласие на обработку персональных данных</h2>
      <p id="staff-consent-description" class="mf-muted staff-consent__description">
        Для работы в кабинете нужно ваше согласие на обработку персональных данных. Без него доступ в кабинет не предоставляется.
      </p>
      <ConsentField
        v-for="document in pending"
        :key="document.code"
        class="staff-consent__field"
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
.staff-consent {
  padding: var(--mf-space-6);
}
.staff-consent__title {
  margin: 0;
  font-size: var(--mf-text-xl);
  font-weight: var(--mf-weight-semibold);
  line-height: var(--mf-leading-snug);
  overflow-wrap: anywhere;
  text-wrap: balance;
}
.staff-consent__description {
  margin: var(--mf-space-3) 0 var(--mf-space-4);
}
.staff-consent__field :deep(.v-label) {
  white-space: normal;
  line-height: var(--mf-leading-normal);
}
.staff-consent__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-3);
  justify-content: flex-end;
  margin-top: var(--mf-space-6);
}
@media (max-width: 480px) {
  .staff-consent {
    padding: var(--mf-space-5);
  }
  .staff-consent__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
