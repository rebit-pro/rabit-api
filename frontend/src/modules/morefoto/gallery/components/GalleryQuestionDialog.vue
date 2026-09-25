<script setup lang="ts">
import QuestionThread from '../../support/components/QuestionThread.vue';
import type { QuestionMessage } from '../../support/types';
import type { GallerySnapshot } from '../types';

defineProps<{
  gallery: GallerySnapshot;
  messages: QuestionMessage[];
  loading: boolean;
  loadError: string;
  sending: boolean;
  sendError: string;
  needsName: boolean;
  pending: boolean;
  submit: (text: string) => Promise<boolean>;
}>();
const emit = defineEmits<{ reload: [] }>();
const open = defineModel<boolean>({ default: false });
const name = defineModel<string>('name', { default: '' });
</script>

<template>
  <v-dialog v-model="open" max-width="560" scrollable aria-labelledby="gallery-question-title">
    <v-card class="morefoto-app gallery-question-card" data-testid="gallery-question">
      <div class="gallery-question-card__heading">
        <h2 id="gallery-question-title">Вопрос куратору</h2>
        <v-btn icon="mdi-close" variant="text" aria-label="Закрыть переписку" @click="open = false" />
      </div>
      <p class="gallery-question-card__intro">
        Ответит куратор{{ gallery.curator ? ' ' + gallery.curator : ' группы' }} — ответ появится здесь. Переписка сохраняется в этом
        браузере: откройте галерею по той же ссылке, чтобы увидеть ответ.
      </p>
      <v-alert v-if="pending" type="info" variant="tonal" density="compact" class="mb-4" data-testid="question-pending">
        Первый вопрос ещё не подтверждён. При отправке мы сначала повторим его, а новый текст добавим следующим сообщением.
      </v-alert>
      <QuestionThread
        v-model:name="name"
        :messages="messages"
        own="parent"
        :loading="loading"
        :load-error="loadError"
        :sending="sending"
        :send-error="sendError"
        :name-required="needsName"
        :submit="submit"
        empty-text="Спросите про съёмку, фотографии или сроки. Укажите код кадра, если вопрос о конкретном снимке."
        @reload="emit('reload')"
      />
      <p class="mf-muted gallery-question-card__notice">Имя и текст передаются кураторам МореФото через мессенджер MAX.</p>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.gallery-question-card {
  padding: 24px;
  border-radius: 12px !important;
}
.gallery-question-card__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.gallery-question-card h2 {
  font-size: 22px;
  line-height: 1.3;
}
.gallery-question-card__intro {
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 16px;
}
.gallery-question-card__notice {
  margin-top: 12px;
  font-size: 12px;
  line-height: 1.5;
}
@media (max-width: 600px) {
  .gallery-question-card {
    padding: 16px;
  }
}
</style>
