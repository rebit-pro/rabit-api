<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
import { validChildCode } from '../rules';
import type { ManagedPhoto } from '../types';
const props = defineProps<{
  photos: ManagedPhoto[];
  selected: string[];
  filter: string;
  childCodes: string[];
  coverId?: string;
  disabled: boolean;
  allowMove: boolean;
  busy: boolean;
  suggestedCode: string;
}>();
const emit = defineEmits<{
  'update:selected': [ids: string[]];
  'update:filter': [value: string];
  assign: [code: string];
  cover: [id: string];
  preview: [code: string];
  move: [code: string];
}>();
const code = shallowRef(props.suggestedCode);
const codeError = computed(() =>
  code.value.trim() && !validChildCode(code.value.trim().toUpperCase())
    ? 'Код ребёнка — от 1 до 3 латинских букв. Код кадра A001 появится автоматически для ребёнка A.'
    : ''
);
watch(
  () => props.suggestedCode,
  (value) => {
    code.value = value;
  }
);
const filters = computed(() => [
  { title: 'Все кадры', value: 'all' },
  { title: 'Без ребёнка', value: 'unassigned' },
  ...props.childCodes.map((value) => ({ title: 'Ребёнок ' + value, value }))
]);
function toggle(id: string) {
  emit('update:selected', props.selected.includes(id) ? props.selected.filter((value) => value !== id) : [...props.selected, id]);
}
function photoCodes(photo: ManagedPhoto): string {
  return photo.assignments.map((assignment) => assignment.code).join(' · ') || 'Без ребёнка';
}
</script>
<template>
  <section class="mf-panel" aria-labelledby="collection-heading">
    <div class="collection-heading">
      <h2 id="collection-heading">Кадры группы</h2>
      <span class="mf-muted">{{ photos.length }} в текущем фильтре</span>
    </div>
    <div class="collection-toolbar mt-5">
      <v-select
        :model-value="filter"
        :items="filters"
        label="Показать кадры"
        data-testid="photo-filter"
        @update:model-value="$emit('update:filter', $event)"
      />
      <div v-if="!['all', 'unassigned'].includes(filter)" class="mf-actions">
        <v-btn variant="outlined" @click="$emit('preview', filter)">Просмотреть набор</v-btn>
        <v-btn v-if="!disabled && allowMove" variant="outlined" :disabled="busy" @click="$emit('move', filter)">Перенести весь набор</v-btn>
      </div>
    </div>
    <div v-if="!disabled" class="collection-assignment mt-4">
      <p>
        Выбрано кадров: <strong data-testid="photo-selection-count">{{ selected.length }}</strong>
      </p>
      <div class="collection-code">
        <v-text-field
          v-model="code"
          label="Код ребёнка"
          hint="Введите A, B или AA. Коды кадров A001, A002… появятся автоматически."
          :persistent-hint="!codeError"
          :hide-details="Boolean(codeError)"
          :error="Boolean(codeError)"
          maxlength="3"
          data-testid="child-code"
          :disabled="busy"
        />
        <p v-if="codeError" class="collection-code__error" role="alert">{{ codeError }}</p>
      </div>
      <v-btn
        :disabled="!selected.length || busy || !validChildCode(code.trim().toUpperCase())"
        :loading="busy"
        @click="$emit('assign', code)"
        >Назначить ребёнку</v-btn
      >
    </div>
    <p v-if="!photos.length" class="mf-muted py-8" data-testid="photos-empty">
      Кадров пока нет. Выберите фотографии для подготовки или измените фильтр.
    </p>
    <div v-else class="photo-grid mt-6">
      <article v-for="photo in photos" :key="photo.id" class="photo-card" :data-photo-id="photo.id" data-testid="photo-card">
        <GalleryImage :src="photo.thumbSrc" :alt="'Кадр ' + (photo.code || photo.filename)" :width="photo.width" :height="photo.height" />
        <div class="photo-card-body">
          <p class="photo-code">{{ photoCodes(photo) }} <span v-if="coverId === photo.id" class="photo-cover">Обложка</span></p>
          <p class="photo-filename">{{ photo.filename }}</p>
          <v-checkbox
            v-if="!disabled"
            :model-value="selected.includes(photo.id)"
            :label="'Выбрать кадр ' + (photo.code || photo.filename)"
            :disabled="busy"
            hide-details
            @update:model-value="toggle(photo.id)"
          />
          <v-btn
            v-if="!disabled && photo.assignments.length"
            variant="text"
            :disabled="busy || coverId === photo.id"
            @click="$emit('cover', photo.id)"
            >Сделать обложкой</v-btn
          >
        </div>
      </article>
    </div>
  </section>
</template>
<style scoped>
.collection-heading,
.collection-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.collection-toolbar > :first-child {
  flex: 1 1 220px;
  max-width: 380px;
}
.collection-assignment {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
  padding: 20px;
  background: var(--mf-color-bg);
  border-radius: 4px;
}
.collection-code {
  flex: 1 1 240px;
  min-width: 0;
}
.collection-code__error {
  color: var(--mf-tone-danger-fg);
  font-size: 12px;
  line-height: 1.4;
  margin-top: 6px;
}
.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 220px), 1fr));
  gap: 20px;
}
.photo-card {
  border: 1px solid var(--mf-color-border);
  border-radius: 4px;
  overflow: hidden;
  min-width: 0;
}
.photo-card :deep(.gallery-image) {
  aspect-ratio: 4/3;
  height: auto;
}
.photo-card-body {
  padding: 12px;
}
.photo-code {
  font-weight: 600;
}
.photo-filename {
  color: var(--mf-color-text-secondary);
  font-size: 13px;
  overflow-wrap: anywhere;
  margin-top: 4px;
}
.photo-cover {
  display: inline-block;
  color: var(--mf-tone-success-fg);
  font-size: 12px;
  margin-left: 8px;
}
@media (max-width: 600px) {
  .collection-assignment {
    padding: 12px;
    gap: 12px;
  }
  .collection-assignment > * {
    width: 100%;
  }
}
</style>
