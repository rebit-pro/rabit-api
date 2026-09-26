<script setup lang="ts">
import { computed, nextTick, shallowRef, useTemplateRef } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { questionsApi } from '../api';
import { questionProblem } from '../problem';
import { CONTACT_MAX, feedbackFieldProblems, feedbackPayloadKey, feedbackProblemMessage, type FeedbackDraft } from '../feedback';
import { MESSAGE_MAX, NAME_MAX, newRequestId } from '../rules';

const open = defineModel<boolean>({ default: false });
const form = useTemplateRef<{ $el: HTMLElement }>('feedbackForm');
const draft = shallowRef<FeedbackDraft>({ name: '', contact: '', message: '' });
const attempted = shallowRef(false);
const sending = shallowRef(false);
const problem = shallowRef('');
// null — the form is shown; a number — the request is accepted (0 in the demo, where nothing is sent).
const acceptedNumber = shallowRef<number | null>(null);
let attempt: { payload: string; id: string } | null = null;

const fieldProblems = computed(() => (attempted.value ? feedbackFieldProblems(draft.value) : {}));

function update(field: keyof FeedbackDraft, value: string | null): void {
  draft.value = { ...draft.value, [field]: value ?? '' };
}

async function submit(): Promise<void> {
  if (sending.value) return;
  attempted.value = true;
  problem.value = '';
  if (Object.keys(feedbackFieldProblems(draft.value)).length > 0) {
    await nextTick();
    form.value?.$el.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    return;
  }
  const payload = feedbackPayloadKey(draft.value);
  if (attempt?.payload !== payload) attempt = { payload, id: newRequestId() };
  sending.value = true;
  try {
    acceptedNumber.value = isMockApiEnabled
      ? 0
      : (
          await questionsApi.feedback(
            { name: draft.value.name.trim(), contact: draft.value.contact.trim(), message: draft.value.message.trim() },
            attempt.id
          )
        ).number;
    attempt = null;
  } catch (cause) {
    problem.value = feedbackProblemMessage(questionProblem(cause));
  } finally {
    sending.value = false;
  }
}

function reset(): void {
  if (acceptedNumber.value === null) return;
  draft.value = { name: '', contact: '', message: '' };
  attempted.value = false;
  acceptedNumber.value = null;
}
</script>

<template>
  <v-dialog v-model="open" max-width="520" scrollable aria-labelledby="feedback-title" @after-leave="reset">
    <v-card class="morefoto-app feedback-card" data-testid="login-feedback">
      <div class="feedback-card__heading">
        <h2 id="feedback-title">Написать нам</h2>
        <v-btn icon="mdi-close" variant="text" aria-label="Закрыть форму" @click="open = false" />
      </div>
      <div v-if="acceptedNumber !== null" data-testid="feedback-sent">
        <v-alert type="success" variant="tonal" role="status">
          <template v-if="acceptedNumber > 0">Обращение №{{ acceptedNumber }} отправлено.</template>
          <template v-else>Обращение принято.</template>
          Ответим по указанному контакту{{ draft.contact ? ': ' + draft.contact.trim() : '' }}.
        </v-alert>
        <p v-if="isMockApiEnabled" class="mf-muted mt-4">В демонстрационной версии обращения не отправляются.</p>
        <v-btn block class="mt-5" @click="open = false">Закрыть</v-btn>
      </div>
      <template v-else>
        <p class="feedback-card__intro">
          Не получается войти, нет приглашения или есть вопрос о съёмке? Напишите — ответим по телефону или email.
        </p>
        <v-form ref="feedbackForm" class="mf-form-fields" novalidate :disabled="sending" @submit.prevent="submit">
          <v-text-field
            :model-value="draft.name"
            label="Как к вам обращаться"
            autocomplete="name"
            :maxlength="NAME_MAX"
            :error-messages="fieldProblems.name"
            :aria-invalid="fieldProblems.name ? true : undefined"
            data-testid="feedback-name"
            @update:model-value="update('name', $event)"
          />
          <v-text-field
            :model-value="draft.contact"
            label="Телефон или email"
            autocomplete="email"
            hint="По нему мы ответим"
            persistent-hint
            :maxlength="CONTACT_MAX"
            :error-messages="fieldProblems.contact"
            :aria-invalid="fieldProblems.contact ? true : undefined"
            data-testid="feedback-contact"
            @update:model-value="update('contact', $event)"
          />
          <v-textarea
            :model-value="draft.message"
            label="Сообщение"
            rows="4"
            auto-grow
            :counter="MESSAGE_MAX"
            :maxlength="MESSAGE_MAX"
            :error-messages="fieldProblems.message"
            :aria-invalid="fieldProblems.message ? true : undefined"
            data-testid="feedback-message"
            @update:model-value="update('message', $event)"
          />
          <v-alert v-if="problem" type="error" variant="tonal" density="compact" role="alert" data-testid="feedback-problem">{{
            problem
          }}</v-alert>
          <v-btn type="submit" block :loading="sending" data-testid="feedback-send">Отправить</v-btn>
        </v-form>
        <p class="mf-muted feedback-card__notice">Имя, контакт и текст получат сотрудники «Море фото» в мессенджере MAX.</p>
      </template>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.feedback-card {
  padding: var(--mf-space-6);
  border-radius: var(--mf-radius-lg) !important;
}
.feedback-card__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-3);
  margin-bottom: var(--mf-space-2);
}
.feedback-card h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-xl);
  line-height: var(--mf-leading-tight);
}
.feedback-card__intro {
  margin-bottom: var(--mf-space-4);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-normal);
}
.feedback-card__notice {
  margin-top: var(--mf-space-3);
  font-size: var(--mf-text-xs);
  line-height: var(--mf-leading-snug);
}
@media (max-width: 600px) {
  .feedback-card {
    padding: var(--mf-space-4);
  }
}
</style>
