<script setup lang="ts">
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import { avatarSeed } from '@/components/avatar/avatar';
import type { StaffSummary } from '../model';
defineProps<{ item: StaffSummary }>();
defineEmits<{ open: [item: StaffSummary] }>();
</script>
<template>
  <button
    type="button"
    class="staff-person"
    :aria-label="'Редактировать сотрудника ' + item.name"
    :data-open-id="String(item.id)"
    @click="$emit('open', item)"
  >
    <MfAvatar
      :seed="avatarSeed(item.id, item.email)"
      :name="item.name"
      :email="item.email"
      :size="32"
      :src="item.avatar?.thumbUrl"
      decorative
    />
    <span class="staff-person__text"
      ><strong>{{ item.name }}</strong
      ><small>{{ item.email }}</small></span
    >
  </button>
</template>
<style scoped>
.staff-person {
  display: flex;
  align-items: center;
  gap: var(--mf-space-3);
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  overflow-wrap: anywhere;
}
.staff-person:hover strong,
.staff-person:focus-visible strong {
  color: var(--mf-color-link);
  text-decoration: underline;
}
.staff-person__text {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.staff-person__text strong {
  font-weight: 600;
}
.staff-person__text small {
  color: var(--mf-color-text-secondary);
  font-size: 12px;
  font-weight: 400;
}
</style>
