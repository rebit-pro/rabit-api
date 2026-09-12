<script setup lang="ts">
import { money } from '../../commerce/money';
import type { CatalogProduct } from '../api';
defineProps<{ products: CatalogProduct[]; disabled: boolean }>();
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
        <span>{{ product.active ? 'В продаже' : 'Отключено' }}</span>
        <span v-if="product.staffDiscount">Скидка сотрудникам 50%</span>
      </div>
      <v-btn variant="outlined" :disabled="disabled" :aria-label="'Редактировать ' + product.name" @click="$emit('edit', product)"
        >Редактировать</v-btn
      >
    </article>
  </div>
</template>
<style scoped>
.catalog-grid {
  display: grid;
  gap: 16px;
}
.catalog-product {
  display: flex;
  align-items: center;
  gap: 24px;
}
.catalog-copy {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}
.catalog-copy h2 {
  font-size: 20px;
}
.catalog-copy p {
  margin-top: 8px;
  white-space: pre-wrap;
}
.catalog-price {
  display: grid;
  gap: 6px;
  flex-shrink: 0;
  font-size: 14px;
}
.catalog-price strong {
  font-size: 20px;
}
@media (max-width: 700px) {
  .catalog-product {
    align-items: stretch;
    flex-direction: column;
    gap: 16px;
  }
}
</style>
