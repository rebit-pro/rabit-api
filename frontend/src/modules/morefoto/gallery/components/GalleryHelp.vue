<script setup lang="ts">
import type { GallerySnapshot } from '../types';
defineProps<{ gallery: GallerySnapshot }>();
const open = defineModel<boolean>({ default: false });
</script>
<template>
  <v-dialog v-model="open" max-width="520" aria-labelledby="gallery-help-title">
    <v-card class="morefoto-app gallery-help-card">
      <div class="gallery-help-card__heading">
        <h2 id="gallery-help-title">Получение и помощь</h2>
        <v-btn icon="mdi-close" variant="text" aria-label="Закрыть помощь" @click="open = false" />
      </div>
      <h3>Готовые фотографии</h3>
      <p>{{ gallery.delivery }} Дату выдачи сообщит ответственный группы.</p>
      <h3>Вопрос по съёмке</h3>
      <p>Напишите ответственному в том чате, где получили ссылку. Он передаст вопрос куратору {{ gallery.curator }}.</p>
      <p class="mf-muted">Укажите группу «{{ gallery.groupName }}» и код кадра, например A001-01.</p>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.gallery-help-card {
  padding: 24px;
  border-radius: 12px !important;
}
.gallery-help-card__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.gallery-help-card h2 {
  font-size: 22px;
  line-height: 1.3;
}
.gallery-help-card h3 {
  font-size: 16px;
  margin: 20px 0 8px;
}
.gallery-help-card p {
  font-size: 14px;
  line-height: 1.7;
  margin-bottom: 12px;
}
</style>
