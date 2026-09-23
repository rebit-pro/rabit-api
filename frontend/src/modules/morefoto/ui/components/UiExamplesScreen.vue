<script setup lang="ts">
import { ref } from 'vue';
import type { UiDensity, UiFieldState } from '../types';
import UiFieldExamples from './UiFieldExamples.vue';
import UiCompositeExamples from './UiCompositeExamples.vue';
import UiSelectionExamples from './UiSelectionExamples.vue';
import UiButtonExamples from './UiButtonExamples.vue';
import UiTableExample from './UiTableExample.vue';
import UiTokenExamples from './UiTokenExamples.vue';
import UiTypographyExamples from './UiTypographyExamples.vue';
const density = ref<UiDensity>('comfortable');
const state = ref<UiFieldState>('empty');
const longText = ref(false);
const revision = ref(0);
const states = [
  { title: 'Пустые', value: 'empty' },
  { title: 'Заполненные', value: 'filled' },
  { title: 'Ошибка', value: 'error' },
  { title: 'Отключены', value: 'disabled' },
  { title: 'Только чтение', value: 'readonly' },
  { title: 'Загрузка', value: 'loading' }
];
function reset() {
  density.value = 'comfortable';
  state.value = 'empty';
  longText.value = false;
  revision.value += 1;
}
</script>
<template>
  <main class="morefoto-app ui-examples">
    <div class="mf-main">
      <router-link to="/login" class="mf-back">← К входу</router-link>
      <header class="mf-page-heading">
        <p class="mf-eyebrow">MoreFoto · образцы интерфейса</p>
        <h1>Поля и кнопки</h1>
        <p class="mf-muted">Общие элементы для форм MoreFoto. Изменения здесь не затрагивают вашу корзину и заказы.</p>
      </header>
      <nav class="ui-example-actions my-4" aria-label="Разделы образцов">
        <a href="#ui-forms" class="mf-back">Поля и кнопки</a><a href="#ui-tables" class="mf-back">Таблицы и списки</a
        ><a href="#ui-tokens" class="mf-back">Токены</a><a href="#ui-typography" class="mf-back">Типографика</a>
      </nav>
      <section id="ui-forms" class="mf-panel ui-example-controls" aria-label="Настройки образцов">
        <div class="ui-density" role="group" aria-label="Плотность">
          <v-btn
            :variant="density === 'comfortable' ? 'flat' : 'outlined'"
            :aria-pressed="density === 'comfortable'"
            @click="density = 'comfortable'"
            >Обычная</v-btn
          >
          <v-btn :variant="density === 'compact' ? 'flat' : 'outlined'" :aria-pressed="density === 'compact'" @click="density = 'compact'"
            >Компактная</v-btn
          >
        </div>
        <v-select v-model="state" label="Состояние полей" aria-label="Состояние полей" :items="states" class="ui-state" />
        <v-switch v-model="longText" label="Длинные подписи" class="ui-long-text" />
        <v-btn variant="text" @click="reset">Сбросить образцы</v-btn>
      </section>
      <div :key="revision" class="ui-example-sections">
        <UiFieldExamples :density="density" :state="state" :long-text="longText" />
        <UiCompositeExamples :density="density" :state="state" />
        <div class="ui-example-pair">
          <UiSelectionExamples :state="state" :long-text="longText" />
          <UiButtonExamples :density="density" :long-text="longText" />
        </div>
        <header id="ui-tables" class="ui-tables-heading">
          <h2>Таблицы и списки</h2>
          <p class="mf-muted">Два независимых образца для будущих кабинетов. Фильтры и действия работают только с вымышленными записями.</p>
        </header>
        <UiTableExample kind="institutions" :density="density" />
        <UiTableExample kind="orders" :density="density" />
        <UiTokenExamples />
        <UiTypographyExamples />
      </div>
    </div>
  </main>
</template>
<style>
.ui-examples {
  min-height: 100svh;
  background: rgb(var(--v-theme-background));
}
.ui-examples .mf-panel {
  padding: var(--mf-space-6);
}
.ui-example-controls,
.ui-density,
.ui-example-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--mf-space-3);
}
.ui-state {
  flex: 1 1 220px !important;
  max-width: 280px;
}
.ui-long-text {
  min-width: 220px;
}
.ui-example-sections {
  display: grid;
  gap: var(--mf-space-6);
  margin-top: var(--mf-space-6);
}
.ui-example-section {
  display: grid;
  gap: var(--mf-space-4);
  align-content: start;
  min-width: 0;
}
.ui-example-section h2 + p {
  margin-top: var(--mf-space-2);
  font-size: var(--mf-text-small);
}
.ui-fields,
.ui-example-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--mf-space-6);
  align-items: start;
}
@media (max-width: 767px) {
  .ui-examples .mf-panel {
    padding: var(--mf-space-4);
  }
  .ui-fields,
  .ui-example-pair {
    grid-template-columns: minmax(0, 1fr);
  }
  .ui-state {
    max-width: none;
    flex-basis: 100% !important;
  }
}
</style>
