<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import { shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import CartEntry from '../../commerce/components/CartEntry.vue';
import { useGallery } from '../composables/useGallery';
import GalleryHeader from './GalleryHeader.vue';
import MfLogo from '@/components/brand/MfLogo.vue';
import GalleryFilters from './GalleryFilters.vue';
import GalleryGrid from './GalleryGrid.vue';
import PhotoViewer from './PhotoViewer.vue';
import GalleryHelp from './GalleryHelp.vue';
const {
  gallery,
  loading,
  error,
  unavailable,
  reload,
  code,
  photos,
  allPhotos,
  activePhoto,
  activeIndex,
  missingPhoto,
  selectCode,
  openPhoto,
  closePhoto,
  stepPhoto
} = useGallery();
const route = useRoute();
const helpOpen = shallowRef(false);
</script>

<template>
  <a href="#gallery-content" class="mf-skip">К фотографиям</a>
  <div class="gallery-shell">
    <header class="gallery-topbar">
      <MfLogo :size="24" mono class="gallery-brand" />
      <span class="gallery-topbar__caption">Фотографии ваших детей</span>
      <CartEntry v-if="gallery && gallery.state !== 'preparing'" :gallery="gallery" :token="String(route.params.token)" />
      <v-btn
        v-if="gallery"
        class="gallery-help-top"
        aria-label="Помощь"
        variant="text"
        color="primary"
        prepend-icon="mdi-help-circle-outline"
        @click="helpOpen = true"
        >Помощь</v-btn
      >
    </header>
    <p v-if="isMockApiEnabled" class="gallery-demo">Тестовая галерея · Названия и даты условные</p>
    <main id="gallery-content" class="gallery-main">
      <div v-if="loading" role="status" aria-label="Загрузка галереи" class="gallery-loading">
        <v-skeleton-loader type="heading, paragraph" />
        <div class="gallery-loading__grid">
          <v-skeleton-loader v-for="index in 4" :key="index" type="image, text" />
        </div>
      </div>
      <section v-else-if="unavailable" class="mf-panel gallery-empty" data-testid="gallery-unavailable">
        <v-icon icon="mdi-link-off" size="40" color="primary" />
        <h1>Ссылка недействительна</h1>
        <p class="mf-muted">Проверьте, что скопировали её целиком, или попросите новую ссылку у ответственного группы.</p>
      </section>
      <v-alert v-else-if="error" type="error" variant="tonal" class="my-8" data-testid="gallery-error">
        {{ error }}
        <v-btn variant="text" @click="reload">Повторить загрузку</v-btn>
      </v-alert>
      <template v-else-if="gallery">
        <GalleryHeader :gallery="gallery" @help="helpOpen = true" />
        <v-alert v-if="gallery.audience === 'staff'" type="info" variant="tonal" class="mb-6" data-testid="staff-gallery">
          Подборка сотрудников. Для разрешённых товаров действует скидка 50%. Льгота определяется этой подборкой.
        </v-alert>
        <v-alert v-if="gallery.state === 'closed'" type="info" variant="tonal" class="mb-6">
          Приём заказов завершён. Фотографии можно посмотреть; новые заказы сейчас не принимаются.
        </v-alert>
        <section v-if="gallery.state === 'preparing'" class="mf-panel gallery-empty">
          <v-icon icon="mdi-timer-sand" size="40" color="primary" />
          <h2>Фотографии ещё готовятся</h2>
          <p class="mf-muted">Ответственный сообщит, когда галерея будет открыта.</p>
        </section>
        <section v-else-if="!allPhotos.length" class="mf-panel gallery-empty">
          <v-icon icon="mdi-image-multiple-outline" size="40" color="primary" />
          <h2>В подборке пока нет фотографий</h2>
          <p class="mf-muted">Проверьте ссылку позже или обратитесь к ответственному группы.</p>
        </section>
        <template v-else>
          <GalleryFilters :children="gallery.children" :code="code" :count="photos.length" @select="selectCode" />
          <v-alert v-if="missingPhoto" type="info" variant="tonal" class="mb-6">
            Кадр не найден в этой подборке.
            <v-btn variant="text" @click="closePhoto">Вернуться к фотографиям</v-btn>
          </v-alert>
          <GalleryGrid v-if="photos.length" :photos="photos" @open="openPhoto" />
          <section v-else class="mf-panel gallery-empty">
            <v-icon icon="mdi-magnify" size="40" color="primary" />
            <h2>По этому коду ничего не найдено</h2>
            <p class="mf-muted">Проверьте код. Поиск работает только внутри этой группы и съёмки.</p>
            <v-btn color="primary" variant="outlined" @click="selectCode('')">Показать все серии</v-btn>
          </section>
        </template>
        <PhotoViewer
          :photo="activePhoto"
          :index="activeIndex"
          :total="photos.length"
          :context="gallery.groupName"
          :gallery="gallery"
          :token="String(route.params.token)"
          @close="closePhoto"
          @step="stepPhoto"
        />
        <GalleryHelp v-model="helpOpen" :gallery="gallery" />
      </template>
    </main>
    <footer class="gallery-footer"><MfLogo :size="20" mono /><span>Сохраняем моменты детства</span></footer>
  </div>
</template>

<style scoped>
.gallery-shell {
  min-height: 100svh;
  background: var(--mf-color-bg);
}
.gallery-topbar {
  display: flex;
  align-items: center;
  gap: 24px;
  max-width: 1320px;
  padding: 20px 40px;
  margin: auto;
}
.gallery-brand {
  flex: 0 0 auto;
}
.gallery-topbar__caption {
  font-size: 13px;
  color: var(--mf-color-text-secondary);
  margin-left: auto;
}
.gallery-demo {
  border-block: 1px solid var(--mf-color-border);
  background: var(--mf-color-selected);
  text-align: center;
  color: var(--mf-color-text-secondary);
  padding: 10px 20px;
  font-size: 12px;
  line-height: 1.6;
}
.gallery-main {
  max-width: 1320px;
  margin: auto;
  padding: 0 40px 64px;
  min-height: 65svh;
}
.gallery-empty {
  padding: 56px 24px;
  display: grid;
  justify-items: center;
  gap: 16px;
  text-align: center;
}
.gallery-empty h1 {
  font-size: 28px;
  line-height: 1.3;
}
.gallery-empty h2 {
  font-size: 23px;
  line-height: 1.3;
}
.gallery-empty p {
  max-width: 520px;
}
.gallery-loading {
  padding-top: 48px;
}
.gallery-loading__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
  margin-top: 24px;
}
.gallery-footer {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  max-width: 1240px;
  margin: 0 auto;
  padding: 24px 0;
  border-top: 1px solid var(--mf-color-border);
  color: var(--mf-color-text-secondary);
  font-size: 12px;
}
@media (max-width: 767px) {
  .gallery-brand {
    font-size: 20px;
  }
  .gallery-topbar {
    gap: 8px !important;
  }
  .gallery-help-top {
    min-width: 44px;
    width: 44px;
    padding: 0 !important;
  }
  .gallery-help-top :deep(.v-btn__content) {
    font-size: 0;
  }
  .gallery-help-top :deep(.v-btn__prepend) {
    margin: 0;
  }

  .gallery-topbar {
    padding: 14px 16px;
    gap: 12px;
    justify-content: space-between;
  }
  .gallery-topbar__caption {
    display: none;
  }
  .gallery-main {
    padding: 0 16px 40px;
  }
  .gallery-demo {
    text-align: left;
    padding: 10px 16px;
  }
  .gallery-loading__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .gallery-footer {
    margin: 0 16px;
    flex-wrap: wrap;
  }
  .gallery-empty {
    padding: 40px 20px;
  }
}
</style>
