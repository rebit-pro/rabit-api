<script setup lang="ts">
defineProps<{ error: string; loading: boolean; missing?: boolean }>();
defineEmits<{ retry: [] }>();
</script>
<template>
  <v-alert v-if="error" type="error" variant="tonal" role="alert"
    >{{ error }}
    <div class="mt-3"><v-btn variant="outlined" @click="$emit('retry')">Повторить загрузку</v-btn></div></v-alert
  >
  <v-skeleton-loader v-else-if="loading" type="article, list-item-two-line" aria-label="Загрузка учреждений" aria-busy="true" />
  <section v-else-if="missing" class="mf-panel mf-empty">
    <h1>Запись недоступна</h1>
    <p class="mf-muted mt-3">Проверьте ссылку и вернитесь к списку учреждений.</p>
  </section>
</template>
