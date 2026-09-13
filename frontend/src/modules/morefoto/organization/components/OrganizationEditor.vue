<script setup lang="ts">
import { nextTick, useTemplateRef } from 'vue';
import { useOrganizationEditor } from '../composables/useOrganizationEditor';
import type { EditorTarget, OrganizationSnapshot, SaveResult } from '../types';
import InstitutionFields from './InstitutionFields.vue';
import ShootFields from './ShootFields.vue';
import GroupFields from './GroupFields.vue';
const props = defineProps<{ snapshot: OrganizationSnapshot | null; context?: string }>();
const emit = defineEmits<{ saved: [result: SaveResult] }>();
const { command, fields, busy, error, errors, restored, title, open, patch, reset, close, save } = useOrganizationEditor(
  () => props.snapshot,
  (result) => emit('saved', result)
);
const form = useTemplateRef<HTMLElement>('form');
async function submit() {
  await save();
  await nextTick();
  (form.value?.querySelector<HTMLElement>('[aria-invalid="true"]') ?? form.value?.querySelector<HTMLElement>('[role="alert"]'))?.focus();
}
let returnFocus: HTMLElement | null = null;
function openEditor(target: EditorTarget) {
  returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  open(target);
}
function restoreFocus() {
  if (returnFocus?.isConnected) returnFocus.focus();
}
defineExpose({ open: openEditor });
</script>
<template>
  <v-dialog
    :model-value="!!command"
    max-width="660"
    :persistent="busy"
    aria-labelledby="org-editor-title"
    @after-leave="restoreFocus"
    @update:model-value="!$event && close()"
    @after-enter="form?.querySelector('input')?.focus()"
  >
    <v-card class="morefoto-app org-editor">
      <form v-if="command" ref="form" novalidate @submit.prevent="submit">
        <header class="org-editor-heading">
          <div>
            <h2 id="org-editor-title">{{ title }}</h2>
            <p v-if="context" class="mf-muted mt-2">{{ context }}</p>
          </div>
          <v-btn icon="mdi-close" variant="text" aria-label="Закрыть форму" :disabled="busy" @click="close" />
        </header>
        <div class="mf-form-fields">
          <v-alert v-if="restored" type="info" variant="tonal"
            >Восстановлен сохранённый черновик. <v-btn variant="text" @click="reset">Загрузить актуальные данные</v-btn></v-alert
          >
          <v-alert v-if="error" role="alert" tabindex="-1" type="error" variant="tonal">{{ error }}</v-alert>
          <InstitutionFields
            v-if="command.kind === 'institution'"
            :fields="fields"
            :errors="errors"
            :busy="busy"
            :staff="snapshot?.staff ?? []"
            @patch="patch"
          />
          <ShootFields v-else-if="command.kind === 'shoot'" :fields="fields" :errors="errors" :busy="busy" @patch="patch" />
          <GroupFields
            v-else
            :fields="fields"
            :errors="errors"
            :busy="busy"
            :staff="snapshot?.staff ?? []"
            :editing="!!command.id"
            @patch="patch"
          />
        </div>
        <footer class="mf-actions mt-6">
          <v-btn type="submit" :loading="busy" :disabled="busy" color="primary">Сохранить</v-btn>
          <v-btn variant="outlined" :disabled="busy" @click="close">Закрыть</v-btn>
          <v-btn variant="text" :disabled="busy" @click="reset">Сбросить черновик</v-btn>
        </footer>
      </form>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.org-editor {
  padding: 24px;
  overflow-wrap: anywhere;
}
.org-editor-heading {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}
@media (max-width: 599px) {
  .org-editor {
    padding: 16px;
  }
}
</style>
