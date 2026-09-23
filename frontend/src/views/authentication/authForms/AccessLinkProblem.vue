<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ code: string; purpose: 'invite' | 'reset' }>();
const view = computed(() => {
  if ('LINK_USED' === props.code) {
    return 'invite' === props.purpose
      ? { text: 'Ссылка уже использована. Если вы задали пароль, войдите в кабинет.', action: 'Войти', to: '/login' }
      : {
          text: 'Ссылка уже использована. Если пароль снова нужен, запросите новую ссылку.',
          action: 'Запросить ссылку',
          to: '/access/recover'
        };
  }
  if ('LINK_EXPIRED' === props.code) {
    return 'invite' === props.purpose
      ? { text: 'Срок приглашения истёк. Попросите организатора отправить приглашение повторно.', action: 'Ко входу', to: '/login' }
      : { text: 'Срок ссылки истёк: она действует 60 минут. Запросите новую ссылку.', action: 'Запросить ссылку', to: '/access/recover' };
  }
  return 'invite' === props.purpose
    ? {
        text: 'Ссылка не найдена. Проверьте, что открыли её из письма целиком, или попросите приглашение повторно.',
        action: 'Ко входу',
        to: '/login'
      }
    : {
        text: 'Ссылка не найдена. Проверьте, что открыли её из письма целиком, или запросите новую.',
        action: 'Запросить ссылку',
        to: '/access/recover'
      };
});
</script>

<template>
  <div class="mt-6" data-testid="access-link-problem">
    <v-alert type="warning" variant="tonal" role="alert">{{ view.text }}</v-alert>
    <v-btn :to="view.to" block class="mt-5">{{ view.action }}</v-btn>
  </div>
</template>
