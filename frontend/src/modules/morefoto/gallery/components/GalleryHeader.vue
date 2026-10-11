<script setup lang="ts">
import { computed } from 'vue';
import { daysLeft, salesStateTitle } from '../../commerce/sales-windows';
import type { GallerySnapshot } from '../types';
const props = defineProps<{ gallery: GallerySnapshot }>();
const emit = defineEmits<{ help: [] }>();
const format = (moment: string): string =>
  new Intl.DateTimeFormat('ru-RU', {
    timeZone: 'Europe/Moscow',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(moment));
// E7: the open window counts down to the print close, the digital window — to the digital close.
const remaining = computed(() => {
  const until = { open: props.gallery.closesAt, digital: props.gallery.digitalSalesUntil }[props.gallery.state as 'open' | 'digital'];
  const left = until === undefined ? null : daysLeft(until, props.gallery.referenceNow);
  return left ? 'Осталось ' + left : '';
});
</script>

<template>
  <header class="gallery-heading">
    <div class="gallery-heading__copy">
      <p class="mf-eyebrow">ВАШИ ФОТОГРАФИИ</p>
      <p class="gallery-context">
        {{ gallery.institutionName }} <span aria-hidden="true">/</span>
        {{ gallery.groupName }}
      </p>
      <h1>{{ gallery.shootName }}</h1>
      <p class="mf-muted">
        {{
          gallery.state === 'preparing'
            ? 'Фотографии появятся на этой странице, как только их подготовят.'
            : 'Выберите код ребёнка и откройте понравившийся кадр.'
        }}
      </p>
    </div>
    <aside class="gallery-conditions" aria-label="Условия съёмки">
      <div class="gallery-conditions__title">
        <v-icon icon="mdi-calendar-clock-outline" size="22" />
        <strong>{{ salesStateTitle(gallery.state) }}</strong>
      </div>
      <dl v-if="gallery.state !== 'preparing' && gallery.closesAt" class="gallery-deadlines" data-testid="gallery-deadlines">
        <div>
          <dt>Печать</dt>
          <dd>
            до <time :datetime="gallery.closesAt">{{ format(gallery.closesAt) }}</time> МСК
          </dd>
        </div>
        <div v-if="gallery.digitalSalesUntil">
          <dt>Электронные фото</dt>
          <dd>
            до <time :datetime="gallery.digitalSalesUntil">{{ format(gallery.digitalSalesUntil) }}</time> МСК
          </dd>
        </div>
      </dl>
      <p v-else>Сроки появятся после передачи ссылки в группу.</p>
      <p v-if="remaining" class="gallery-remaining">{{ remaining }}</p>
      <v-btn variant="text" color="primary" size="small" class="gallery-help" @click="emit('help')"
        >Получение и помощь <v-icon icon="mdi-arrow-top-right" end size="16"
      /></v-btn>
    </aside>
  </header>
</template>

<style scoped>
.gallery-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 40px;
  padding: 48px 0 36px;
}
.gallery-heading__copy {
  min-width: 0;
}
.gallery-heading h1 {
  font-size: clamp(32px, 4vw, 48px);
  line-height: 1.15;
  letter-spacing: -0.035em;
  margin: 16px 0;
  font-weight: 600;
}
.gallery-heading .mf-muted {
  font-size: 16px;
}
.gallery-context {
  color: var(--mf-color-text-secondary);
  font-size: 14px;
  line-height: 1.6;
}
.gallery-context span {
  margin: 0 8px;
  color: var(--mf-color-text-tertiary);
}
.gallery-conditions {
  flex: 0 0 292px;
  padding: 22px 24px 14px;
  background: var(--mf-color-selected);
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.6;
}
.gallery-conditions__title {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
  color: var(--mf-color-link);
}
.gallery-deadlines {
  display: grid;
  gap: 2px;
  margin: 0;
}
.gallery-deadlines div {
  display: flex;
  flex-wrap: wrap;
  gap: 0 6px;
}
.gallery-deadlines dt {
  font-weight: 600;
}
.gallery-deadlines dt::after {
  content: ':';
}
.gallery-deadlines dd {
  margin: 0;
}
.gallery-remaining {
  margin-top: 4px;
  color: var(--mf-color-text-secondary);
}
.gallery-help {
  margin-left: -12px;
  margin-top: 8px;
}
@media (max-width: 767px) {
  .gallery-heading {
    flex-direction: column;
    gap: 16px;
    padding: 24px 0;
  }
  .gallery-conditions {
    flex: auto;
    width: 100%;
    padding: 14px 18px;
  }
  .gallery-heading h1 {
    margin-top: 12px;
  }
  .mf-eyebrow,
  .gallery-help {
    display: none;
  }
  .gallery-conditions__title {
    margin-bottom: 4px;
  }
  .gallery-heading .mf-muted {
    font-size: 14px;
  }
}
</style>
