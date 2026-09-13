<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { moscowInput } from '../../handoff/rules';
import type { ReviewSettings } from '../rules';
const props = defineProps<{ now: string; delay: number; offline: boolean; busy: boolean }>();
const emit = defineEmits<{ apply: [settings: ReviewSettings]; fail: [] }>();
const date = shallowRef(moscowInput(props.now)),
  delay = shallowRef(props.delay),
  offline = shallowRef(props.offline);
watch(
  () => props.now,
  (v) => {
    date.value = moscowInput(v);
  }
);
watch(
  () => props.delay,
  (v) => {
    delay.value = v;
  }
);
watch(
  () => props.offline,
  (v) => {
    offline.value = v;
  }
);
</script>
<template>
  <section class="mf-panel">
    <h2>Условия проверки</h2>
    <p class="mf-muted mt-3">
      Время влияет на приём заказов, доставку и месячный доступ к файлам. Условия сохраняются при переходах и действуют во всех вкладках
      этого набора.
    </p>
    <form class="review-controls mt-5" @submit.prevent="emit('apply', { date, delay, offline })">
      <v-text-field v-model="date" label="Время сценария (МСК)" type="datetime-local" :disabled="busy" hide-details /><v-select
        v-model="delay"
        label="Задержка ответа"
        aria-label="Задержка ответа"
        :items="[
          { title: 'Без задержки', value: 0 },
          { title: 'Обычная · 0,25 с', value: 250 },
          { title: 'Медленно · 1,5 с', value: 1500 },
          { title: 'Медленно · 3 с', value: 3000 }
        ]"
        :disabled="busy"
        hide-details
      /><v-checkbox v-model="offline" label="Сеть недоступна" :disabled="busy" hide-details />
      <div class="mf-actions">
        <v-btn type="submit" :disabled="busy">Применить условия</v-btn
        ><v-btn variant="outlined" :disabled="busy || offline" @click="emit('fail')">Ошибка следующего запроса</v-btn>
      </div>
    </form>
    <p class="mf-muted mt-4">
      Чтобы продолжить после ошибки сети, снимите отметку и примените условия. Эти инструменты остаются доступны при имитации сбоя.
    </p>
  </section>
</template>
<style scoped>
.review-controls {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}
.review-controls .mf-actions {
  grid-column: 1/-1;
}
@media (max-width: 700px) {
  .review-controls {
    grid-template-columns: 1fr;
  }
}
</style>
