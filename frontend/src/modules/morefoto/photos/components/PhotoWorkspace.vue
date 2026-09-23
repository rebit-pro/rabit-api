<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { usePhotoWorkspace } from '../composables/usePhotoWorkspace';
import { usePhotoQueue } from '../composables/usePhotoQueue';
import { photoApiError } from '../api';
import type { ManagedPhoto } from '../types';
import OrganizationLoadState from '../../organization/components/OrganizationLoadState.vue';
import PhotoUpload from './PhotoUpload.vue';
import PhotoCollection from './PhotoCollection.vue';
import PhotoPreview from './PhotoPreview.vue';
import ChildMoveDialog from './ChildMoveDialog.vue';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
const route = useRoute();
const workspace = usePhotoWorkspace();
const {
  data,
  loading,
  mediaLoading,
  loadError,
  reload,
  institution,
  shoot,
  groups,
  group,
  selectedGroupId,
  changeGroup,
  editable,
  items,
  total,
  page,
  pages,
  setPage,
  summary,
  childCodes,
  childPhotos,
  cover,
  suggestedCode,
  selected,
  filter,
  setFilter,
  busy,
  error,
  notice,
  assign,
  setCover,
  transfer
} = workspace;
const queue = usePhotoQueue(String(route.params.shootId));
const { jobs, busy: uploading, paused, error: uploadError, queued, accepted, waiting, failed } = queue;
const groupItems = computed(() =>
  groups.value.map((item) => ({
    title: item.name + (item.state === 'preparing' ? ' · Подготовка' : ' · Подборка опубликована'),
    value: item.id
  }))
);
const preview = shallowRef(false);
const previewChild = shallowRef('');
const previewPhotos = shallowRef<ManagedPhoto[]>([]);
const previewLoading = shallowRef(false);
const previewError = shallowRef('');
let previewRequest = 0;
const move = shallowRef<{ child: string; ids: string[] } | null>(null);
const moveLoading = shallowRef(false);
const targets = computed(() =>
  groups.value.filter((item) => item.id !== group.value?.id && item.kind === group.value?.kind && item.state === 'preparing')
);
// Sets span pages, so the preview and the transfer read the child's frames from the server when opened.
async function loadPreview(code: string) {
  const ticket = ++previewRequest;
  previewChild.value = code;
  previewPhotos.value = [];
  previewError.value = '';
  if (!code) return;
  previewLoading.value = true;
  try {
    const photos = await childPhotos(code);
    if (ticket === previewRequest) previewPhotos.value = photos;
  } catch (cause) {
    if (ticket === previewRequest) previewError.value = photoApiError(cause);
  } finally {
    if (ticket === previewRequest) previewLoading.value = false;
  }
}
function showPreview(code = '') {
  preview.value = true;
  void loadPreview(code || childCodes.value[0] || '');
}
async function showMove(code: string) {
  error.value = '';
  moveLoading.value = true;
  try {
    move.value = { child: code, ids: (await childPhotos(code)).map((photo) => photo.id) };
  } catch (cause) {
    error.value = photoApiError(cause);
  } finally {
    moveLoading.value = false;
  }
}
async function confirmMove(toId: string, code: string) {
  if (move.value && (await transfer(move.value.child, toId, code, move.value.ids))) move.value = null;
}
</script>
<template>
  <RouterLink :to="'/cabinet/institutions/' + route.params.institutionId + '/shoots/' + route.params.shootId" class="mf-back"
    >← {{ shoot?.name ?? 'К съёмке' }}</RouterLink
  >
  <OrganizationLoadState
    v-if="!shoot || !institution"
    class="mt-6"
    :loading="loading"
    :error="loadError"
    :missing="!!data"
    @retry="reload"
  />
  <template v-else>
    <header class="mf-page-heading">
      <p class="mf-eyebrow">{{ institution.name }} · {{ shoot.name }}</p>
      <h1>Фотографии съёмки</h1>
      <p class="mf-muted">Подготовьте превью и соберите полный набор для каждого ребёнка.</p>
    </header>
    <div class="photo-context mb-6">
      <v-select
        :model-value="selectedGroupId"
        :items="groupItems"
        label="Группа съёмки"
        data-testid="photo-group"
        :disabled="busy || !!move"
        @update:model-value="changeGroup"
      /><v-btn variant="outlined" :disabled="!childCodes.length" @click="showPreview()">Предпросмотр</v-btn>
    </div>
    <p v-if="!group" class="mf-panel">В съёмке пока нет групп. Добавьте группу на странице съёмки.</p>
    <template v-else>
      <section class="mf-panel photo-readiness mb-6" aria-label="Состояние подборки">
        <div>
          <h2>{{ group.name }}</h2>
          <p class="mt-2" data-testid="photo-readiness">
            Кадров: {{ summary.photos }} · Детей: {{ childCodes.length }} · Без ребёнка: {{ summary.unassigned }}
          </p>
          <p class="mf-muted mt-2">{{ cover ? 'Обложка группы выбрана' : 'Обложка группы ещё не выбрана' }}</p>
        </div>
        <GalleryImage v-if="cover" :src="cover.thumbSrc" alt="Обложка группы" class="group-cover" />
      </section>
      <v-alert v-if="!editable" type="info" variant="tonal" class="mb-6"
        >Подборка уже опубликована. Здесь можно просмотреть наборы; изменения доступны в группах со статусом «Подготовка».</v-alert
      >
      <PhotoUpload
        v-if="editable || jobs.length"
        class="mb-6"
        :jobs="jobs"
        :groups="groups"
        :busy="uploading"
        :paused="paused"
        :disabled="busy || !editable"
        :queued="queued"
        :accepted="accepted"
        :waiting="waiting"
        :failed="failed"
        :error="uploadError"
        :group-name="group.name"
        @files="queue.add($event, group.id)"
        @start="queue.start"
        @pause="queue.pause"
        @retry="queue.retry"
        @clear="queue.clear"
        @remove="queue.remove"
      />
      <v-alert v-if="error && !move" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
      <PhotoCollection
        v-model:selected="selected"
        :filter="filter"
        :photos="items"
        :total="total"
        :page="page"
        :pages="pages"
        :loading="mediaLoading"
        :child-codes="childCodes"
        :cover-id="cover?.id"
        :disabled="!editable"
        :allow-move="true"
        :busy="busy || uploading || moveLoading"
        :suggested-code="suggestedCode"
        @update:filter="setFilter"
        @update:page="setPage"
        @assign="assign"
        @cover="setCover"
        @preview="showPreview"
        @move="showMove"
      />
      <PhotoPreview
        :open="preview"
        :codes="childCodes"
        :child="previewChild"
        :photos="previewPhotos"
        :loading="previewLoading"
        :error="previewError"
        :group-name="group.name"
        @update:child="loadPreview"
        @close="preview = false"
      />
      <ChildMoveDialog
        :open="!!move"
        :groups="targets"
        :child="move?.child ?? ''"
        :count="move?.ids.length ?? 0"
        :from-name="group.name"
        :busy="busy"
        :error="error"
        @close="move = null"
        @move="confirmMove"
      />
    </template>
  </template>
</template>
<style scoped>
.photo-context {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}
.photo-context > .v-input {
  flex: 1 1 260px;
  max-width: 540px;
}
.photo-readiness {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.group-cover {
  width: 120px;
  height: 90px;
}
</style>
