<script setup lang="ts">
import { shallowRef, useTemplateRef } from 'vue';
import MfAvatar from '@/components/avatar/MfAvatar.vue';
import type { AvatarRef } from '@/api/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { AVATAR_TYPES, avatarError, avatarFileProblem } from './api';

const props = defineProps<{
  seed: string;
  name?: string | null;
  email?: string | null;
  avatar?: AvatarRef | null;
  save: (file: File) => Promise<AvatarRef>;
  remove: () => Promise<void>;
}>();
const emit = defineEmits<{ changed: [avatar: AvatarRef | null] }>();
const input = useTemplateRef<HTMLInputElement>('input');
const busy = shallowRef(false);
const error = shallowRef('');

async function pick(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';
  if (!file || busy.value) return;
  const problem = avatarFileProblem(file);
  if (problem) {
    error.value = problem;
    return;
  }
  await run(async () => emit('changed', await props.save(file)));
}

async function drop(): Promise<void> {
  await run(async () => {
    await props.remove();
    emit('changed', null);
  });
}

async function run(action: () => Promise<void>): Promise<void> {
  busy.value = true;
  error.value = '';
  try {
    await action();
  } catch (cause) {
    error.value = avatarError(cause);
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="mf-avatar-editor" data-testid="avatar-editor">
    <MfAvatar :seed="seed" :name="name" :email="email" :size="96" :src="avatar?.fullUrl" decorative />
    <div class="mf-avatar-editor__body">
      <div class="mf-avatar-editor__actions">
        <input
          ref="input"
          type="file"
          :accept="AVATAR_TYPES.join(',')"
          class="mf-avatar-editor__input"
          tabindex="-1"
          aria-hidden="true"
          data-testid="avatar-file"
          @change="pick"
        />
        <v-btn variant="outlined" prepend-icon="mdi-camera-outline" :loading="busy" :disabled="isMockApiEnabled" @click="input?.click()"
          >Загрузить фото</v-btn
        >
        <v-btn
          v-if="avatar"
          variant="text"
          color="secondary"
          prepend-icon="mdi-delete-outline"
          :disabled="busy || isMockApiEnabled"
          @click="drop"
          >Удалить фото</v-btn
        >
      </div>
      <p class="mf-muted">JPEG, PNG или WebP до 5 МБ. В кабинете останется квадрат из середины кадра.</p>
      <p v-if="isMockApiEnabled" class="mf-muted">В демонстрационной версии фото не загружаются.</p>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" data-testid="avatar-error">{{ error }}</v-alert>
    </div>
  </div>
</template>

<style scoped>
.mf-avatar-editor {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-5);
}
.mf-avatar-editor__body {
  display: grid;
  flex: 1 1 240px;
  gap: var(--mf-space-2);
}
.mf-avatar-editor__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
.mf-avatar-editor__input {
  display: none;
}
</style>
