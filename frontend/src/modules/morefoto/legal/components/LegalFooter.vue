<script setup lang="ts">
import { computed } from 'vue';
import MfLogo from '@/components/brand/MfLogo.vue';
import { documentPath, sellerLine } from '../rules';
import { useLegalCatalog } from '../useLegalCatalog';
import { buyerGuidePath } from '../../guide/buyerGuide';
const { catalog } = useLegalCatalog();
const seller = computed(() => sellerLine(catalog.value?.seller ?? null));
</script>
<template>
  <!-- UI-DEC-07: brand and the buyer's navigation first, the seller's requisites below; #219: no developer credit. -->
  <footer class="mf-legal-footer" data-testid="legal-footer">
    <div class="mf-legal-footer__inner">
      <div class="mf-legal-footer__brand">
        <MfLogo :size="22" />
        <p>Сохраняем моменты детства</p>
      </div>
      <nav class="mf-legal-footer__nav" aria-label="Помощь покупателю">
        <p>Покупателям</p>
        <a :href="buyerGuidePath" data-testid="footer-buyer-guide">Как купить фото</a>
        <a href="/guide/faq/">Вопросы и ответы</a>
      </nav>
      <nav class="mf-legal-footer__nav" aria-label="Юридические документы">
        <p>Документы</p>
        <RouterLink :to="documentPath('offer')">Оферта</RouterLink>
        <RouterLink :to="documentPath('privacy')">Политика обработки персональных данных</RouterLink>
        <RouterLink to="/legal">Документы и реквизиты</RouterLink>
      </nav>
      <div class="mf-legal-footer__base">
        <p>{{ seller }}</p>
      </div>
    </div>
  </footer>
</template>
<style scoped>
.mf-legal-footer {
  border-top: 1px solid var(--mf-color-border);
  background: var(--mf-color-surface);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-normal);
}
.mf-legal-footer__inner {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1.2fr);
  gap: var(--mf-space-6) var(--mf-space-8);
  max-width: 1320px;
  margin: 0 auto;
  padding: var(--mf-space-8) var(--mf-space-10) var(--mf-space-6);
}
.mf-legal-footer__brand,
.mf-legal-footer__nav {
  display: grid;
  align-content: start;
  gap: var(--mf-space-2);
}
.mf-legal-footer__nav > p {
  color: var(--mf-color-text-tertiary);
  font-size: var(--mf-text-xs);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-caps);
  text-transform: uppercase;
}
.mf-legal-footer__nav a {
  justify-self: start;
  color: var(--mf-color-text);
  text-decoration: none;
}
.mf-legal-footer__nav a:hover,
.mf-legal-footer__nav a:focus-visible {
  color: var(--mf-color-link);
  text-decoration: underline;
}
.mf-legal-footer__base {
  display: flex;
  flex-wrap: wrap;
  grid-column: 1 / -1;
  justify-content: space-between;
  gap: var(--mf-space-2) var(--mf-space-6);
  padding-top: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-divider);
}
.mf-legal-footer__base p {
  overflow-wrap: anywhere;
}
@media (max-width: 767px) {
  .mf-legal-footer__inner {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: var(--mf-space-5) var(--mf-space-4);
    padding: var(--mf-space-6) var(--mf-space-4) var(--mf-space-5);
  }
  .mf-legal-footer__brand {
    grid-column: 1 / -1;
  }
}
</style>
