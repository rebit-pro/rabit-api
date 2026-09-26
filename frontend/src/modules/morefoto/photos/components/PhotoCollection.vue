<script setup lang="ts">
import { computed, shallowRef, useTemplateRef, watch } from 'vue';
import { useDisplay } from 'vuetify';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
import { validChildCode } from '../rules';
import { failedPreviews, retryFailedPreviews } from '../previews';
import type { ManagedPhoto } from '../types';
const props = defineProps<{
  photos: ManagedPhoto[];
  total: number;
  page: number;
  pages: number;
  loading: boolean;
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
  'update:page': [value: number];
  assign: [code: string];
  cover: [id: string];
  preview: [code: string];
  move: [code: string];
  remove: [ids: string[]];
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
  return photo.assignments.map((assignment) => assignment.code).join(' · ');
}
/** Name of the frame for assistive technology: its codes, or the file of a frame without a child. */
function photoLabel(photo: ManagedPhoto): string {
  return photoCodes(photo) || photo.filename;
}
const allSelected = computed(() => !!props.photos.length && props.photos.every((photo) => props.selected.includes(photo.id)));
function selectAll() {
  emit('update:selected', [...new Set([...props.selected, ...props.photos.map((photo) => photo.id)])]);
}
const { smAndDown } = useDisplay();
const section = useTemplateRef<HTMLElement>('section');
function changePage(value: number) {
  emit('update:page', value);
  section.value?.scrollIntoView({ block: 'start' });
}
</script>
<template>
  <section ref="section" class="mf-panel collection" aria-labelledby="collection-heading" :aria-busy="loading">
    <v-progress-linear v-if="loading" indeterminate color="primary" class="collection-progress" />
    <div class="collection-heading">
      <h2 id="collection-heading">Кадры группы</h2>
      <span class="mf-muted" data-testid="photo-page-status">Показано {{ photos.length }} из {{ total }}</span>
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
      <div class="collection-selection">
        <p>
          Выбрано кадров:
          <strong data-testid="photo-selection-count">{{ selected.length }}</strong>
        </p>
        <v-btn
          variant="text"
          density="comfortable"
          prepend-icon="mdi-checkbox-multiple-marked-outline"
          :disabled="busy || !photos.length || allSelected"
          data-testid="photo-select-all"
          @click="selectAll"
          >Выбрать все на странице</v-btn
        >
        <v-btn
          v-if="selected.length"
          variant="text"
          density="comfortable"
          prepend-icon="mdi-selection-remove"
          :disabled="busy"
          data-testid="photo-select-none"
          @click="$emit('update:selected', [])"
          >Снять выбор</v-btn
        >
      </div>
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
        <p v-if="codeError" class="collection-code__error" role="alert">
          {{ codeError }}
        </p>
      </div>
      <v-btn
        :disabled="!selected.length || busy || !validChildCode(code.trim().toUpperCase())"
        :loading="busy"
        @click="$emit('assign', code)"
        >Назначить ребёнку</v-btn
      >
      <v-btn
        color="error"
        variant="outlined"
        prepend-icon="mdi-delete-outline"
        :disabled="!selected.length || busy"
        data-testid="photo-remove-selected"
        @click="$emit('remove', [...selected])"
        >Удалить выбранные</v-btn
      >
    </div>
    <p v-if="failedPreviews" class="collection-retry mt-4" role="status">
      <span>Не загрузилось превью: {{ failedPreviews }}</span>
      <v-btn variant="outlined" size="small" @click="retryFailedPreviews">Повторить все</v-btn>
    </p>
    <p v-if="!photos.length && !loading" class="mf-muted py-8" data-testid="photos-empty">
      Кадров пока нет. Выберите фотографии для подготовки или измените фильтр.
    </p>
    <div v-else-if="photos.length" class="photo-grid mt-6">
      <article
        v-for="photo in photos"
        :key="photo.id"
        :class="[
          'photo-card',
          { 'photo-card--unassigned': !photo.assignments.length, 'photo-card--selected': selected.includes(photo.id) }
        ]"
        :data-photo-id="photo.id"
        data-testid="photo-card"
      >
        <div class="photo-card-media">
          <GalleryImage :src="photo.thumbSrc" :alt="'Кадр ' + photoLabel(photo)" :width="photo.width" :height="photo.height" />
          <div v-if="!disabled" class="photo-card-check">
            <v-checkbox-btn
              :model-value="selected.includes(photo.id)"
              :aria-label="'Выбрать кадр ' + photoLabel(photo)"
              :disabled="busy"
              density="comfortable"
              @update:model-value="toggle(photo.id)"
            />
          </div>
          <span v-if="coverId === photo.id" class="photo-card-cover"><v-icon icon="mdi-star" size="14" />Обложка</span>
        </div>
        <div class="photo-card-strip">
          <div class="photo-card-name">
            <strong v-if="photo.assignments.length" class="photo-code">{{ photoCodes(photo) }}</strong>
            <strong v-else class="photo-code photo-code--missing">
              <v-icon icon="mdi-account-question-outline" size="18" aria-hidden="true" />
              <span class="mf-sr-only">Кадр без ребёнка</span>
            </strong>
            <span class="photo-filename">{{ photo.filename }}</span>
          </div>
          <div v-if="!disabled" class="photo-card-actions">
            <v-btn
              v-if="photo.assignments.length"
              icon
              variant="text"
              density="comfortable"
              size="small"
              :aria-label="coverId === photo.id ? 'Обложка группы' : 'Сделать обложкой'"
              :disabled="busy || coverId === photo.id"
              @click="$emit('cover', photo.id)"
            >
              <v-icon :icon="coverId === photo.id ? 'mdi-star' : 'mdi-star-outline'" />
              <v-tooltip activator="parent" :text="coverId === photo.id ? 'Обложка группы' : 'Сделать обложкой'" />
            </v-btn>
            <v-btn
              icon
              variant="text"
              color="error"
              density="comfortable"
              size="small"
              :aria-label="'Удалить кадр ' + photoLabel(photo)"
              :disabled="busy"
              @click="$emit('remove', [photo.id])"
            >
              <v-icon icon="mdi-delete-outline" />
              <v-tooltip activator="parent" text="Удалить кадр" />
            </v-btn>
          </div>
        </div>
      </article>
    </div>
    <v-pagination
      v-if="pages > 1"
      class="mt-6"
      :model-value="page"
      :length="pages"
      :total-visible="smAndDown ? 5 : 7"
      density="comfortable"
      data-testid="photo-pagination"
      @update:model-value="changePage"
    />
  </section>
