<script setup lang="ts">
import { nextTick, watch } from 'vue';
const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    busy: boolean;
    error: string;
    restored: boolean;
    saveLabel?: string;
    focusHeading?: boolean;
    footerHint?: string;
    canReset?: boolean;
    fieldsDisabled?: boolean;
  }>(),
  { canReset: true }
);
const emit = defineEmits<{ close: []; save: []; reset: [] }>();
let opener: HTMLElement | null = null;
watch(
  () => props.open,
  (value) => {
    if (value) opener = document.activeElement as HTMLElement;
  },
  { flush: 'sync' }
);
function hasControlFocus(): boolean {
  const active = document.activeElement;
  return (
    active instanceof HTMLElement &&
    active !== opener &&
    !!active.closest('input, textarea, select, button, a[href], [role="combobox"], [role="option"], [contenteditable="true"]')
  );
}
async function focusFirst() {
  // The opening transition may finish after the user has already started editing.
  if (!props.open || hasControlFocus()) return;
  await nextTick();
  if (!props.open || hasControlFocus()) return;
  document
    .querySelector<HTMLElement>(
      props.focusHeading
        ? '#admin-title'
        : '[data-testid="admin-dialog"] input:not(:disabled), [data-testid="admin-dialog"] textarea:not(:disabled)'
    )
    ?.focus();
}
function returnFocus() {
  if (opener?.isConnected) opener.focus();
}
</script>
<template>
  <v-dialog
    :model-value="open"
    :persistent="busy"
    max-width="880"
    aria-labelledby="admin-title"
    @update:model-value="!$event && emit('close')"
    @after-enter="focusFirst"
    @after-leave="returnFocus"
  >
    <v-card class="morefoto-app admin-dialog" data-testid="admin-dialog">
      <form novalidate @submit.prevent="emit('save')">
        <header class="admin-heading">
          <h2 id="admin-title" tabindex="-1">{{ title }}</h2>
          <v-btn icon="mdi-close" variant="text" aria-label="Закрыть редактор" :disabled="busy" @click="emit('close')" />
        </header>
        <div class="admin-body">
          <v-alert v-if="error" type="error" variant="tonal" tabindex="-1" class="management-error mb-5">{{ error }}</v-alert>
          <p v-if="restored" role="status" class="mf-muted mb-4">Восстановлен несохранённый черновик.</p>
          <v-btn
            v-if="canReset !== false && (restored || error)"
            variant="text"
            class="admin-reset mb-5"
            :disabled="busy"
            @click="emit('reset')"
            >Загрузить актуальные данные</v-btn
          >
          <fieldset :disabled="busy || fieldsDisabled" class="admin-fields"><slot /></fieldset>
        </div>
        <footer class="admin-footer">
          <div class="mf-actions">
            <v-btn variant="outlined" :disabled="busy" @click="emit('close')">Отмена</v-btn
            ><v-btn type="submit" :loading="busy" :disabled="busy">{{ saveLabel ?? 'Сохранить' }}</v-btn>
          </div>
          <p class="mf-muted">{{ footerHint ?? 'При закрытии несохранённые изменения остаются в черновике этого браузера.' }}</p>
        </footer>
      </form>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.admin-dialog {
  max-height: 90dvh !important;
  border-radius: var(--mf-radius-field);
}
.admin-dialog form {
  display: flex;
  flex-direction: column;
  min-height: 0;
  max-height: 90dvh;
}
.admin-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 24px;
  border-bottom: 1px solid #dce3e8;
  flex-shrink: 0;
}
.admin-heading h2 {
  font-size: 22px;
  line-height: 1.35;
}
.admin-body {
  padding: 24px;
  overflow-y: auto;
  min-height: 0;
}
.admin-fields {
  border: 0;
  min-width: 0;
}
.admin-footer {
  padding: 16px 24px;
  border-top: 1px solid #dce3e8;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  flex-shrink: 0;
}
.admin-footer p {
  width: 100%;
  font-size: 12px;
}
@media (max-width: 600px) {
  .admin-heading,
  .admin-body,
  .admin-footer {
    padding: 16px;
  }
  .admin-footer > .v-btn {
    white-space: normal;
    height: auto;
    min-height: 40px;
    max-width: 100%;
  }
  .admin-footer .mf-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
.admin-fields :deep(.v-input) {
  margin-bottom: 20px;
}
.admin-fields :deep(.v-messages__message) {
  line-height: 1.5;
}
.admin-reset {
  white-space: normal;
  height: auto;
  min-height: 48px;
  max-width: 100%;
}
</style>
