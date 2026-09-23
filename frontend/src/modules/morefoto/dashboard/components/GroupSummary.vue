<script setup lang="ts">
import { computed } from 'vue';
import type { Group } from '../../types';
import type { FinancialTotals } from '../../settlement/types';
import type { GroupWork } from '../types';
import { stateLabels } from '../types';
import { formatMoment } from '../../handoff/display';
import { money } from '../../commerce/money';
import { useGroupLink } from '../useGroupLink';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { groupStateTone } from '../../ui/statusTone';
const props = defineProps<{
  readonly group: Group;
  readonly work?: GroupWork;
  readonly totals?: FinancialTotals;
  readonly detail?: boolean;
  readonly teacher?: boolean;
  readonly groupTo?: { path: string; query: Record<string, string> };
}>();
const { url, copy, notice } = useGroupLink();
const linkLabel = computed(() =>
  props.work?.linkState === 'sent' ? 'Ссылка передана' : props.work?.linkState === 'ready' ? 'Готова к передаче' : 'Ссылка на проверке'
);
const periodTo = computed(() => ({
  path: '/cabinet/links',
  query: { group: props.group.id }
}));
</script>
<template>
  <article class="group-summary" :class="{ 'group-summary--staff': group.kind === 'staff' }" :data-testid="'summary-' + group.id">
    <header class="group-summary__header">
      <div>
        <p v-if="group.kind === 'staff'" class="mf-eyebrow">СОТРУДНИКИ</p>
        <h2 v-if="detail" class="group-summary__title">Сроки и ссылка группы</h2>
        <h3 v-else class="group-summary__title">{{ group.name }}</h3>
      </div>
      <MfStatus :tone="toneOf(groupStateTone, group.state)">{{ stateLabels[group.state] }}</MfStatus>
    </header>
    <p class="group-summary__link-state">
      <v-icon :icon="work?.sentAt ? 'mdi-check-circle-outline' : 'mdi-link-variant'" size="18" />{{ linkLabel }}
    </p>
    <dl v-if="totals" class="group-summary__totals" aria-label="Итог группы">
      <div>
        <dt>Оплаченных заказов</dt>
        <dd>{{ totals.paidCount }}</dd>
      </div>
      <div>
        <dt>После возвратов</dt>
        <dd>{{ money(totals.net) }}</dd>
      </div>
      <div>
        <dt>Подтверждённые возвраты</dt>
        <dd>{{ money(totals.refunded) }}</dd>
      </div>
    </dl>
    <dl class="group-summary__dates">
      <div v-if="detail">
        <dt>Передана родителям</dt>
        <dd>{{ formatMoment(work?.sentAt) }}</dd>
      </div>
      <div>
        <dt>Приём заказов до</dt>
        <dd>{{ formatMoment(group.closesAt) }}</dd>
      </div>
      <div>
        <dt>Доставка до</dt>
        <dd>{{ formatMoment(work?.deliveryAt) }}</dd>
      </div>
    </dl>
    <p v-if="!work?.sentAt" class="mf-muted">Семь дней приёма начнутся после отметки фактической передачи ссылки.</p>
    <label v-if="group.galleryToken" class="group-summary__url"
      >Ссылка группы
      <input
        :value="url(group)"
        readonly
        :aria-label="'Ссылка группы ' + group.name"
        @focus="($event.target as HTMLInputElement).select()"
      />
    </label>
    <p v-if="notice" role="status" class="group-summary__notice">
      {{ notice }}
    </p>
    <div class="group-summary__actions">
      <v-btn v-if="!detail" :to="groupTo" variant="outlined" :aria-label="'Открыть группу ' + group.name">Открыть группу</v-btn>
      <v-btn v-if="group.galleryToken" variant="text" :aria-label="'Скопировать ссылку ' + group.name" @click="copy(group)"
        >Скопировать ссылку</v-btn
      >
      <v-btn v-if="detail && group.galleryToken" :to="'/g/' + group.galleryToken" variant="outlined">Открыть галерею</v-btn>
      <v-btn v-if="detail" :to="periodTo" :variant="teacher && work?.linkState === 'ready' ? 'flat' : 'text'">{{
        teacher && work?.linkState === 'ready' ? 'Отметить передачу ссылки' : 'Ссылка и сроки'
      }}</v-btn>
    </div>
    <p v-if="detail" class="mf-muted">Копирование не меняет срок. Исправление даты передачи и продление согласует куратор.</p>
  </article>
</template>
<style scoped>
.group-summary {
  border: 1px solid #dde2e5;
  border-radius: 8px;
  padding: 24px;
  min-width: 0;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.group-summary--staff {
  border-top: 3px solid #4f7771;
}
.group-summary__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: start;
  gap: 12px;
}
.group-summary__header > div {
  min-width: 0;
  flex: 1 1 150px;
}
.group-summary__title {
  font-size: 20px;
  line-height: 1.4;
  overflow-wrap: anywhere;
}
.group-summary__link-state {
  display: flex;
  gap: 8px;
  align-items: center;
  color: #5e6872;
}
.group-summary__totals,
.group-summary__dates {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}
.group-summary__totals {
  padding: 16px 0;
  border-block: 1px solid #e7ebed;
}
.group-summary__totals > div:last-child {
  grid-column: 1/-1;
}
.group-summary dt {
  color: #5e6872;
  font-size: 14px;
  margin-bottom: 6px;
}
.group-summary dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.group-summary__totals dd {
  font-size: 22px;
  font-weight: 600;
}
.group-summary__totals > div:last-child dd {
  font-size: 16px;
}
.group-summary__url {
  display: grid;
  gap: 6px;
  color: #5e6872;
  font-size: 14px;
}
.group-summary__url input {
  width: 100%;
  min-width: 0;
  border: 1px solid #8d9b9e;
  border-radius: 4px;
  padding: 10px 12px;
  color: #253638;
  background: #fafcfb;
}
.group-summary__url input:focus-visible {
  outline: 2px solid #346d66;
  outline-offset: 2px;
}
.group-summary__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: auto;
}
.group-summary__actions > * {
  max-width: 100%;
}
.group-summary__notice {
  color: #315b53;
  overflow-wrap: anywhere;
}
@media (max-width: 600px) {
  .group-summary {
    padding: 18px;
  }
  .group-summary__dates {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
