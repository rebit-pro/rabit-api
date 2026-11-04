<script setup lang="ts">
import { isAxiosError } from 'axios';
import { computed, onMounted, shallowRef, watch } from 'vue';
import { calendarApi, type GroupCalendarView } from '../calendar-api';
import { calendarDays } from '../rules';
import { formatMoment } from '../display';

const props = defineProps<{ groupId: string; groupName: string }>();
const emit = defineEmits<{ close: []; saved: [] }>();
const current = shallowRef<GroupCalendarView | null>(null);
const days = shallowRef(1);
const confirmed = shallowRef(false);
const loading = shallowRef(true);
const busy = shallowRef(false);
const error = shallowRef('');
const requestId = shallowRef(crypto.randomUUID());

const validDays = computed(() => Number.isInteger(days.value) && days.value >= 1 && days.value <= 365);
const oldClose = computed(() => current.value?.calendar.closesAt ?? null);
const newClose = computed(() => (oldClose.value && validDays.value ? calendarDays(oldClose.value, days.value) : null));
const newDigital = computed(() => {
  const previous = current.value?.calendar.digitalSalesUntil;
  return newClose.value && previous ? (Date.parse(newClose.value) > Date.parse(previous) ? newClose.value : previous) : null;
});
const newDelivery = computed(() => (newClose.value ? calendarDays(newClose.value, 7) : null));

watch(days, () => {
  requestId.value = crypto.randomUUID();
  confirmed.value = false;
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = '';
  try {
    current.value = await calendarApi.get(props.groupId);
  } catch {
    error.value = 'Не удалось загрузить актуальные сроки. Повторите попытку.';
  } finally {
    loading.value = false;
  }
}

async function save(): Promise<void> {
  if (!current.value || !validDays.value || !confirmed.value || busy.value || current.value.calendar.status !== 'open') return;
  busy.value = true;
  error.value = '';
  try {
    await calendarApi.extend(props.groupId, current.value.revision, days.value, requestId.value);
    emit('saved');
  } catch (cause) {
    const code = isAxiosError(cause) ? (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code : undefined;
    error.value =
      !isAxiosError(cause) || !cause.response
        ? 'Ответ сервера не получен. Повторите команду без изменения дней: дни не добавятся дважды.'
        : code === 'PRINT_SALES_CLOSED'
          ? 'Приём заказов на печать уже закрыт. Продлить галерею нельзя.'
          : code === 'VERSION_CONFLICT'
            ? 'Сроки изменились. Закройте окно и загрузите актуальные данные.'
            : 'Не удалось продлить галерею. Проверьте сроки и повторите попытку.';
  } finally {
    busy.value = false;
  }
}

onMounted(() => void load());
</script>

<template>
  <v-dialog :model-value="true" max-width="620" @update:model-value="!$event && emit('close')">
    <v-card class="morefoto-app mf-panel pa-6" data-testid="calendar-extension-dialog">
      <h2>Продлить галерею · {{ groupName }}</h2>
      <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка сроков" class="my-5" />
      <v-alert v-if="error" type="error" variant="tonal" role="alert" class="my-4">{{ error }}</v-alert>
      <template v-if="current && !loading">
        <v-alert v-if="current.calendar.status !== 'open'" type="warning" variant="tonal" class="my-4">
          Продлить можно только до окончания приёма заказов на печать.
        </v-alert>
        <template v-else>
          <v-text-field
            v-model.number="days"
            type="number"
            min="1"
            max="365"
            step="1"
            label="Добавить дней к приёму заказов на печать"
            :error-messages="validDays ? [] : ['Укажите от 1 до 365 целых дней.']"
            class="mt-5"
          />
          <div class="handoff-dates mb-5" aria-live="polite">
            <div>
              <span>Сейчас</span>
              <strong>Печать до {{ formatMoment(oldClose) }}</strong>
              <span>Электронные фото до {{ formatMoment(current.calendar.digitalSalesUntil) }}</span>
              <span>Передача печати до {{ formatMoment(current.calendar.deliveryDueAt) }}</span>
            </div>
            <div>
              <span>После продления</span>
              <strong>Печать до {{ formatMoment(newClose) }}</strong>
              <span>Электронные фото до {{ formatMoment(newDigital) }}</span>
              <span>Передача печати до {{ formatMoment(newDelivery) }}</span>
            </div>
          </div>
          <p class="mf-muted mb-3">Дата передачи ссылки и оплаченные заказы сохранятся.</p>
          <v-checkbox v-model="confirmed" label="Проверил новые сроки и подтверждаю продление" hide-details class="mb-4" />
        </template>
        <section class="mt-5" aria-label="История продлений">
          <h3>История продлений</h3>
          <p v-if="!current.history.length" class="mf-muted">Продлений пока не было.</p>
          <ol v-else class="pl-5">
            <li v-for="(event, index) in current.history" :key="index" class="my-3">
              <strong>{{ event.actorName }}</strong> · {{ formatMoment(event.at) }} ·
              {{ event.days === null ? 'изменён срок' : '+' + event.days + ' дн.' }}
              <p class="mf-muted">
                Печать: {{ formatMoment(event.before.closesAt) }} → {{ formatMoment(event.after.closesAt) }}<br />
                Электронные фото:
                {{ formatMoment(event.before.digitalSalesUntil) }} → {{ formatMoment(event.after.digitalSalesUntil) }}<br />
                Передача печати:
                {{ formatMoment(event.before.deliveryDueAt) }} →
                {{ formatMoment(event.after.deliveryDueAt) }}
              </p>
            </li>
          </ol>
        </section>
      </template>
      <div class="mf-actions">
        <v-btn variant="outlined" :disabled="busy" @click="emit('close')">Закрыть</v-btn>
        <v-btn
          v-if="current?.calendar.status === 'open'"
          color="primary"
          :loading="busy"
          :disabled="!validDays || !confirmed"
          @click="save"
        >
          Продлить галерею
        </v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
