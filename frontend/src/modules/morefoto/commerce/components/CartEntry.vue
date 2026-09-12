<script setup lang="ts">
import type { GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
const props = defineProps<{ gallery: GallerySnapshot; token: string }>();
const { quote } = useCart(() => props.gallery);
</script>
<template>
  <v-btn :to="'/g/' + token + '/cart'" color="primary" variant="tonal" prepend-icon="mdi-cart-outline" aria-label="Открыть корзину"
    >Корзина <span class="ml-2">{{ quote.count }}</span></v-btn
  >
</template>

<style scoped>
@media (max-width: 767px) {
  .v-btn {
    padding-inline: 10px;
    min-width: 0;
  }
}
</style>
