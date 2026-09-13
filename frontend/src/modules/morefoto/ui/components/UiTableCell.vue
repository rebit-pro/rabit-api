<script setup lang="ts">
import { computed } from 'vue';
import type { UiTableColumn, UiTableValue } from '../table-types';
import { tableCellText } from '../table-values';
const props = defineProps<{ value?: UiTableValue; column: UiTableColumn }>();
const text = computed(() => tableCellText(props.value, props.column));
const tone = computed(() => props.column.statuses?.[String(props.value)]?.tone ?? 'neutral');
</script>
<template>
  <span v-if="column.type === 'status'" class="ui-table-status" :class="'ui-table-status--' + tone">{{ text }}</span>
  <span v-else :class="{ 'ui-table-value-number': column.type === 'money' || column.type === 'number' }">{{ text }}</span>
</template>
<style scoped>
.ui-table-status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: var(--mf-text-small);
  line-height: 1.5;
  background: #edf0f3;
  color: #414c58;
}
.ui-table-status--success {
  background: #e8f3ed;
  color: #246045;
}
.ui-table-status--info {
  background: #eaf3f9;
  color: #24658a;
}
.ui-table-status--warning {
  background: #fff2d8;
  color: #745218;
}
.ui-table-value-number {
  font-variant-numeric: tabular-nums;
}
</style>
