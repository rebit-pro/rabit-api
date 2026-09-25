<script setup lang="ts">
import { computed } from 'vue';
import { documentPath, sellerLine } from '../rules';
import { useLegalCatalog } from '../useLegalCatalog';
const { catalog } = useLegalCatalog();
const seller = computed(() => sellerLine(catalog.value?.seller ?? null));
</script>
<template>
  <footer class="mf-legal-footer" data-testid="legal-footer">
    <p>{{ seller }}</p>
    <nav aria-label="Юридические документы">
      <RouterLink :to="documentPath('privacy')">Политика обработки персональных данных</RouterLink>
      <RouterLink :to="documentPath('offer')">Оферта</RouterLink>
      <RouterLink to="/legal">Документы и реквизиты</RouterLink>
    </nav>
  </footer>
</template>
<style scoped>
.mf-legal-footer {
  display: grid;
  gap: var(--mf-space-2);
  justify-items: center;
  padding: var(--mf-space-6) var(--mf-space-4) var(--mf-space-8);
  border-top: 1px solid var(--mf-color-divider);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-normal);
  text-align: center;
}
.mf-legal-footer p {
  overflow-wrap: anywhere;
}
.mf-legal-footer nav {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2) var(--mf-space-5);
  justify-content: center;
}
.mf-legal-footer a {
  color: var(--mf-color-link);
}
</style>
