<script setup lang="ts">
import { computed } from 'vue';
import type { SaleOrder, SaleCommand } from '../types';
import { settlement, currentLines, financials } from '../rules';
import { money } from '../../commerce/money';
import { formatMoment } from '../../handoff/display';
import SettlementTotals from './SettlementTotals.vue';
const props = defineProps<{ order: SaleOrder; editable?: boolean }>();
defineEmits<{ edit: [action: SaleCommand['action'], refundId?: string] }>();
const state = computed(() => settlement(props.order)),
  lines = computed(() => currentLines(props.order));
const refundLabels = { pending: 'Обрабатывается', confirmed: 'Подтверждён', failed: 'Ошибка — деньги не возвращены' };
</script>
<template>
  <div class="sale-history" data-testid="settlement-history">
    <SettlementTotals :totals="financials([order])" single />
    <p v-if="state.hold" class="sale-warning">
      Дальнейшее исполнение приостановлено до согласования. Фактический статус производства сохранён.
    </p>
    <p v-if="state.needsReprint && !state.reprintApproved" class="sale-warning">
      Изменения затрагивают уже начатую печать. Требуется согласование перепечатки; изготовленное не отменено.
    </p>
    <p v-if="state.reprintApproved" class="sale-warning">
      Перепечатка согласована. Новое изготовление потребуется подготовить отдельно; исходный статус производства сохранён.
    </p>
    <section v-if="state.corrections.some((c) => c.kind === 'line')" class="sale-current">
      <h3>Согласованный состав для исполнения</h3>
      <p v-for="line in lines" :key="line.id">
        {{ line.product.name }} · {{ line.photo?.code ?? line.childCode }} · {{ line.quantity ? line.quantity + ' шт.' : 'Исключено'
        }}<br /><span class="mf-muted">Цена при покупке: {{ money(line.unitPrice) }} за единицу</span>
      </p>
      <p class="mf-muted">
        Исходный состав и оплаченная сумма сохранены ниже. Возврат оформляется отдельно; скидки и подарок покупки не пересчитываются.
      </p>
    </section>
    <article v-for="c in state.corrections" :key="c.id" class="sale-event" data-testid="sale-correction">
      <h3>{{ c.kind === 'contacts' ? 'Контакты исправлены' : 'Позиция исправлена' }}</h3>
      <p class="mf-muted">{{ c.actorName }} · {{ formatMoment(c.at) }}</p>
      <div v-if="c.buyerBefore && c.buyerAfter" class="sale-comparison">
        <div>
          <strong>Было</strong>
          <p>{{ c.buyerBefore.name }}<br />{{ c.buyerBefore.email }}<br />{{ c.buyerBefore.phone }}</p>
        </div>
        <div>
          <strong>Стало</strong>
          <p>{{ c.buyerAfter.name }}<br />{{ c.buyerAfter.email }}<br />{{ c.buyerAfter.phone }}</p>
        </div>
      </div>
      <div v-if="c.before && c.after" class="sale-comparison">
        <div>
          <strong>Было</strong>
          <p>{{ c.before.product.name }} · {{ c.before.photo?.code ?? c.before.childCode }} · {{ c.before.quantity }} шт.</p>
        </div>
        <div>
          <strong>Стало</strong>
          <p>{{ c.after.product.name }} · {{ c.after.photo?.code ?? c.after.childCode }} · {{ c.after.quantity }} шт.</p>
        </div>
      </div>
      <p class="sale-reason">{{ c.reason }}</p>
      <p v-if="c.needsReprint" class="mf-muted">Начатая печать сохранена; нужна проверка перепечатки.</p>
    </article>
    <article v-for="r in state.refunds" :key="r.id" class="sale-event" data-testid="sale-refund">
      <h3>Возврат {{ money(r.amount) }}</h3>
      <p>
        <strong>{{ refundLabels[r.status] }}</strong>
      </p>
      <p class="sale-reason">{{ r.reason }}</p>
      <p class="mf-muted">
        Файлы после подтверждения:
        {{
          r.fileIds === null
            ? 'прекратить последующую выдачу всех файлов'
            : r.fileIds.length
              ? 'прекратить выдачу выбранных файлов'
              : 'сохранить доступ'
        }}. {{ r.hold ? 'Запрошена остановка дальнейшего исполнения.' : 'Производство сохраняет текущий статус.' }}
      </p>
      <p v-if="r.status === 'confirmed' && r.hold && !r.holdApplied" class="mf-muted">
        На момент подтверждения производство уже начато. Автоматическая остановка не выполнена; требуется согласование.
      </p>
      <ol>
        <li v-for="(h, i) in r.history" :key="i">
          {{ refundLabels[h.status] }} · {{ formatMoment(h.at) }} · {{ h.actorName }}
          <p class="sale-reason">{{ h.reason }}</p>
        </li>
      </ol>
      <div v-if="editable" class="mf-actions">
        <v-btn v-if="r.status === 'pending'" variant="outlined" @click="$emit('edit', 'result', r.id)">Результат возврата</v-btn
        ><v-btn v-if="r.status === 'failed'" variant="outlined" @click="$emit('edit', 'retry', r.id)">Повторить возврат</v-btn>
      </div>
    </article>
    <article v-for="d in state.decisions" :key="d.id" class="sale-event" data-testid="sale-decision">
      <h3>{{ d.decision === 'fulfil' ? 'Исполнение согласовано' : 'Согласован возврат' }}</h3>
      <p class="mf-muted">{{ d.actorName }} · {{ formatMoment(d.at) }}</p>
      <p class="sale-reason">{{ d.reason }}</p>
    </article>
    <article v-for="r in state.recoveries" :key="r.id" class="sale-event" data-testid="sale-recovery">
      <h3>Повторное письмо с файлами</h3>
      <p>{{ r.status === 'sent' ? 'Отправка имитирована' : 'Ошибка доставки' }} · {{ r.email }}</p>
      <p class="mf-muted">{{ r.actorName }} · {{ formatMoment(r.at) }}</p>
      <p class="sale-reason">{{ r.reason }}</p>
    </article>
    <details v-if="state.preparedFiles.length" class="sale-event">
      <summary>История подготовки файлов: {{ state.preparedFiles.length }}</summary>
      <p v-for="file in state.preparedFiles" :key="file.id">
        {{ formatMoment(file.at) }} · {{ file.photoIds.length }} файлов подготовлено для скачивания
      </p>
      <p class="mf-muted">Подготовка не подтверждает сохранение на устройстве. Уже полученные файлы отозвать невозможно.</p>
    </details>
  </div>
</template>
<style scoped>
.sale-history {
  min-width: 0;
}
.sale-event {
  border-top: 1px solid #dce3e8;
  padding: 22px 0;
  overflow-wrap: anywhere;
}
.sale-event h3 {
  font-size: 18px;
}
.sale-event p,
.sale-current p {
  margin-top: 10px;
  line-height: 1.6;
}
.sale-event li {
  margin: 12px 0;
}
.sale-event ol {
  padding-left: 22px;
}
.sale-reason {
  white-space: pre-wrap;
}
.sale-comparison {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin-top: 18px;
}
.sale-comparison div {
  padding: 16px;
  background: #f3f7fa;
  border-radius: 4px;
}
.sale-warning {
  padding: 16px;
  background: #fff6e7;
  line-height: 1.6;
  margin-bottom: 16px;
}
.sale-current {
  padding: 20px;
  background: #f3f7fa;
  margin-bottom: 20px;
  border-radius: 4px;
}
@media (max-width: 599px) {
  .sale-comparison {
    grid-template-columns: 1fr;
  }
}
</style>
