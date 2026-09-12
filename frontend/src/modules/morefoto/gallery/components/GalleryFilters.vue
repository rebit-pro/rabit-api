<script setup lang="ts">
import type { GalleryChild } from '../types';
import UiClearButton from '../../ui/components/UiClearButton.vue';
defineProps<{ children: GalleryChild[]; code: string; count: number }>();
defineEmits<{ select: [code: string] }>();
</script>

<template>
  <section class="gallery-filters" aria-label="Выбор серии фотографий">
    <div class="gallery-filters__top">
      <div>
        <h2>Найдите свою серию</h2>
        <p class="mf-muted">Выберите код или введите его с карточки съёмки.</p>
      </div>
      <v-text-field
        :model-value="code"
        label="Код ребёнка или кадра"
        aria-label="Код ребёнка или кадра"
        placeholder="Например, A001"
        prepend-inner-icon="mdi-magnify"
        clearable
        class="gallery-search"
        autocomplete="off"
        autocapitalize="characters"
        @update:model-value="$emit('select', $event ?? '')"
      >
        <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить поиск фотографий" /></template>
      </v-text-field>
    </div>
    <div class="gallery-filters__bottom">
      <div class="gallery-code-list" aria-label="Серии">
        <v-btn :variant="!code ? 'flat' : 'outlined'" color="primary" :aria-pressed="!code" @click="$emit('select', '')">Все серии</v-btn>
        <v-btn
          v-for="child in children"
          :key="child.code"
          :variant="code === child.code ? 'flat' : 'outlined'"
          color="primary"
          :aria-pressed="code === child.code"
          :aria-label="'Серия ' + child.code"
          @click="$emit('select', child.code)"
        >
          {{ child.code }}
          <span class="gallery-code-count">{{ child.photos.length }}</span>
        </v-btn>
      </div>
      <p class="gallery-count" role="status" aria-live="polite">Кадров: {{ count }}</p>
    </div>
  </section>
</template>

<style scoped>
.gallery-filters {
  border-top: 1px solid #dce4ea;
  padding: var(--mf-space-6) 0;
}
.gallery-filters__top,
.gallery-filters__bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}
.gallery-filters__top {
  margin-bottom: var(--mf-space-4);
}
.gallery-filters h2 {
  font-size: 1.3125rem;
  font-weight: 600;
  margin-bottom: 6px;
}
.gallery-filters .mf-muted {
  font-size: 14px;
}
.gallery-search {
  flex: 0 1 310px !important;
  width: 310px;
  max-width: 100%;
}
.gallery-code-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.gallery-code-count {
  opacity: 0.72;
  font-size: 12px;
  margin-left: 10px;
}
.gallery-count {
  flex-shrink: 0;
  color: #5e6872;
  font-size: 14px;
}
@media (max-width: 767px) {
  .gallery-filters {
    padding: var(--mf-space-4) 0;
  }
  .gallery-filters__top > div > .mf-muted {
    display: none;
  }
  .gallery-filters__top {
    align-items: stretch;
    flex-direction: column;
    gap: var(--mf-space-4);
  }
  .gallery-search {
    flex: 0 1 auto !important;
    width: 100%;
  }
  .gallery-filters__bottom {
    align-items: flex-start;
    flex-direction: column;
    gap: 16px;
  }
  .gallery-code-list {
    gap: 8px;
  }
}
</style>
