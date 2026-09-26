<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useHead } from '@unhead/vue';
import { legalApi } from './api';
import { documentPath, formatEffectiveDate } from './rules';
import type { LegalDocumentCode, LegalDocumentText } from './types';
const route = useRoute();
const text = shallowRef<LegalDocumentText | null>(null);
const problem = shallowRef('');
async function load(): Promise<void> {
  text.value = null;
  problem.value = '';
  try {
    text.value = await legalApi.document(
      String(route.params.code) as LegalDocumentCode,
      route.params.version ? String(route.params.version) : undefined
    );
  } catch (cause) {
    const status = (cause as { response?: { status?: number } }).response?.status;
    problem.value = status === 404 ? 'Такого документа или редакции нет.' : 'Не удалось загрузить документ.';
  }
}
watch(() => [route.params.code, route.params.version], load, { immediate: true });
useHead({ title: () => (text.value ? text.value.document.title + ' — Море фото' : 'Документ — Море фото') });
</script>
<template>
  <main class="mf-main legal-page">
    <RouterLink to="/legal" class="mf-back">← Документы и реквизиты</RouterLink>
    <v-alert v-if="problem" type="warning" variant="tonal" class="mt-5"
      >{{ problem }} <v-btn variant="text" @click="load">Повторить</v-btn></v-alert
    >
    <v-skeleton-loader v-else-if="!text" type="heading, article" class="mt-5" />
    <article v-else class="legal-document" data-testid="legal-document">
      <header class="mf-page-heading">
        <h1>{{ text.document.title }}</h1>
        <p class="mf-muted">Редакция от {{ formatEffectiveDate(text.document.effectiveFrom) }}</p>
      </header>
      <v-alert v-if="!text.current" type="info" variant="tonal" class="mb-6" data-testid="legal-archived"
        >Это прежняя редакция. <RouterLink :to="documentPath(text.document.code)">Открыть действующую</RouterLink></v-alert
      >
      <template v-for="(block, index) in text.blocks" :key="index">
        <h2 v-if="block.type === 'heading' && block.level === 2">{{ block.text }}</h2>
        <h3 v-else-if="block.type === 'heading'">{{ block.text }}</h3>
        <ul v-else-if="block.type === 'list'">
          <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
        </ul>
        <p v-else>{{ block.text }}</p>
      </template>
      <details v-if="text.versions.length > 1" class="legal-versions">
        <summary>Все редакции</summary>
        <ul>
          <li v-for="version in text.versions" :key="version.version">
            <RouterLink :to="documentPath(version.code, version.version)"
              >Редакция от {{ formatEffectiveDate(version.effectiveFrom) }}</RouterLink
            >
          </li>
        </ul>
      </details>
    </article>
  </main>
</template>
<style scoped>
.legal-page {
  max-width: 880px;
}
.legal-document {
  font-size: var(--mf-text-base);
  line-height: 1.7;
  overflow-wrap: anywhere;
}
.legal-document h2 {
  margin: var(--mf-space-8) 0 var(--mf-space-3);
  font-family: var(--mf-font-display);
  font-size: 1.25rem;
  line-height: 1.35;
}
.legal-document h3 {
  margin: var(--mf-space-6) 0 var(--mf-space-2);
  font-size: 1.0625rem;
}
.legal-document p,
.legal-document ul {
  margin: 0 0 var(--mf-space-3);
}
.legal-document ul {
  padding-left: var(--mf-space-6);
}
.legal-document li + li {
  margin-top: var(--mf-space-1);
}
.legal-versions {
  margin-top: var(--mf-space-8);
  padding-top: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-divider);
}
.legal-versions summary {
  cursor: pointer;
  color: var(--mf-color-link);
}
.legal-document a,
.legal-versions a {
  color: var(--mf-color-link);
}
</style>
