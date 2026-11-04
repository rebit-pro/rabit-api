<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { openingApi } from '../opening-api';
import { formatMoment } from '../display';
import { moscowInput, serverMoment } from '../rules';
import type { LinkGroup } from '../types';

const props = defineProps<{ group: LinkGroup; now: string }>();
const emit = defineEmits<{ close: []; saved: [] }>();
const initial = props.group.scheduledOpenAt ? moscowInput(props.group.scheduledOpenAt) : '';
const openAt = shallowRef(initial);
const confirmed = shallowRef(false);
const busy = shallowRef(false);
const error = shallowRef('');
const requestId = shallowRef(crypto.randomUUID());
const valid = computed(() => {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(openAt.value)) return false;
  const moment = Date.parse(serverMoment(openAt.value));
  return Number.isFinite(moment) && moment > Date.parse(props.now) && moscowInput(new Date(moment).toISOString()) === openAt.value;
});
watch(openAt, () => {
  requestId.value = crypto.randomUUID();
  confirmed.value = false;
});
async function save(): Promise<void> {
  if (!valid.value || !confirmed.value || busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await openingApi.schedule(props.group.id, props.group.revision, props.group.signature, serverMoment(openAt.value), requestId.value);
    emit('saved');
  } catch {
    error.value = 'Не удалось назначить открытие. Проверьте актуальность группы и повторите без изменения времени.';
  } finally {
    busy.value = false;
  }
}
async function cancel(): Promise<void> {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await openingApi.cancel(props.group.id, props.group.revision, requestId.value);
    emit('saved');
  } catch {
    error.value = 'Не удалось отменить открытие. Обновите группу и повторите.';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <v-dialog :model-value="true" max-width="560" @update:model-value="!$event && emit('close')">
    <v-card class="morefoto-app mf-panel pa-6" data-testid="opening-schedule-dialog">
      <h2>Открытие галереи · {{ group.name }}</h2>
      <p v-if="group.openingStatus === 'scheduled'" class="mt-4">Сейчас назначено: {{ formatMoment(group.scheduledOpenAt ?? null) }}</p>
      <p v-if="group.openingStatus === 'blocked'" class="mt-4">
        Прошлый план остановлен: готовность группы изменилась. Проверьте ссылку и назначьте новое время.
      </p>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" class="my-4">{{ error }}</v-alert>
      <v-text-field
        v-model="openAt"
        type="datetime-local"
        label="Дата и время открытия (МСК)"
        :error-messages="openAt && !valid ? ['Укажите будущее время по Москве.'] : []"
        class="mt-5"
      />
      <p class="mf-muted mb-3">
        Родители смогут открыть ссылку заранее. Заказы станут доступны после автоматического открытия; семь дней начнутся с этого момента.
      </p>
      <v-checkbox v-model="confirmed" label="Подтверждаю время автоматического открытия" hide-details />
      <div class="mf-actions mt-5">
        <v-btn variant="outlined" :disabled="busy" @click="emit('close')">Закрыть</v-btn>
        <v-btn
          v-if="group.openingStatus === 'scheduled' || group.openingStatus === 'blocked'"
          variant="outlined"
          :loading="busy"
          @click="cancel"
          >Отменить план</v-btn
        >
        <v-btn color="primary" :loading="busy" :disabled="!valid || !confirmed" @click="save">{{
          group.openingStatus === 'scheduled' ? 'Перенести открытие' : 'Назначить открытие'
        }}</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
