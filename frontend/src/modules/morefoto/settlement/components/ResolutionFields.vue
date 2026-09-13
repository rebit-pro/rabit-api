<script setup lang="ts">
import { computed } from 'vue';
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
import { settlement, remaining, productionStarted } from '../rules';
import { downloadDeadline } from '../../orders/delivery/rules';
import { formatMoment } from '../../handoff/display';
import { money } from '../../commerce/money';
const props = defineProps<{ command: SaleCommand; errors: SaleErrors; order: SaleOrder }>();
defineEmits<{ change: [value: Partial<SaleCommand>] }>();
const refund = computed(() => settlement(props.order).refunds.find((r) => r.id === props.command.refundId));
</script>
<template>
  <div class="sale-fields">
    <template v-if="command.action === 'fulfilment'"
      ><v-select
        :model-value="command.decision"
        label="Решение по исполнению"
        aria-label="Решение по исполнению"
        :items="[
          { value: 'fulfil', title: 'Исполнить заказ' },
          ...(order.latePayment ? [{ value: 'refund', title: 'Вернуть доступный остаток' }] : [])
        ]"
        :error-messages="errors.decision ?? errors.amount"
        :aria-invalid="!!(errors.decision || errors.amount)"
        @update:model-value="$emit('change', { decision: $event })"
      />
      <p v-if="command.decision === 'refund'">
        Возврат {{ money(remaining(order)) }} перейдёт в обработку. Выдача файлов будет приостановлена.
        {{
          productionStarted(order)
            ? 'Производство уже начато: согласуйте изготовленное отдельно.'
            : 'Дальнейшее исполнение будет приостановлено.'
        }}
      </p>
      <p v-else>Исполнение будет согласовано. Оплата сохраняется, повторное списание не выполняется.</p></template
    >
    <p v-if="order.paidAt">
      Исходный срок файлов: {{ formatMoment(downloadDeadline(order.paidAt)) }}. Операция не продлевает календарный месяц.
    </p>
    <template v-if="refund"
      ><p>
        <strong>Возврат {{ money(refund.amount) }}</strong>
      </p>
      <p>
        {{
          command.action === 'retry'
            ? 'Повторно отправить тот же возврат в обработку. Новый возврат не создаётся.'
            : 'После подтверждения сумма уменьшит итог заказа и учреждения.'
        }}
      </p>
      <p>
        План выдачи:
        {{ refund.fileIds === null ? 'прекратить все файлы' : refund.fileIds.length ? 'прекратить выбранные файлы' : 'сохранить доступ' }}.
        {{
          refund.hold && !productionStarted(order) ? 'Дальнейшее исполнение будет приостановлено.' : 'Фактическое производство сохраняется.'
        }}
      </p>
      <p v-if="refund.hold && productionStarted(order)">
        Печать уже начата. Запрошенная остановка автоматически не применяется; нужно отдельное согласование.
      </p></template
    >
    <v-select
      v-if="command.action === 'result' || command.action === 'recover'"
      :model-value="command.result"
      :label="command.action === 'result' ? 'Результат возврата (демо)' : 'Результат письма (демо)'"
      :aria-label="command.action === 'result' ? 'Результат возврата (демо)' : 'Результат письма (демо)'"
      :items="[
        { value: 'confirmed', title: command.action === 'result' ? 'Подтверждён' : 'Отправлено' },
        { value: 'failed', title: command.action === 'result' ? 'Ошибка возврата' : 'Ошибка доставки' }
      ]"
      :error-messages="errors.result"
      :aria-invalid="!!errors.result"
      @update:model-value="$emit('change', { result: $event })"
    />
    <p v-if="command.action === 'result' && command.result === 'failed'">
      Ошибка освободит зарезервированную сумму. Итог, файлы и производство не изменятся.
    </p>
  </div>
</template>
