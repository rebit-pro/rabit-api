<script setup lang="ts">
import { watch } from 'vue';
import type { ManagedPhoto } from '../types';
let returnFocus: HTMLElement | null = null;
function restoreFocus() {
  if (returnFocus?.isConnected) returnFocus.focus();
}
const props = defineProps<{
  open: boolean;
  photos: ManagedPhoto[];
  busy: boolean;
  error: string;
  /** The previous answer was lost: confirming repeats that same request. */
  retry: boolean;
}>();
defineEmits<{ close: []; confirm: [] }>();
watch(
  () => props.open,
  (value) => {
    if (value) returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  }
);
function label(photo: ManagedPhoto): string {
  return photo.assignments.map((assignment) => assignment.code).join(' · ') || photo.filename;
}
</script>
<template>
  <v-dialog
    :model-value="open"
    max-width="520"
    :persistent="busy"
    aria-labelledby="photo-delete-heading"
    @after-leave="restoreFocus"
    @update:model-value="!$event && $emit('close')"
  >
    <v-card class="morefoto-app pa-6" data-testid="photo-delete-dialog">
      <h2 id="photo-delete-heading">
        {{ photos.length === 1 ? 'Удалить кадр?' : 'Удалить кадры: ' + photos.length + '?' }}
      </h2>
      <ul class="photo-delete-names my-4">
        <li v-for="photo in photos.slice(0, 10)" :key="photo.id">
          {{ label(photo) }}
        </li>
        <li v-if="photos.length > 10">и ещё {{ photos.length - 10 }}</li>
      </ul>
      <p>Кадры и их назначения детям будут удалены без возможности восстановления. Коды остальных кадров не изменятся.</p>
      <v-alert v-if="error" role="alert" type="error" variant="tonal" class="mt-4">{{ error }}</v-alert>
      <div class="mf-actions mt-6">
        <v-btn color="error" :loading="busy" data-testid="photo-delete-confirm" @click="$emit('confirm')">
          {{ retry ? 'Повторить удаление' : 'Удалить' }}
        </v-btn>
        <v-btn variant="outlined" :disabled="busy" @click="$emit('close')">Отмена</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.photo-delete-names {
  padding-left: 20px;
  overflow-wrap: anywhere;
}
</style>
