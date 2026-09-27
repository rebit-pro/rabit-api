<script setup lang="ts">
import type { BulkNotice } from '../removal';
defineProps<{ notice: BulkNotice }>();
</script>
<template>
  <v-alert :type="notice.tone" variant="tonal" role="status">
    {{ notice.text }}
    <ul v-if="notice.failures.length" class="ui-bulk-failures">
      <li v-for="failure in notice.failures" :key="failure">{{ failure }}</li>
    </ul>
  </v-alert>
</template>
<style scoped>
.ui-bulk-failures {
  margin: var(--mf-space-2) 0 0 var(--mf-space-5);
}
</style>
