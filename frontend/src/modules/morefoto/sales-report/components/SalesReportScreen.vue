<script setup lang="ts">
import { computed } from 'vue';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import { levelKind, reportFigures, reportTitle, rowsTitle } from '../rules';
import { useSalesReport } from '../useSalesReport';
import ReportChildrenTable from './ReportChildrenTable.vue';
import ReportOrdersTable from './ReportOrdersTable.vue';
import ReportRowsTable from './ReportRowsTable.vue';

// E10: the sales of the staff member's area — children of a group or groups, shoots and institutions — and its PDF.
const { report, loading, error, crumbs, downloading, downloadError, load, rowHref, download } = useSalesReport();
const figures = computed(() => (report.value ? reportFigures(report.value.totals) : []));
const area = computed(() => {
  const value = report.value;
  if (!value || 'group' !== value.level) return 'Оплаченные заказы родителей по вашей области';
  return [value.institution?.name, value.shoot ? `съёмка «${value.shoot.name}»` : ''].filter(Boolean).join(' · ');
});
const levelKey = computed(() => [report.value?.level, report.value?.institution?.id, report.value?.shoot?.id].join(':'));
const number = new Intl.NumberFormat('ru-RU');
</script>

<template>
  <MfBreadcrumbs v-if="crumbs.length > 1" :items="crumbs" />
  <header class="mf-page-heading report-heading">
    <div class="report-heading__text">
      <p class="mf-eyebrow">{{ report ? levelKind(report.level) : 'ОТЧЁТ О ПРОДАЖАХ' }}</p>
      <h1 data-testid="report-title">{{ report ? reportTitle(report) : 'Отчёты' }}</h1>
      <p class="mf-muted">{{ area }}</p>
    </div>
    <v-btn
      color="primary"
      prepend-icon="mdi-download-outline"
      :loading="downloading"
      :disabled="!report"
      data-testid="report-pdf"
      @click="download"
      >Скачать PDF</v-btn
    >
  </header>
  <v-alert v-if="downloadError" type="error" variant="tonal" class="mb-4" role="alert">{{ downloadError }}</v-alert>

  <div v-if="loading && !report" class="report-state" role="status">
    <v-progress-circular indeterminate color="primary" />
    <p>Собираем отчёт…</p>
  </div>
  <v-alert v-else-if="error" type="error" variant="tonal" role="alert">
    {{ error }}
    <div class="mt-3"><v-btn variant="outlined" @click="load">Повторить</v-btn></div>
  </v-alert>
  <template v-else-if="report">
    <dl class="report-figures" :aria-busy="loading" data-testid="report-figures">
      <div v-for="figure in figures" :key="figure.key" :data-testid="'report-figure-' + figure.key">
        <dt>{{ figure.label }}</dt>
        <dd>{{ figure.value }}</dd>
      </div>
    </dl>

    <section class="report-section" aria-labelledby="report-rows-title">
      <h2 id="report-rows-title">{{ rowsTitle(report.level) }}</h2>
      <!-- A new level starts with its own sort and first page. -->
      <ReportChildrenTable v-if="'group' === report.level" :key="report.group?.id" :items="report.children" />
      <ReportRowsTable v-else :key="levelKey" :report="report" :title="rowsTitle(report.level)" :row-href="rowHref" />
    </section>

    <section v-if="'group' === report.level" class="report-section" aria-labelledby="report-orders-title">
      <h2 id="report-orders-title">Заказы с ФИО ребёнка</h2>
      <p class="mf-muted">Покупатель указал имя и фамилию для заказа целиком. В одном заказе могут быть фото нескольких детей.</p>
      <ReportOrdersTable :items="report.orders" />
    </section>

    <section v-if="report.products.length" class="report-section" aria-labelledby="report-formats-title">
      <h2 id="report-formats-title">Печать по форматам</h2>
      <ul class="report-formats">
        <li v-for="product in report.products" :key="product.id" data-testid="report-format">
          <span>{{ product.name }}</span>
          <strong>{{ number.format(product.prints) }} шт.</strong>
        </li>
      </ul>
    </section>

    <p class="mf-muted report-note">
      Проданным считается только подтверждённая оплата. Оплата после закрытия продаж ждёт решения и в суммы не входит. Бесплатные копии
      печати и подарок не считаются продажей. Данные покупателей в отчёт не включаются.
    </p>
  </template>
</template>

<style scoped>
.report-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--mf-space-4);
}
.report-heading__text {
  min-width: 0;
}
.report-heading h1 {
  overflow-wrap: anywhere;
}
.report-state {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-3);
  padding: var(--mf-space-8) 0;
}
.report-figures {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--mf-space-3);
  margin: 0 0 var(--mf-space-6);
}
.report-figures div {
  display: flex;
  flex-direction: column-reverse;
  justify-content: flex-end;
  gap: var(--mf-space-1);
  padding: var(--mf-space-3) var(--mf-space-4);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-primary-soft);
}
.report-figures dt {
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
}
.report-figures dd {
  margin: 0;
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-xl);
  font-weight: var(--mf-weight-semibold);
  color: var(--mf-color-primary-active);
  white-space: nowrap;
}
.report-section {
  display: grid;
  gap: var(--mf-space-3);
  margin-bottom: var(--mf-space-6);
}
.report-section h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.report-formats {
  display: grid;
  gap: var(--mf-space-1);
  max-width: 560px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.report-formats li {
  display: flex;
  justify-content: space-between;
  gap: var(--mf-space-3);
  padding: var(--mf-space-2) 0;
  border-bottom: 1px solid var(--mf-color-border);
}
.report-formats span {
  min-width: 0;
  overflow-wrap: anywhere;
}
.report-note {
  max-width: 720px;
  font-size: var(--mf-text-sm);
}
</style>
