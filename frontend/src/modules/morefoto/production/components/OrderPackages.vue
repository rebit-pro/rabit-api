<script setup lang="ts">
import { computed } from 'vue';
import type { PrintVersion } from '../types';
import { packageIds, printCount } from '../rules';
import { formatMoment } from '../../handoff/display';
const props = defineProps<{ version: PrintVersion; editable: boolean }>();
const emit = defineEmits<{ pack: [orderId: string, packed: boolean] }>();
const packages = computed(() =>
  packageIds(props.version).map((id) => {
    const rows = props.version.plan.rows.filter((r) => r.orderId === id);
    return { id, rows, first: rows[0]!, prints: printCount(rows), checked: props.version.packages[id] };
  })
);
</script>
<template>
  <section class="mf-panel production-packages">
    <h2>Пакеты по заказам</h2>
    <p class="mf-muted mt-3">Каждый заказ — отдельный подписанный пакет. После сверки пакеты объединяются по учреждению.</p>
    <p class="mt-3" role="status">Скомплектовано {{ packages.filter((p) => p.checked).length }} из {{ packages.length }}</p>
    <p v-if="!version.startedAt" class="mt-3">Комплектация доступна после учёта запуска этой версии.</p>
    <article v-for="pack in packages" :key="pack.id" class="production-package" :data-testid="'package-' + pack.id">
      <div>
        <h3>{{ pack.first.orderNumber }}</h3>
        <p>{{ pack.prints }} отпечатков · {{ pack.first.childCode }} · {{ pack.first.audience === 'staff' ? 'Сотрудник' : 'Родитель' }}</p>
        <p class="mf-muted">
          Исходные группы: {{ [...new Set(pack.rows.map((r) => r.sourceGroupName + ' · ' + r.sourceChildCode))].join(', ') }}
        </p>
        <p v-if="pack.checked" class="mt-2">Сверено: {{ pack.checked.actor }} · {{ formatMoment(pack.checked.at) }}</p>
      </div>
      <v-btn
        v-if="editable && version.startedAt"
        variant="outlined"
        :aria-label="(pack.checked ? 'Снять комплектацию ' : 'Скомплектовать ') + pack.first.orderNumber"
        @click="emit('pack', pack.id, !pack.checked)"
        >{{ pack.checked ? 'Снять отметку' : 'Скомплектовать пакет' }}</v-btn
      >
      <v-chip v-else>{{ pack.checked ? 'Скомплектован' : 'Ожидает сверки' }}</v-chip>
    </article>
  </section>
</template>
