<script setup lang="ts">
import { shallowRef } from 'vue';
import type { OrderSnapshot } from '../types';
const props = defineProps<{ order: OrderSnapshot }>();
const copied = shallowRef(false);
const error = shallowRef('');
async function copyLink() {
  error.value = '';
  try {
    await navigator.clipboard.writeText(window.location.origin + '/orders/access/' + props.order.accessKey);
    copied.value = true;
  } catch {
    error.value = 'Не удалось скопировать автоматически. Сохраните адрес страницы из строки браузера.';
  }
}
</script>
<template>
  <v-btn block variant="outlined" color="primary" class="mt-5" @click="copyLink">Скопировать личную ссылку</v-btn>
  <p v-if="copied" role="status" class="order-access-note">Ссылка скопирована. Сохраните её для возврата к заказу.</p>
  <p v-if="error" role="alert" class="order-access-note">{{ error }}</p>
  <p class="mf-muted order-access-note mt-4">
    Ссылка предназначена только покупателю. Не размещайте её в общем чате группы. В демонстрации заказ хранится в этом браузере.
  </p>
</template>
<style scoped>
.order-access-note {
  font-size: 13px;
  line-height: 1.7;
  margin-top: 12px;
}
</style>
