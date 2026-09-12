<script setup lang="ts">
import SupportTimeline from '../../curator/components/SupportTimeline.vue';
import { useOrderPeriod } from '../../curator/useOrderPeriod';
import { computed, nextTick, useTemplateRef, watch } from 'vue';
import type { OrderSnapshot } from '../types';
import { supportTopics } from '../delivery/rules';
import { formatMoment } from '../formatters';
import { useOrderSupport } from '../composables/useOrderSupport';
const props = defineProps<{ order: OrderSnapshot }>();
const { draft, busy, errors, error, notice, photos, submit } = useOrderSupport(() => props.order);
const period = useOrderPeriod(() => props.order.groupId);
const history = computed(() => [...(props.order.supportRequests ?? [])].reverse());
const form = useTemplateRef<HTMLFormElement>('form');
watch(errors, async (value) => {
  const first = Object.keys(value)[0];
  if (!first) return;
  await nextTick();
  form.value?.querySelector<HTMLElement>('[name="support-' + first + '"]')?.focus();
});
function topicTitle(value: string) {
  return supportTopics.find((topic) => topic.value === value)?.title ?? value;
}
</script>
<template>
  <section id="order-help" class="mf-panel support" aria-labelledby="support-title" tabindex="-1">
    <p class="mf-eyebrow">КУРАТОР {{ period.curator.toLocaleUpperCase('ru') }}</p>
    <h2 id="support-title">Помощь с заказом</h2>
    <p class="mf-muted support__note mt-3">
      Заказ {{ order.number }}, {{ order.groupName }} и съёмка уже прикреплены. Не нужно переписывать номер или отправлять личную ссылку.
    </p>
    <form ref="form" class="support__form" novalidate @submit.prevent="submit">
      <v-select
        v-model="draft.topic"
        :items="supportTopics"
        label="Тема обращения"
        aria-label="Тема обращения"
        name="support-topic"
        :disabled="busy"
        :error-messages="errors.topic"
      />
      <v-select
        v-if="photos.length"
        v-model="draft.photoId"
        :items="[{ title: 'Весь заказ', value: '' }, ...photos]"
        label="Фотография — необязательно"
        aria-label="Фотография — необязательно"
        name="support-photoId"
        data-testid="support-photo"
        :disabled="busy"
        :error-messages="errors.photoId"
      />
      <v-text-field
        v-model="draft.replyEmail"
        label="Email для ответа"
        aria-label="Email для ответа"
        name="support-replyEmail"
        type="email"
        autocomplete="email"
        required
        maxlength="254"
        :disabled="busy"
        :error-messages="errors.replyEmail"
        :aria-invalid="!!errors.replyEmail || undefined"
        hint="Можно указать правильный адрес, если в заказе допущена ошибка."
        persistent-hint
      />
      <v-textarea
        v-model="draft.message"
        label="Ваш вопрос"
        aria-label="Ваш вопрос"
        name="support-message"
        required
        :rows="4"
        maxlength="2000"
        :counter="2000"
        :disabled="busy"
        :error-messages="errors.message"
        :aria-invalid="!!errors.message || undefined"
      />
      <v-alert v-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" role="status">{{ notice }}</v-alert>
      <div>
        <v-btn type="submit" color="primary" :loading="busy" :disabled="busy" data-testid="send-support"
          >Сохранить тестовое обращение</v-btn
        >
      </div>
      <p class="mf-muted support__note">
        Демонстрация: обращение сохраняется в этом браузере и связано с заказом. Настоящие сообщения куратору не отправляются.
      </p>
    </form>
    <div class="support__history" data-testid="support-history">
      <h3 class="support__subtitle">Ваши обращения</h3>
      <p v-if="!order.supportRequests?.length" class="mf-muted support__note mt-3">Обращений по этому заказу пока нет.</p>
      <article v-for="request in history" :key="request.id" class="support__request">
        <strong>{{ request.number }} · {{ topicTitle(request.topic) }}</strong>
        <p class="mf-muted support__note mt-2">{{ formatMoment(request.createdAt) }} мск · Сохранено · Куратор {{ request.curator }}</p>
        <p v-if="request.photoCode" class="support__note mt-2">Фотография {{ request.photoCode }}</p>
        <SupportTimeline :request="request" :extensions="period.extensions" :settlement="order.settlement" />
        <p class="support__message mt-3">{{ request.message }}</p>
        <p class="mf-muted support__note mt-2">Адрес для ответа: {{ request.replyEmail }}</p>
      </article>
    </div>
  </section>
</template>
<style scoped>
.support {
  scroll-margin-top: var(--mf-space-6);
  min-width: 0;
}
.support__note {
  font-size: var(--mf-text-small);
}
.support__form {
  display: grid;
  gap: var(--mf-space-4);
  margin-top: var(--mf-space-6);
}
.support__history {
  margin-top: var(--mf-space-8);
}
.support__subtitle {
  font-size: var(--mf-text-control);
}
.support__request {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  padding-top: var(--mf-space-4);
  margin-top: var(--mf-space-4);
  overflow-wrap: anywhere;
}
.support__message {
  white-space: pre-wrap;
  line-height: 1.6;
}
</style>
