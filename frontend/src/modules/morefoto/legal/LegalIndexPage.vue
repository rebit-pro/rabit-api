<script setup lang="ts">
import { computed } from 'vue';
import { documentPath, formatEffectiveDate } from './rules';
import { useLegalCatalog } from './useLegalCatalog';
const { catalog, failed, reload } = useLegalCatalog();
const seller = computed(() => catalog.value?.seller ?? null);
</script>
<template>
  <main class="mf-main legal-page">
    <header class="mf-page-heading">
      <p class="mf-eyebrow">МОРЕ ФОТО</p>
      <h1>Документы и реквизиты</h1>
    </header>
    <v-alert v-if="failed" type="warning" variant="tonal"
      >Не удалось загрузить документы. <v-btn variant="text" @click="reload">Повторить</v-btn></v-alert
    >
    <v-skeleton-loader v-else-if="!catalog" type="article" />
    <template v-else>
      <section class="mf-panel mb-6" aria-labelledby="legal-seller" data-testid="legal-seller">
        <h2 id="legal-seller">Продавец и оператор персональных данных</h2>
        <p v-if="!seller?.published" class="mf-muted">Реквизиты продавца будут опубликованы до начала продаж.</p>
        <dl v-else class="legal-requisites">
          <div>
            <dt>Продавец</dt>
            <dd>{{ seller.name }}</dd>
          </div>
          <div>
            <dt>ИНН</dt>
            <dd>{{ seller.inn }}</dd>
          </div>
          <div>
            <dt>ОГРНИП</dt>
            <dd>{{ seller.ogrnip }}</dd>
          </div>
          <div>
            <dt>Адрес</dt>
            <dd>{{ seller.address }}</dd>
          </div>
          <div>
            <dt>Email</dt>
            <dd>
              <a :href="'mailto:' + seller.email">{{ seller.email }}</a>
            </dd>
          </div>
          <div v-if="seller.phone">
            <dt>Телефон</dt>
            <dd>{{ seller.phone }}</dd>
          </div>
        </dl>
      </section>
      <section class="mf-panel" aria-labelledby="legal-documents">
        <h2 id="legal-documents">Документы</h2>
        <ul class="legal-documents">
          <li v-for="document in catalog.documents" :key="document.code">
            <RouterLink :to="documentPath(document.code)">{{ document.title }}</RouterLink>
            <span class="mf-muted">редакция от {{ formatEffectiveDate(document.effectiveFrom) }}</span>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
<style scoped>
.legal-page {
  max-width: 880px;
}
.legal-requisites {
  display: grid;
  gap: var(--mf-space-3);
}
.legal-requisites div {
  display: grid;
  grid-template-columns: 120px minmax(0, 1fr);
  gap: var(--mf-space-3);
}
.legal-requisites dt {
  color: var(--mf-color-text-secondary);
}
.legal-requisites dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.legal-documents {
  display: grid;
  gap: var(--mf-space-4);
  list-style: none;
  padding: 0;
}
.legal-documents li {
  display: grid;
  gap: var(--mf-space-1);
}
.legal-documents a {
  color: var(--mf-color-link);
  font-weight: var(--mf-weight-medium);
}
@media (max-width: 599px) {
  .legal-requisites div {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
  }
}
</style>
