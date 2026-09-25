<script setup lang="ts">
import { countLabel } from '@/components/viz/measures';
import { money } from '../../commerce/money';
import { rateText } from '../payment-costs';
import type { ConditionsSnapshot } from '../api';
defineProps<{ snapshot: ConditionsSnapshot }>();
</script>
<template>
  <div class="conditions-summary" data-testid="conditions-summary">
    <div>
      <span class="mf-muted">В продаже</span
      ><strong>{{ countLabel(snapshot.products.filter((product) => product.active).length, ['позиция', 'позиции', 'позиций']) }}</strong>
    </div>
    <div>
      <span class="mf-muted">Подарочный комплект</span>
      <strong>{{ snapshot.giftThreshold > 0 ? 'От ' + money(snapshot.giftThreshold) : 'Не предлагается' }}</strong>
    </div>
    <div>
      <span class="mf-muted">Подарок сотрудникам</span>
      <strong>{{ snapshot.giftThreshold > 0 && snapshot.giftForStaff ? 'Действует' : 'Не действует' }}</strong>
    </div>
    <div data-testid="payment-costs-summary">
      <span class="mf-muted">Расходы на оплату</span>
      <strong>{{
        snapshot.paymentCosts.enabled ? 'Учитываются: ' + rateText(snapshot.paymentCosts.rateBps) + ' %' : 'Не учитываются'
      }}</strong>
    </div>
  </div>
</template>
<style scoped>
.conditions-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1px;
  background: var(--mf-color-border);
  border: 1px solid var(--mf-color-border);
  border-radius: 4px;
  overflow: hidden;
}
.conditions-summary > div {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 20px;
  background: var(--mf-color-surface);
  min-width: 0;
}
.conditions-summary strong {
  font-size: 18px;
  overflow-wrap: anywhere;
}
@media (max-width: 1100px) {
  .conditions-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 768px) {
  .conditions-summary {
    grid-template-columns: 1fr;
  }
}
</style>
