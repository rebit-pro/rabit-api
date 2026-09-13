<script setup lang="ts">
import { computed } from 'vue';
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
import { currentLines, productionStarted } from '../rules';
import { correctionPhotos } from '../service';
import { money } from '../../commerce/money';
const props = defineProps<{ command: SaleCommand; errors: SaleErrors; order: SaleOrder }>();
const emit = defineEmits<{ change: [value: Partial<SaleCommand>] }>();
const lines = computed(() => currentLines(props.order)),
  line = computed(() => lines.value.find((l) => l.id === props.command.lineId));
const options = computed(() => {
  const photos = correctionPhotos(props.order, props.command.lineId);
  const original = line.value?.photo;
  return original && !photos.some((p) => p.id === original.id) ? [original, ...photos] : photos;
});
function select(id: string) {
  const row = lines.value.find((l) => l.id === id);
  emit('change', { lineId: id, photoId: row?.photoId ?? '', quantity: String(row?.quantity ?? 0) });
}
</script>
<template>
  <div class="sale-fields">
    <v-select
      :model-value="command.lineId"
      label="Позиция заказа"
      aria-label="Позиция заказа"
      :items="
        lines.map((l) => ({ value: l.id, title: l.product.name + ' · ' + (l.photo?.code ?? l.childCode) + ' · ' + l.quantity + ' шт.' }))
      "
      :error-messages="errors.lineId"
      :aria-invalid="!!errors.lineId"
      @update:model-value="select"
    />
    <p v-if="line"><strong>Было:</strong> {{ line.photo?.code ?? line.childCode }} · {{ line.quantity }} шт. · {{ money(line.total) }}</p>
    <v-select
      v-if="line?.product.kind !== 'bundle'"
      :model-value="command.photoId"
      label="Новый кадр"
      aria-label="Новый кадр"
      :items="options"
      item-title="code"
      item-value="id"
      :error-messages="errors.photoId"
      :aria-invalid="!!errors.photoId"
      @update:model-value="$emit('change', { photoId: $event })"
    />
    <v-text-field
      :model-value="command.quantity"
      label="Новое количество"
      inputmode="numeric"
      :error-messages="errors.quantity"
      :aria-invalid="!!errors.quantity"
      hint="От 0 до текущего количества. Увеличение оформляется отдельным заказом."
      persistent-hint
      @update:model-value="$emit('change', { quantity: $event })"
    />
    <p v-if="line">
      <strong>Станет:</strong> {{ options.find((p) => p.id === command.photoId)?.code ?? line.childCode }} · {{ command.quantity }} шт. Цена
      при покупке: {{ money(line.unitPrice) }} за единицу.
    </p>
    <p class="mf-muted">
      Оплаченная сумма не изменится. При уменьшении количества возврат оформляется отдельно. Подарок и скидки покупки сохраняются.
    </p>
    <p>
      {{
        productionStarted(order)
          ? 'Печать уже начата: фактическое изготовление сохраняется, для печатной позиции потребуется согласование перепечатки.'
          : 'Изменение печатной позиции приостановит дальнейшее исполнение до согласования.'
      }}
    </p>
    <p class="mf-muted">Электронный кадр заменится в последующей выдаче. Уже полученные файлы невозможно отозвать.</p>
  </div>
</template>
