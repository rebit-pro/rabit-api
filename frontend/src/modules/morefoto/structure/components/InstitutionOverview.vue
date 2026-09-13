<script setup lang="ts">
import type { InstitutionDetail } from '../model';
defineProps<{ institution: InstitutionDetail }>();
</script>
<template>
  <section class="institution-overview" aria-label="Реквизиты и назначения" data-testid="institution-overview">
    <h2>Об учреждении</h2>
    <dl class="institution-facts">
      <div class="institution-address">
        <dt>Адрес</dt>
        <dd>{{ institution.address || 'Адрес не указан' }}</dd>
      </div>
      <div>
        <dt>Куратор</dt>
        <dd>{{ institution.curatorId === null ? 'Не назначен' : 'Сотрудник №' + institution.curatorId }}</dd>
      </div>
      <div>
        <dt>Заведующая</dt>
        <dd>{{ institution.headId === null ? 'Не назначена' : 'Сотрудник №' + institution.headId }}</dd>
      </div>
    </dl>
  </section>
  <section class="institution-summary" aria-label="Финансовая сводка" data-testid="institution-summary">
    <h2>Финансовая сводка</h2>
    <p v-if="institution.summary.availability === 'unavailable'" class="mf-muted" role="status">Финансовая сводка пока недоступна.</p>
  </section>
</template>
<style scoped>
.institution-overview,
.institution-summary {
  padding: 24px;
  border: 1px solid rgba(var(--v-theme-inputBorder), 0.35);
  border-radius: var(--mf-radius-field);
  background: rgb(var(--v-theme-surface));
}
.institution-summary {
  margin-top: 16px;
}
.institution-overview h2,
.institution-summary h2 {
  font-size: 20px;
}
.institution-summary p {
  margin-top: 12px;
}
.institution-facts {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 24px;
  margin-top: 20px;
}
.institution-facts dt {
  font-size: 14px;
  color: rgb(var(--v-theme-secondary));
}
.institution-facts dd {
  margin: 6px 0 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 800px) {
  .institution-facts {
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  .institution-address {
    grid-column: 1 / -1;
  }
}
@media (max-width: 600px) {
  .institution-overview,
  .institution-summary {
    padding: 20px;
  }
}
</style>
