<script setup lang="ts">
import { onMounted, shallowRef } from 'vue';
import { COOKIE_NOTICE_KEY, documentPath } from '../rules';
// Only technical cookies and browser storage are used, so the notice informs and has nothing to refuse.
const visible = shallowRef(false);
onMounted(() => {
  try {
    visible.value = localStorage.getItem(COOKIE_NOTICE_KEY) === null;
  } catch {
    visible.value = true;
  }
});
function dismiss(): void {
  visible.value = false;
  try {
    localStorage.setItem(COOKIE_NOTICE_KEY, new Date().toISOString());
  } catch {
    // Without storage the notice simply appears again on the next visit.
  }
}
</script>
<template>
  <!-- In the page flow at the top: it never covers a button or a form, and disappears after «Понятно». -->
  <section v-if="visible" class="mf-cookie-notice" aria-label="Уведомление о cookie" data-testid="cookie-notice">
    <p>
      Мы используем только необходимые cookie и хранилище браузера — для входа, корзины и оформления заказа. Аналитику и рекламу не
      подключаем. Подробнее — в
      <RouterLink :to="documentPath('privacy')">политике обработки персональных данных</RouterLink>.
    </p>
    <v-btn size="small" color="primary" variant="flat" data-testid="cookie-notice-ok" @click="dismiss">Понятно</v-btn>
  </section>
</template>
<style scoped>
.mf-cookie-notice {
  display: flex;
  gap: var(--mf-space-4);
  align-items: center;
  justify-content: center;
  padding: var(--mf-space-3) var(--mf-space-4);
  border-bottom: 1px solid var(--mf-color-border);
  background: var(--mf-color-surface-2);
  color: var(--mf-color-text);
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-normal);
}
.mf-cookie-notice p {
  max-width: 880px;
}
.mf-cookie-notice a {
  color: var(--mf-color-link);
}
@media (max-width: 599px) {
  .mf-cookie-notice {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
