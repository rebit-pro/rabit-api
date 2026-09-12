<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import type { ManagedGroup } from '../../organization/types';
let returnFocus: HTMLElement | null = null;
function restoreFocus() {
  if (returnFocus?.isConnected) returnFocus.focus();
}
const props = defineProps<{
  open: boolean;
  groups: ManagedGroup[];
  child: string;
  count: number;
  fromName: string;
  busy: boolean;
  error: string;
}>();
defineEmits<{ close: []; move: [groupId: string, code: string] }>();
const target = shallowRef('');
const code = shallowRef('');
const items = computed(() => props.groups.map((group) => ({ title: group.name, value: group.id })));
watch(
  () => props.open,
  (value) => {
    if (value) {
      returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      target.value = props.groups[0]?.id ?? '';
      code.value = props.child;
    }
  }
);
</script>
<template>
  <v-dialog
    :model-value="open"
    @after-leave="restoreFocus"
    max-width="560"
    :persistent="busy"
    aria-labelledby="move-heading"
    @update:model-value="!$event && $emit('close')"
  >
    <v-card class="morefoto-app pa-6">
      <h2 id="move-heading">Перенести набор ребёнка {{ child }}</h2>
      <p class="my-4">
        Из группы «{{ fromName }}» будут перенесены все {{ count }} кадра набора. Выбор отдельных кадров не влияет на перенос.
      </p>
      <p v-if="!groups.length" class="mf-muted mb-4">
        В этой съёмке нет другой группы того же типа в подготовке. Создайте её на странице съёмки.
      </p>
      <v-select v-model="target" :items="items" label="Целевая группа" data-testid="move-group" :disabled="busy || !groups.length" />
      <v-text-field
        v-model="code"
        class="mt-4"
        label="Код в целевой группе"
        hint="Если код занят, выберите другой. Существующие наборы не объединяются."
        persistent-hint
        maxlength="3"
        :disabled="busy"
        data-testid="move-code"
      />
      <v-alert v-if="error" role="alert" type="error" variant="tonal" class="mt-4">{{ error }}</v-alert>
      <div class="mf-actions mt-6">
        <v-btn :disabled="!target || !code || busy" :loading="busy" @click="$emit('move', target, code)">Перенести {{ count }} кадра</v-btn
        ><v-btn variant="outlined" :disabled="busy" @click="$emit('close')">Отмена</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
