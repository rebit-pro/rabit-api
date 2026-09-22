<script setup lang="ts">
import { computed } from 'vue';
import type { LinkCommand, LinkGroup, HandoffErrors } from '../types';
import { calendarDays, closingAfterCorrection, parseTransmission } from '../rules';
import { formatMoment, problemText } from '../display';
import { isMockApiEnabled } from '@/mocks/config';
const props = defineProps<{ command: LinkCommand; group: LinkGroup; now: string; errors: HandoffErrors }>();
const emit = defineEmits<{ change: [value: Partial<LinkCommand>] }>();
const sent = computed(() => parseTransmission(props.command.sentAt, props.now));
const closing = computed(() => (sent.value ? closingAfterCorrection(sent.value, props.group.extensionClosesAt) : null));
</script>
<template>
  <p class="mb-5">
    <strong>{{ group.name }}</strong> · {{ group.shootName }}
  </p>
  <template v-if="command.action === 'prepare'">
    <p class="mb-4">
      {{ group.photoCount }} фото · {{ group.childCount }} наборов. После проверки ответственный сможет отметить передачу ссылки родителям.
    </p>
    <v-alert v-if="group.problems.length" type="warning" variant="tonal" class="mb-5">{{
      group.problems.map(problemText).join(' ')
    }}</v-alert>
    <v-checkbox
      :model-value="command.photosReviewed"
      label="Фотографии и коды проверены"
      :error-messages="errors.photosReviewed"
      :aria-invalid="!!errors.photosReviewed"
      @update:model-value="emit('change', { photosReviewed: !!$event })"
    />
    <v-checkbox
      :model-value="command.conditionsReviewed"
      label="Продукция, цены и условия группы проверены"
      :error-messages="errors.conditionsReviewed"
      :aria-invalid="!!errors.conditionsReviewed"
      @update:model-value="emit('change', { conditionsReviewed: !!$event })"
    />
    <v-checkbox
      :model-value="command.staffReviewed"
      label="Списки сотрудников и ответственные проверены"
      :error-messages="errors.staffReviewed"
      :aria-invalid="!!errors.staffReviewed"
      @update:model-value="emit('change', { staffReviewed: !!$event })"
    />
    <p class="mf-muted">Проверка фиксируется для текущего состава фотографий и условий. Изменения потребуют повторной проверки.</p>
  </template>
  <template v-else>
    <p class="mb-4">Укажите, когда ссылка действительно была передана родителям. Приём длится 7 календарных дней с этого момента.</p>
    <p class="mf-muted mb-5">{{ isMockApiEnabled ? 'Текущее время демонстрации' : 'Текущее время' }}: {{ formatMoment(now) }}.</p>
    <v-text-field
      :model-value="command.sentAt"
      label="Дата и время передачи (МСК)"
      type="datetime-local"
      :error-messages="errors.sentAt"
      :aria-invalid="!!errors.sentAt"
      @update:model-value="emit('change', { sentAt: $event })"
    />
    <div class="handoff-dates mb-5" aria-live="polite">
      <div v-if="command.action === 'correct'">
        <span>До исправления</span><strong>Приём до {{ formatMoment(group.closesAt) }}</strong
        ><span>Доставка до {{ formatMoment(group.deliveryAt) }}</span>
      </div>
      <div>
        <span>{{ command.action === 'correct' ? 'После исправления' : 'Сроки' }}</span
        ><strong>Приём до {{ formatMoment(closing) }}</strong
        ><span>Доставка до {{ formatMoment(closing ? calendarDays(closing, 7) : null) }}</span>
      </div>
    </div>
    <p v-if="group.extensionClosesAt" class="mf-muted mb-4">Согласованное продление сохраняется при исправлении даты передачи.</p>
    <v-textarea
      v-if="command.action === 'correct'"
      :model-value="command.reason"
      label="Причина исправления"
      rows="3"
      counter="500"
      :error-messages="errors.reason"
      :aria-invalid="!!errors.reason"
      @update:model-value="emit('change', { reason: $event })"
    />
    <p v-if="command.action === 'correct'" class="mb-4 mf-muted">
      Исправление изменит доступность новых заказов. Оплаченные заказы сохранятся. История прежней даты останется в карточке.
    </p>
    <v-checkbox
      :model-value="command.confirmed"
      label="Подтверждаю факт передачи и указанные сроки"
      :error-messages="errors.confirmed"
      :aria-invalid="!!errors.confirmed"
      @update:model-value="emit('change', { confirmed: !!$event })"
    />
  </template>
</template>
