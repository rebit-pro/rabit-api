<script setup lang="ts">
/** An attempt whose answer was lost is checked before anything else: replaying it can only open the same order. */
defineProps<{ busy: boolean; error: string }>();
const emit = defineEmits<{ retry: [] }>();
</script>
<template>
  <section class="mf-panel mf-empty checkout-recovery" data-testid="checkout-recovery">
    <v-icon icon="mdi-cloud-sync-outline" color="primary" size="42" />
    <h2 class="my-4">Проверим прошлую отправку</h2>
    <p class="mf-muted mb-5">
      Подтверждение предыдущей отправки заказа не получено. Повторите её: если заказ уже создан, откроется он же, второй заказ не появится.
    </p>
    <v-alert v-if="error" id="checkout-error" type="warning" variant="tonal" class="mb-5" role="alert" tabindex="-1">{{ error }}</v-alert>
    <v-btn color="primary" :loading="busy" :disabled="busy" data-testid="recover-order" @click="emit('retry')">Повторить отправку</v-btn>
  </section>
</template>
<style scoped>
.checkout-recovery {
  margin-bottom: var(--mf-space-5);
}
</style>
