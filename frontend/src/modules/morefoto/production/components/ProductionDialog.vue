<script setup lang="ts">
import { computed } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import type { ProductionCommand, ProductionGroup } from '../types';
import { composeVersion, newCount, printCount } from '../rules';
const props = defineProps<{ command: ProductionCommand | null; item: ProductionGroup; busy: boolean; error: string; restored: boolean }>();
const emit = defineEmits<{ close: []; save: []; reset: []; reason: [value: string] }>();
const title = computed(() =>
  props.command?.kind === 'version'
    ? 'Проверка печатного задания'
    : props.command?.kind === 'start'
      ? 'Учёт запуска печати'
      : 'Проверка пакета заказа'
);
const label = computed(() =>
  props.command?.kind === 'version' ? 'Сохранить версию' : props.command?.kind === 'start' ? 'Подтвердить запуск' : 'Сохранить комплектацию'
);
const preview = computed(() => composeVersion(props.item.plan, props.item.job, '', '', ''));
const latest = computed(() => props.item.job?.versions.slice(-1)[0]);
const commandCurrent = computed(
  () => props.command?.signature === props.item.plan.signature && props.command?.revision === (props.item.job?.revision ?? 0)
);
</script>
<template>
  <AdminDialog
    :open="!!command"
    :title="title"
    :busy="busy"
    :error="error"
    :restored="restored"
    :save-label="label"
    focus-heading
    @close="emit('close')"
    @save="emit('save')"
    @reset="emit('reset')"
  >
    <template v-if="command"
      ><p>{{ item.institutionName }} · {{ item.group.name }}</p>
      <v-alert v-if="!commandCurrent" class="my-4" type="warning" variant="tonal"
        >Данные изменились после открытия редактора. Повтор потерянного ответа безопасен; для нового действия загрузите актуальные
        данные.</v-alert
      >
      <template v-if="command.kind === 'version'"
        ><p class="mt-4">
          <strong>{{ newCount(preview) }}</strong> новых отпечатков · {{ printCount(item.plan.rows) }} всего ·
          {{ new Set(item.plan.rows.map((r) => r.orderId)).size }} пакетов
        </p>
        <p class="mf-muted mt-2">Исключено заказов: {{ item.plan.excluded.length }}. Электронных позиций: {{ item.plan.digitalCount }}.</p>
        <p v-if="preview.surplus.length" class="mt-3">
          Есть ранее запущенные отпечатки сверх нового состава: {{ preview.surplus.reduce((n, r) => n + r.next, 0) }}. Они будут перечислены
          для отдельной сверки.
        </p></template
      >
      <template v-else-if="command.kind === 'start'"
        ><p class="mt-4">
          {{ item.job?.number }} · версия {{ latest?.number }} · к новой печати {{ latest ? newCount(latest) : 0 }} отпечатков.
        </p>
        <p class="mf-muted mt-2">Подтверждение фиксирует один запуск этой версии. Повторное действие не увеличит количество.</p></template
      >
      <template v-else
        ><p class="mt-4">Заказ {{ latest?.plan.rows.find((r) => r.orderId === command?.orderId)?.orderNumber }}</p>
        <p class="mf-muted mt-2">
          {{ command.packed ? 'Сверьте кадры, форматы, количество и подпись на отдельном пакете.' : 'Пакет снова будет ожидать сверки.' }}
        </p></template
      >
      <v-textarea
        class="mt-5"
        label="Комментарий к действию"
        :model-value="command.reason"
        :maxlength="500"
        counter="500"
        rows="3"
        auto-grow
        :disabled="busy"
        :error="!!error && !command.reason.trim()"
        hint="Сохраняется в истории задания"
        persistent-hint
        @update:model-value="emit('reason', $event)"
      />
    </template>
  </AdminDialog>
</template>
