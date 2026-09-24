<script setup lang="ts">
import { computed } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import type { UiTableColumn, UiTableValue } from '../table-types';
import { tableCellText } from '../table-values';
const props = defineProps<{ value?: UiTableValue; column: UiTableColumn }>();
const text = computed(() => tableCellText(props.value, props.column));
const tone = computed(() => props.column.statuses?.[String(props.value)]?.tone ?? 'neutral');
</script>
<template>
  <MfStatus v-if="column.type === 'status'" :tone="tone" class="ui-table-status">{{ text }}</MfStatus>
  <span v-else :class="{ 'ui-table-value-number': column.type === 'money' || column.type === 'number' }">{{ text }}</span>
</template>
<style scoped>
.ui-table-value-number {
  font-variant-numeric: tabular-nums;
}
</style>
