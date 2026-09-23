<script setup lang="ts">
import MfStatus from '@/components/status/MfStatus.vue';
import { money } from '../../commerce/money';
import type { CatalogProduct } from '../api';
withDefaults(defineProps<{ products: CatalogProduct[]; disabled: boolean; editable?: boolean }>(), { editable: true });
defineEmits<{ edit: [product: CatalogProduct] }>();
</script>
<template>
  <p v-if="!products.length" role="status" class="mf-panel">Ассортимент пуст. Добавьте первую продукцию.</p>
  <div v-else class="catalog-grid" aria-label="Ассортимент">
    <article v-for="product in products" :key="product.id" class="mf-panel catalog-product">
      <div class="catalog-copy">
        <h2>{{ product.name }}</h2>
        <p class="mf-muted">{{ product.format }} · {{ product.unit }}</p>
        <p v-if="product.description">{{ product.description }}</p>
      </div>
      <div class="catalog-price">
        <strong>{{ money(product.price) }}</strong>
        <MfStatus :tone="product.active ? 'success' : 'neutral'">{{ product.active ? 'В продаже' : 'Отключено' }}</MfStatus>
        <span v-if="product.staffDiscount" class="mf-muted">Скидка сотрудникам 50%</span>
      </div>
      <v-btn
        v-if="editable !== false"
        variant="outlined"
        :disabled="disabled"
        :aria-label="'Редактировать ' + product.name"
        @click="$emit('edit', product)"
        >Редактировать</v-btn
      >
    </article>
  </div>
</template>
<style scoped>
.catalog-grid {
  display: grid;
  gap: var(--mf-space-4);
}
.catalog-product {
  display: flex;
  align-items: center;
  gap: var(--mf-space-6);
}
.catalog-copy {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}
.catalog-copy h2 {
  font-size: var(--mf-text-lg);
}
.catalog-copy p {
  margin-top: var(--mf-space-2);
  white-space: pre-wrap;
}
.catalog-price {
  display: grid;
  justify-items: start;
  gap: var(--mf-space-2);
  flex-shrink: 0;
  font-size: var(--mf-text-sm);
}
.catalog-price strong {
  font-size: var(--mf-text-lg);
}
@media (max-width: 700px) {
  .catalog-product {
    align-items: stretch;
    flex-direction: column;
    gap: var(--mf-space-4);
  }
}
</style>
