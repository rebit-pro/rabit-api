<script setup lang="ts">
import { shallowRef } from 'vue';
import { useReview } from '../useReview';
import ReviewSetup from './ReviewSetup.vue';
import ReviewControls from './ReviewControls.vue';
import ReviewRoutes from './ReviewRoutes.vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
const { data, busy, error, notice, reload, prepare, reset, configure, fail } = useReview();
const confirm = shallowRef(false);
async function restart() {
  await reset();
  if (!error.value) confirm.value = false;
}
</script>
<template>
  <main class="mf-main review-page" data-testid="review-workspace">
    <header class="mf-page-heading">
      <p class="mf-eyebrow">ДЕМОНСТРАЦИОННЫЙ СТЕНД</p>
      <h1>Сквозная проверка MoreFoto</h1>
      <p class="mf-muted mt-3">
        Один связанный набор — от подготовки съёмки до покупки, сопровождения и доставки. Цены и данные здесь тестовые.
      </p>
    </header>
    <v-alert v-if="error" data-testid="review-error" tabindex="-1" role="alert" type="error" variant="tonal"
      >{{ error }}<v-btn v-if="!data" variant="text" @click="reload">Повторить</v-btn></v-alert
    >
    <p v-if="notice" role="status">{{ notice }}</p>
    <p v-if="!data && !error" role="status">Проверяем состояние браузера…</p>
    <template v-if="data"
      ><ReviewSetup :active="!!data.session" :blocked="data.blocked" :busy="busy" @prepare="prepare" @reset="confirm = true" />
      <template v-if="data.session"
        ><ReviewControls
          :now="data.now"
          :delay="data.delay"
          :offline="data.offline"
          :busy="busy"
          @apply="configure"
          @fail="fail" /><ReviewRoutes /></template
    ></template>
    <AdminDialog
      :open="confirm"
      title="Начать проверку заново?"
      :busy="busy"
      :error="error"
      :restored="false"
      save-label="Восстановить исходный набор"
      footer-hint="Отмена сохранит текущий набор без изменений."
      focus-heading
      @close="confirm = false"
      @save="restart"
      @reset="confirm = false"
      ><p>
        Заказы, загруженные в ходе проверки фотографии, черновики и действия этого демонстрационного набора будут удалены. Сессии ролей
        завершатся. Исходные группы и фотографии восстановятся на 8 сентября, 12:00 МСК.
      </p>
      <p class="mt-4">Завершите работу в других вкладках этой проверки перед сбросом.</p></AdminDialog
    >
  </main>
</template>
<style scoped>
.review-page {
  display: grid;
  gap: 24px;
  width: 100%;
  max-width: 1200px;
  padding-block: 40px;
  min-width: 0;
}
.review-page h1 {
  font-size: clamp(28px, 3vw, 38px);
  line-height: 1.2;
}
.review-page :deep(.v-btn) {
  height: auto;
  min-height: 44px;
  padding-block: 10px;
  max-width: 100%;
}
.review-page :deep(.v-btn__content) {
  white-space: normal;
  overflow-wrap: anywhere;
}
.review-page :deep(.mf-panel) {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
