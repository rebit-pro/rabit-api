<script setup lang="ts">
import { computed } from 'vue';
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
import { remaining, lineBalances, refundAmount, financials, productionStarted } from '../rules';
import { money } from '../../commerce/money';
const props = defineProps<{ command: SaleCommand; errors: SaleErrors; order: SaleOrder }>();
defineEmits<{ change: [value: Partial<SaleCommand>] }>();
const balance = computed(() => lineBalances(props.order)),
  totals = computed(() => financials([props.order]));
</script>
<template>
  <div class="sale-fields">
    <p>
      <strong>Доступно к возврату: {{ money(remaining(order)) }}</strong
      ><br /><span class="mf-muted">Подтверждено {{ money(totals.refunded) }} · в обработке {{ money(totals.pending) }}</span>
    </p>
    <v-select
      :model-value="command.mode"
      label="Способ расчёта возврата"
      aria-label="Способ расчёта возврата"
      :items="[
        { value: 'amount', title: 'Сумма' },
        { value: 'lines', title: 'Позиции заказа' }
      ]"
      :error-messages="errors.mode"
      @update:model-value="$emit('change', { mode: $event, files: 'keep', lineIds: [] })"
    />
    <v-text-field
      v-if="command.mode === 'amount'"
      :model-value="command.amount"
      label="Сумма возврата, ₽"
      inputmode="decimal"
      :error-messages="errors.amount"
      :aria-invalid="!!errors.amount"
      @update:model-value="$emit('change', { amount: $event })"
    />
    <v-select
      v-else
      :model-value="command.lineIds"
      label="Позиции для возврата"
      aria-label="Позиции для возврата"
      multiple
      chips
      :items="
        order.quote.lines.map((l) => ({
          value: l.id,
          title: l.product.name + ' · ' + (l.photo?.code ?? l.childCode) + ' · остаток ' + money(balance[l.id] ?? 0)
        }))
      "
      :error-messages="errors.lineIds ?? errors.amount"
      :aria-invalid="!!(errors.lineIds || errors.amount)"
      @update:model-value="$emit('change', { lineIds: $event })"
    />
    <v-select
      :model-value="command.files"
      label="Последующая выдача файлов"
      aria-label="Последующая выдача файлов"
      :items="[
        { value: 'keep', title: 'Сохранить доступ' },
        { value: 'all', title: 'Прекратить выдачу всех файлов' },
        ...(command.mode === 'lines' ? [{ value: 'selected', title: 'Прекратить выдачу выбранных файлов' }] : [])
      ]"
      :error-messages="errors.files"
      :aria-invalid="!!errors.files"
      @update:model-value="$emit('change', { files: $event })"
    />
    <v-select
      :model-value="command.production"
      label="Дальнейшее исполнение"
      aria-label="Дальнейшее исполнение"
      :items="[
        { value: 'keep', title: 'Сохранить текущий статус' },
        ...(!productionStarted(order) ? [{ value: 'hold', title: 'Приостановить до согласования' }] : [])
      ]"
      :error-messages="errors.production"
      :aria-invalid="!!errors.production"
      @update:model-value="$emit('change', { production: $event })"
    />
    <div class="sale-preview">
      <strong>После подтверждения возврата</strong>
      <p>
        Возврат {{ money(refundAmount(order, command)) }}. Итог заказа: {{ money(totals.net) }} →
        {{ money(Math.max(0, totals.net - refundAmount(order, command))) }}.
      </p>
      <p>
        {{
          command.files === 'keep'
            ? 'Доступ к файлам сохранится.'
            : command.files === 'all'
              ? 'Выдача всех файлов прекратится.'
              : 'Выдача выбранных электронных позиций прекратится; печатные позиции не отключают подарок.'
        }}
      </p>
      <p>{{ command.production === 'hold' ? 'Дальнейшее исполнение будет приостановлено.' : 'Фактическое производство сохранится.' }}</p>
    </div>
    <p class="mf-muted">
      Сейчас сумма перейдёт в обработку и будет зарезервирована. Итог и ограничения изменятся после подтверждения. Уже полученные файлы и
      изготовленная продукция сохраняются в истории.
    </p>
  </div>
</template>
