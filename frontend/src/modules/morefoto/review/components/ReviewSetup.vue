<script setup lang="ts">
defineProps<{ active: boolean; blocked: boolean; busy: boolean }>();
const emit = defineEmits<{ prepare: []; reset: [] }>();
</script>
<template>
  <section class="mf-panel">
    <h2>{{ active ? 'Набор готов к работе' : blocked ? 'В браузере уже есть данные' : 'Подготовка проверки' }}</h2>
    <template v-if="blocked"
      ><p class="mt-3">
        Существующие заказы, настройки, сессии и локальные фотографии сохраняются. Подготовка нового набора здесь недоступна.
      </p>
      <p class="mf-muted mt-3">
        Откройте эту страницу в отдельном приватном окне браузера. Все роли проходите внутри этого окна, чтобы они видели одни и те же
        изменения.
      </p></template
    ><template v-else-if="active"
      ><p class="mt-3">
        «Солнечный» — основное учреждение, школа — отдельная область. Есть подготовленные фотографии и пустая осенняя съёмка. Продажи
        открываются после проверки и фактической передачи ссылки.
      </p>
      <p class="mf-muted mt-3">
        Заказы и производственные события появятся после ваших действий. Для каждой независимой проверки можно восстановить начало.
      </p>
      <v-btn class="mt-5" variant="outlined" :disabled="busy" @click="emit('reset')">Начать проверку заново</v-btn></template
    ><template v-else
      ><p class="mt-3">Будут созданы тестовые учреждения, съёмки, группы и фотографии. Оплаченных заказов в начале нет.</p>
      <p class="mf-muted mt-3">Набор хранится в этом браузере. Платежи, сообщения и изготовление имитируются.</p>
      <v-btn class="mt-5" :loading="busy" :disabled="busy" @click="emit('prepare')">Подготовить набор проверки</v-btn></template
    >
  </section>
</template>
