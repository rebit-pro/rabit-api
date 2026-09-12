<script setup lang="ts">
import { packCount, printCountLabel } from '../display';
import type { TransferBatch } from '../types';
import { formatMoment } from '../../handoff/display';
defineProps<{ batches: TransferBatch[]; editable: boolean }>();
const emit = defineEmits<{ transfer: [batch: TransferBatch] }>();
</script>
<template>
  <section aria-labelledby="delivery-batches-title">
    <h2 id="delivery-batches-title">Можно передать в учреждение</h2>
    <p class="mf-muted mt-2 mb-5">
      Все готовые пакеты одной съёмки объединены автоматически. У ещё не готовых групп остаются собственные сроки доставки.
    </p>
    <p v-if="!batches.length" class="delivery-empty">Готовых непереданных пакетов пока нет. Проверьте состояние групп ниже.</p>
    <div class="delivery-grid">
      <article v-for="b in batches" :key="b.id" class="mf-panel delivery-card delivery-ready" data-testid="transfer-batch">
        <p class="mf-eyebrow">{{ b.shootName }}</p>
        <h3 class="mt-2">{{ b.institutionName }}</h3>
        <p class="delivery-address">{{ b.address || 'Адрес учреждения не указан.' }}</p>
        <p class="delivery-totals">{{ packCount(b.packs) }} · {{ printCountLabel(b.prints) }}</p>
        <ul class="delivery-lines">
          <li v-for="g in b.groups" :key="g.id">
            <strong>{{ g.name }}</strong
            ><span v-if="g.kind === 'staff' && g.name !== 'Сотрудники'" class="delivery-staff"> · Сотрудники</span>
            <p>{{ packCount(g.packs) }} · {{ printCountLabel(g.prints) }}</p>
            <p class="delivery-muted">До {{ formatMoment(g.deadline) }}</p>
          </li>
        </ul>
        <v-btn v-if="editable" color="primary" @click="emit('transfer', b)">Записать передачу</v-btn>
      </article>
    </div>
  </section>
</template>
