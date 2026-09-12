<script setup lang="ts">
import { computed } from 'vue';
import { availablePhotos } from '../../settlement/rules';
import PhotoImage from '../../photos/components/PhotoImage.vue';
import type { OrderSnapshot } from '../types';
import type { downloadAccess } from '../delivery/rules';
import { formatMoment } from '../formatters';
const props = defineProps<{
  order: OrderSnapshot;
  access: ReturnType<typeof downloadAccess>;
  busy: string;
}>();
const photos = computed(() => availablePhotos(props.order));
defineEmits<{ download: [photoId?: string] }>();
</script>
<template>
  <section id="order-files" class="mf-panel downloads" aria-labelledby="downloads-title" data-testid="order-downloads">
    <p class="mf-eyebrow">ЭЛЕКТРОННЫЕ ФОТОГРАФИИ</p>
    <h2 id="downloads-title">Ваши файлы</h2>
    <p v-if="access.state === 'unpaid'" class="mf-muted mt-3">Файлы откроются после подтверждённой оплаты.</p>
    <p v-else-if="access.state === 'review'" class="mf-muted mt-3">
      Оплата пришла после закрытия группы. Рита согласует выдачу файлов или возврат. Повторно платить не нужно.
    </p>
    <p v-else-if="access.state === 'empty'" class="mf-muted mt-3">
      В этом заказе только печатная продукция. Электронных файлов и подарочного комплекта нет.
    </p>
    <p v-else-if="access.state === 'revoked'" class="mf-muted mt-3">
      Последующая выдача файлов прекращена по подтверждённому возврату. Ранее полученные файлы остаются в истории.
    </p>
    <p v-else-if="access.state === 'refund'" class="mf-muted mt-3">
      По поздней оплате согласован возврат. Выдача файлов приостановлена; результат виден в истории заказа.
    </p>
    <template v-else>
      <p class="downloads__deadline mt-3" data-testid="download-deadline">
        {{ access.state === 'expired' ? 'Срок доступа истёк' : 'Доступны до' }}
        {{ formatMoment(access.deadline!) }} мск
      </p>
      <p class="mf-muted mt-2 downloads__note">
        Один календарный месяц после покупки. Закрытие группы не сокращает срок. Повторное скачивание в этот период бесплатно.
      </p>
      <v-alert v-if="access.state === 'expired'" type="warning" variant="tonal" class="mt-4"
        >Скачивание завершено. Вопрос о восстановлении доступа поможет решить Рита; продление не гарантируется.</v-alert
      >
      <template v-else>
        <div class="downloads__action">
          <v-btn
            color="primary"
            prepend-icon="mdi-download"
            :loading="busy === 'archive'"
            :disabled="!!busy"
            data-testid="download-archive"
            @click="$emit('download')"
            >Скачать всё архивом</v-btn
          >
          <span class="mf-muted downloads__note">{{ photos.length }} файлов · ZIP</span>
        </div>
        <p class="mf-muted downloads__note mb-4">
          Тестовые WebP с водяным знаком. В архив входят купленные файлы, полные комплекты и подарки этого заказа.
        </p>
        <ul class="downloads__list">
          <li v-for="photo in photos" :key="photo.id" class="downloads__file">
            <PhotoImage :src="photo.thumbSrc" :alt="'Кадр ' + photo.code" width="48" height="64" loading="lazy" />
            <div class="downloads__name">
              <strong>{{ photo.code }}</strong
              ><span class="mf-muted downloads__note">WebP · тестовый файл</span>
            </div>
            <v-btn
              variant="outlined"
              color="primary"
              :aria-label="'Скачать ' + photo.code"
              :loading="busy === photo.id"
              :disabled="!!busy"
              @click="$emit('download', photo.id)"
              >Скачать</v-btn
            >
          </li>
        </ul>
      </template>
    </template>
    <a href="#order-help" class="downloads__help">Нужна помощь с фотографиями</a>
  </section>
</template>
<style scoped>
.downloads {
  min-width: 0;
}
.downloads__deadline {
  font-weight: 600;
  line-height: 1.6;
}
.downloads__note {
  font-size: var(--mf-text-small);
}
.downloads__action {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-3);
  margin-block: var(--mf-space-6) var(--mf-space-3);
}
.downloads__list {
  list-style: none;
  padding: 0;
  display: grid;
  gap: var(--mf-space-3);
}
.downloads__file {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  padding-top: var(--mf-space-3);
}
.downloads__file img {
  object-fit: contain;
  border-radius: var(--mf-radius-field);
  background: rgb(var(--v-theme-surface));
}
.downloads__name {
  display: grid;
  gap: var(--mf-space-1);
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
}
.downloads__help {
  display: inline-flex;
  align-items: center;
  min-height: var(--mf-touch-size);
  margin-top: var(--mf-space-4);
  color: rgb(var(--v-theme-primary));
}
@media (max-width: 479px) {
  .downloads__file {
    flex-wrap: wrap;
  }
  .downloads__file .v-btn {
    margin-left: auto;
  }
}
</style>
