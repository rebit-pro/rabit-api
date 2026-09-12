<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useGallery } from '../../gallery/composables/useGallery';
import CartContents from './CartContents.vue';
const route = useRoute();
const { gallery, loading, error, unavailable, reload } = useGallery();
</script>
<template>
  <main class="mf-main cart-page">
    <RouterLink :to="'/g/' + route.params.token" class="mf-back">← К фотографиям</RouterLink>
    <v-skeleton-loader v-if="loading" type="heading, article" class="mt-5" />
    <v-alert v-else-if="error" type="error" variant="tonal" class="mt-5"
      >{{ unavailable ? 'Ссылка на группу недействительна.' : error
      }}<v-btn v-if="!unavailable" variant="text" @click="reload">Повторить загрузку</v-btn></v-alert
    >
    <template v-else-if="gallery">
      <header class="mf-page-heading">
        <p class="mf-eyebrow">ВАШ ВЫБОР</p>
        <h1>Корзина</h1>
        <p class="mf-muted">{{ gallery.institutionName }} · {{ gallery.groupName }} · {{ gallery.shootName }}</p>
      </header>
      <CartContents :gallery="gallery" :token="String(route.params.token)" />
    </template>
  </main>
</template>
<style scoped>
.cart-page {
  width: 100%;
  min-height: 100svh;
}
</style>
