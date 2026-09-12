<script setup lang="ts">
import { onScopeDispose, ref, useTemplateRef } from 'vue';
import type { UiDensity } from '../types';
defineProps<{ density: UiDensity; longText: boolean }>();
const busy = ref(false);
const count = ref(0);
const notice = ref('');
const confirm = ref(false);
const removeButton = useTemplateRef<{ $el: HTMLButtonElement }>('removeButton');
function returnFocus() {
  removeButton.value?.$el.focus();
}
let timer: ReturnType<typeof setTimeout> | undefined;
function save() {
  if (busy.value) return;
  busy.value = true;
  notice.value = '';
  timer = setTimeout(() => {
    busy.value = false;
    count.value += 1;
    notice.value = 'Пример сохранён. Операций: ' + count.value + '.';
  }, 1000);
}
function remove() {
  confirm.value = false;
  notice.value = 'Демонстрационный элемент удалён.';
}
onScopeDispose(() => clearTimeout(timer));
</script>
<template>
  <section class="mf-panel ui-example-section" aria-labelledby="ui-buttons-title">
    <div>
      <h2 id="ui-buttons-title">Кнопки и действия</h2>
      <p class="mf-muted">Сохранение имитируется на этой странице.</p>
    </div>
    <div class="ui-example-actions">
      <v-btn :density="density === 'compact' ? 'compact' : 'default'" :loading="busy" :disabled="busy" @click="save" data-testid="ui-save">
        {{ longText ? 'Сохранить изменения и вернуться к списку заказов' : 'Сохранить пример' }}
      </v-btn>
      <v-btn variant="outlined" :density="density === 'compact' ? 'compact' : 'default'" @click="notice = 'Изменения примера отменены.'"
        >Отменить</v-btn
      >
      <v-btn variant="text" :density="density === 'compact' ? 'compact' : 'default'" @click="notice = 'Справка для этого примера открыта.'"
        >Помощь</v-btn
      >
      <v-btn
        color="error"
        variant="outlined"
        :density="density === 'compact' ? 'compact' : 'default'"
        ref="removeButton"
        @click="confirm = true"
        >Удалить пример</v-btn
      >
      <v-btn disabled :density="density === 'compact' ? 'compact' : 'default'">Недоступно</v-btn>
      <v-btn icon="mdi-chevron-left" variant="outlined" aria-label="Предыдущий пример" @click="notice = 'Показан предыдущий пример.'" />
      <v-btn icon="mdi-chevron-right" variant="outlined" aria-label="Следующий пример" @click="notice = 'Показан следующий пример.'" />
    </div>
    <p v-if="notice" role="status" data-testid="ui-button-notice">
      {{ notice }}
    </p>
    <v-dialog v-model="confirm" max-width="440" aria-labelledby="ui-confirm-title" @after-leave="returnFocus">
      <v-card class="morefoto-app mf-panel">
        <h2 id="ui-confirm-title">Удалить демонстрационный элемент?</h2>
        <p class="mf-muted mt-3">Это действие относится только к странице образцов.</p>
        <div class="ui-example-actions mt-4">
          <v-btn variant="outlined" @click="confirm = false">Оставить</v-btn>
          <v-btn color="error" @click="remove">Подтвердить удаление</v-btn>
        </div>
      </v-card>
    </v-dialog>
  </section>
</template>
