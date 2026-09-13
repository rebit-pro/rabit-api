<script setup lang="ts">
import { computed } from 'vue';
import type { GallerySnapshot } from '../types';
const props = defineProps<{ gallery: GallerySnapshot }>();
const emit = defineEmits<{ help: [] }>();
const deadline = computed(() =>
  props.gallery.closesAt
    ? new Intl.DateTimeFormat('ru-RU', {
        timeZone: 'Europe/Moscow',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit'
      }).format(new Date(props.gallery.closesAt))
    : ''
);
const remaining = computed(() => {
  if (!props.gallery.closesAt || props.gallery.state !== 'open') return '';
  const days = Math.max(0, Math.floor((Date.parse(props.gallery.closesAt) - Date.parse(props.gallery.referenceNow)) / 86400000));
  if (days === 0) return 'Осталось меньше суток';
  const form = new Intl.PluralRules('ru').select(days);
  return 'Осталось ' + days + ' ' + ({ zero: 'дней', one: 'день', two: 'дня', few: 'дня', many: 'дней', other: 'дня' }[form] ?? 'дней');
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
      <p class="mf-muted">Выберите код ребёнка и откройте понравившийся кадр.</p>
    </div>
    <aside class="gallery-conditions" aria-label="Условия съёмки">
      <div class="gallery-conditions__title">
        <v-icon icon="mdi-calendar-clock-outline" size="22" />
        <strong>{{
          gallery.state === 'open' ? 'Приём открыт' : gallery.state === 'closed' ? 'Приём завершён' : 'Готовим фотографии'
        }}</strong>
      </div>
      <template v-if="deadline && gallery.state !== 'preparing'">
        <p>
          До
          <time :datetime="gallery.closesAt ?? undefined">{{ deadline }}</time>
          МСК
        </p>
        <p v-if="remaining" class="gallery-remaining">{{ remaining }}</p>
      </template>
      <p v-else>Срок появится после передачи ссылки в группу.</p>
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
  color: #5e6872;
  font-size: 14px;
  line-height: 1.6;
}
.gallery-context span {
  margin: 0 8px;
  color: #8a9aa6;
}
.gallery-conditions {
  flex: 0 0 292px;
  padding: 22px 24px 14px;
  background: #eaf3f9;
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.6;
}
.gallery-conditions__title {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
  color: #24658a;
}
.gallery-remaining {
  margin-top: 4px;
  color: #5e6872;
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
