<script setup lang="ts">
import { nextTick, ref, shallowRef, watch } from 'vue';
import { deliveryLabel, formatMoment, MESSAGE_MAX, NAME_MAX, textProblem } from '../rules';
import type { QuestionAuthor, QuestionMessage } from '../types';

const props = defineProps<{
  messages: QuestionMessage[];
  own: Exclude<QuestionAuthor, 'curator'>;
  loading: boolean;
  loadError: string;
  sending: boolean;
  sendError: string;
  nameRequired?: boolean;
  emptyText: string;
  submit: (text: string) => Promise<boolean>;
}>();
const emit = defineEmits<{ reload: [] }>();
const name = defineModel<string>('name', { default: '' });
const draft = ref('');
const problem = ref('');
const list = shallowRef<HTMLElement | null>(null);

async function send(): Promise<void> {
  problem.value = textProblem(props.nameRequired ? name.value : null, draft.value) ?? '';
  if (problem.value) return;
  if (await props.submit(draft.value)) draft.value = '';
}

function authorLabel(message: QuestionMessage): string {
  return message.author === 'curator' ? message.authorName + ' · куратор' : 'Вы';
}

watch(
  () => props.messages.length,
  async () => {
    await nextTick();
    list.value?.scrollTo({ top: list.value.scrollHeight });
  },
  { immediate: true }
);
</script>

<template>
  <section class="question-thread" data-testid="question-thread">
    <p v-if="loading && !messages.length" role="status" class="mf-muted">Загружаем переписку…</p>
    <v-alert v-else-if="loadError" type="error" variant="tonal" density="compact" role="alert">
      {{ loadError }}
      <v-btn variant="text" @click="emit('reload')">Повторить</v-btn>
    </v-alert>
    <p v-else-if="!messages.length" class="mf-muted question-thread__empty">{{ emptyText }}</p>
    <ol v-if="messages.length" ref="list" class="question-thread__list" aria-label="Переписка с куратором" aria-live="polite">
      <li
        v-for="message in messages"
        :key="message.id"
        class="question-thread__item"
        :class="message.author === 'curator' ? 'is-curator' : 'is-own'"
        :data-author="message.author"
      >
        <p class="question-thread__meta">{{ authorLabel(message) }} · {{ formatMoment(message.createdAt) }}</p>
        <p class="question-thread__text">{{ message.text }}</p>
        <p v-if="message.author === own && message.delivery" class="question-thread__delivery" :class="'is-' + message.delivery">
          {{ deliveryLabel(message.delivery) }}
        </p>
      </li>
    </ol>
    <form class="question-thread__form" novalidate @submit.prevent="send">
      <v-text-field
        v-if="nameRequired"
        v-model="name"
        label="Как к вам обращаться"
        autocomplete="name"
        density="comfortable"
        :counter="NAME_MAX"
        hide-details="auto"
        data-testid="question-name"
      />
      <v-textarea
        v-model="draft"
        :label="messages.length ? 'Сообщение' : 'Ваш вопрос'"
        auto-grow
        rows="3"
        max-rows="8"
        :counter="MESSAGE_MAX"
        hide-details="auto"
        data-testid="question-message"
      />
      <v-alert v-if="problem || sendError" type="error" variant="tonal" density="compact" role="alert">{{ problem || sendError }}</v-alert>
      <div class="mf-actions question-thread__actions">
        <v-btn
          type="submit"
          color="primary"
          :loading="sending"
          :disabled="sending"
          prepend-icon="mdi-send-outline"
          data-testid="question-send"
          >Отправить</v-btn
        >
      </div>
    </form>
  </section>
</template>

<style scoped>
.question-thread {
  display: grid;
  gap: var(--mf-space-4);
}
.question-thread__empty {
  font-size: 14px;
  line-height: 1.6;
}
.question-thread__list {
  display: grid;
  gap: var(--mf-space-3);
  max-height: min(52svh, 460px);
  overflow-y: auto;
  padding: 0;
  margin: 0;
  list-style: none;
}
.question-thread__item {
  max-width: 85%;
  padding: 10px 14px;
  border-radius: var(--mf-radius-md);
}
.question-thread__item.is-own {
  justify-self: end;
  background: var(--mf-color-bubble-out);
}
.question-thread__item.is-curator {
  justify-self: start;
  background: var(--mf-color-bubble-in);
}
.question-thread__meta,
.question-thread__delivery {
  font-size: 12px;
  line-height: 1.5;
  color: var(--mf-color-text-secondary);
}
.question-thread__text {
  margin: 2px 0;
  font-size: 15px;
  line-height: 1.6;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.question-thread__delivery.is-failed {
  color: var(--mf-tone-danger-fg);
}
.question-thread__form {
  display: grid;
  gap: var(--mf-space-3);
}
.question-thread__actions {
  justify-content: flex-end;
}
@media (max-width: 600px) {
  .question-thread__item {
    max-width: 92%;
  }
  .question-thread__actions :deep(.v-btn) {
    flex: 1 1 auto;
  }
}
</style>
