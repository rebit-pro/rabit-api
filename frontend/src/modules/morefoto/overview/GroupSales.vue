<script setup lang="ts">
import { computed, onMounted, shallowRef } from 'vue';
import { salesApi, type GroupSales } from './sales-api';
import { bundleNote, paidTotalText, printsText, salesFigures, salesLoadError } from './sales-rules';

// E9: the teacher sees what the parents of the group bought; the server counts paid orders only.
const props = defineProps<{ groupId: string }>();
const sales = shallowRef<GroupSales | null>(null);
const loading = shallowRef(false);
const error = shallowRef('');
const titleId = computed(() => 'group-sales-' + props.groupId);

async function load(): Promise<void> {
  loading.value = true;
  error.value = '';
  try {
    sales.value = await salesApi.group(props.groupId);
  } catch {
    error.value = salesLoadError;
  } finally {
    loading.value = false;
  }
}
onMounted(load);
</script>

<template>
  <section class="group-sales" :aria-labelledby="titleId" :aria-busy="loading" data-testid="group-sales">
    <h3 :id="titleId" class="group-sales__title">Продажи</h3>
    <v-skeleton-loader v-if="loading && !sales" type="text@3" />
    <template v-else-if="error">
      <p class="group-sales__error" role="alert">{{ error }}</p>
      <v-btn variant="outlined" size="small" class="group-sales__retry" @click="load">Повторить</v-btn>
    </template>
    <p v-else-if="sales && 0 === sales.paidOrders" class="mf-muted" data-testid="group-sales-empty">
      Продаж пока нет — здесь появятся оплаченные заказы родителей.
    </p>
    <template v-else-if="sales">
      <p class="group-sales__total">
        <span>Оплачено родителями</span>
        <strong data-testid="group-sales-total">{{ paidTotalText(sales) }}</strong>
      </p>
      <dl class="group-sales__figures">
        <div v-for="figure in salesFigures(sales)" :key="figure.key" :data-testid="'group-sales-' + figure.key">
          <dt>{{ figure.label }}</dt>
          <dd>{{ figure.value }}</dd>
        </div>
      </dl>
      <p v-if="sales.bundles" class="mf-muted group-sales__note" data-testid="group-sales-bundles">{{ bundleNote(sales.bundles) }}</p>
      <ul v-if="sales.products.length" class="group-sales__formats" aria-label="Печать по форматам">
        <li v-for="product in sales.products" :key="product.id" data-testid="group-sales-format">
          <span>{{ product.name }}</span>
          <strong>{{ printsText(product.prints) }}</strong>
        </li>
      </ul>
    </template>
  </section>
</template>

<style scoped>
.group-sales {
  display: grid;
  gap: var(--mf-space-3);
  padding-top: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-border);
}
.group-sales__title {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
}
.group-sales__total {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--mf-space-2);
  margin: 0;
}
.group-sales__total strong {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-xl);
  color: var(--mf-color-primary);
}
.group-sales__figures {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--mf-space-2);
  margin: 0;
}
.group-sales__figures div {
  padding: var(--mf-space-2) var(--mf-space-3);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-workspace);
}
.group-sales__figures dt {
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
}
.group-sales__figures dd {
  margin: 0;
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.group-sales__note {
  margin: 0;
  font-size: var(--mf-text-sm);
}
.group-sales__formats {
  display: grid;
  gap: var(--mf-space-1);
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--mf-text-sm);
}
.group-sales__formats li {
  display: flex;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.group-sales__formats span {
  min-width: 0;
  overflow-wrap: anywhere;
}
.group-sales__error {
  margin: 0;
  color: var(--mf-tone-danger-fg);
}
.group-sales__retry {
  justify-self: start;
}
</style>