</template>
<style scoped>
.collection {
  position: relative;
  scroll-margin-top: 16px;
}
.collection-progress {
  position: absolute;
  inset: 0 0 auto;
}
.collection-retry {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
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
.collection-selection {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 12px;
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
  display: flex;
  flex-direction: column;
  border: 1px solid var(--mf-color-border);
  border-radius: 4px;
  overflow: hidden;
  min-width: 0;
}
.photo-card--selected {
  border-color: var(--mf-color-primary);
  box-shadow: 0 0 0 1px var(--mf-color-primary);
}
.photo-card-media {
  position: relative;
}
.photo-card :deep(.gallery-image) {
  aspect-ratio: 4/3;
  height: auto;
}
.photo-card-check {
  position: absolute;
  top: 6px;
  left: 6px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--mf-color-surface) 88%, transparent);
}
.photo-card-cover {
  position: absolute;
  top: 8px;
  right: 8px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--mf-color-surface);
  color: var(--mf-tone-success-fg);
  font-size: 12px;
  font-weight: 600;
}
.photo-card-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: auto;
  padding: 8px 8px 8px 12px;
  border-top: 1px solid var(--mf-color-border);
}
.photo-card-name {
  display: grid;
  min-width: 0;
}
.photo-code {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.photo-code--missing {
  display: inline-flex;
  align-items: center;
  color: var(--mf-tone-warning-fg);
}
.photo-filename {
  overflow: hidden;
  color: var(--mf-color-text-secondary);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.photo-card-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
}
.photo-card--unassigned {
  border-color: var(--mf-tone-warning-fg);
}
.photo-card--unassigned .photo-card-strip {
  border-top-color: var(--mf-tone-warning-fg);
  background: var(--mf-tone-warning-bg);
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
