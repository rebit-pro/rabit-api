<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { usePhotoWorkspace } from '../composables/usePhotoWorkspace';
import { usePhotoQueue } from '../composables/usePhotoQueue';
import type { ChosenArchive } from '../composables/useArchivePlan';
import type { ArchivePlan as ArchivePlanData } from '../archive';
import { photoApiError } from '../api';
import type { ManagedPhoto } from '../types';
import OrganizationLoadState from '../../organization/components/OrganizationLoadState.vue';
import PhotoUpload from './PhotoUpload.vue';
import PhotoCollection from './PhotoCollection.vue';
import PhotoPreview from './PhotoPreview.vue';
import ChildMoveDialog from './ChildMoveDialog.vue';
import PhotoDeleteDialog from './PhotoDeleteDialog.vue';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import ShootTabs from '../../structure/components/ShootTabs.vue';
import PhotoStats from './PhotoStats.vue';
import GalleryPath from '../../handoff/components/GalleryPath.vue';
import { useGroupGalleryPath } from '../../handoff/useGroupGalleryPath';
import type { GalleryStepKey } from '../../handoff/galleryPath';
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
  pageReady,
  items,
  total,
  page,
  pages,
  setPage,
  summary,
  stats,
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
  removePhotos,
  removalUnknown,
  cancelRemoval,
  transfer
} = workspace;
const queue = usePhotoQueue(String(route.params.shootId));
const { jobs, busy: uploading, paused, error: uploadError, queued, accepted, waiting, failed, previous } = queue;
function startArchive(plan: ArchivePlanData, chosen: ChosenArchive[]) {
  if (!group.value) return;
  queue.addArchive(plan, chosen, group.value.id);
  void queue.start();
}
const groupItems = computed(() =>
  groups.value.map((item) => ({
    title: item.name + (item.state === 'preparing' ? ' · Подготовка' : ' · Подборка опубликована'),
    value: item.id
  }))
);
const institutionId = String(route.params.institutionId);
const shootId = String(route.params.shootId);
// Every refresh of the page data replaces the summary, so the path follows uploads, labelling and deletions.
const pathSteps = useGroupGalleryPath(
  () => group.value?.id ?? '',
  () => summary.value
);
function pathFix(key: GalleryStepKey): { to: string; label: string } | null {
  const id = group.value?.id ?? '';
  if (key === 'conditions')
    return {
      to:
        '/cabinet/institutions/' +
        encodeURIComponent(institutionId) +
        '/shoots/' +
        encodeURIComponent(shootId) +
        '/conditions?group=' +
        encodeURIComponent(id),
      label: 'К условиям'
    };
  if (key === 'staff') return { to: '/cabinet/staff-requests?shoot=' + encodeURIComponent(shootId), label: 'К спискам' };
  if (key === 'prepare' || key === 'transmit') return { to: '/cabinet/links?group=' + encodeURIComponent(id), label: 'К ссылкам и срокам' };
  return null;
}
const crumbs = computed(() => [
  { title: 'Учреждения', to: '/cabinet/institutions' },
  {
    title: institution.value?.name ?? 'Учреждение',
    to: '/cabinet/institutions/' + encodeURIComponent(institutionId)
  },
  {
    title: shoot.value?.name ?? 'Съёмка',
    to: '/cabinet/institutions/' + encodeURIComponent(institutionId) + '/shoots/' + encodeURIComponent(shootId)
  },
  { title: 'Фотографии' }
]);
const preview = shallowRef(false);
const previewChild = shallowRef('');
const previewPhotos = shallowRef<ManagedPhoto[]>([]);
const previewLoading = shallowRef(false);
const previewError = shallowRef('');
let previewRequest = 0;
const move = shallowRef<{
  groupId: string;
  child: string;
  ids: string[];
} | null>(null);
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
// The group selector is locked while the set loads; a group changed anyway (a structure reload) drops the loaded set.
async function showMove(code: string) {
  const groupId = group.value?.id ?? '';
  error.value = '';
  moveLoading.value = true;
  try {
    const ids = (await childPhotos(code)).map((photo) => photo.id);
    if (groupId === group.value?.id) move.value = { groupId, child: code, ids };
  } catch (cause) {
    if (groupId === group.value?.id) error.value = photoApiError(cause);
  } finally {
    moveLoading.value = false;
  }
}
// Selection never spans pages, so the frames to delete are always on the shown page.
const removal = shallowRef<ManagedPhoto[] | null>(null);
function askRemove(ids: string[]) {
  error.value = '';
  removal.value = items.value.filter((photo) => ids.includes(photo.id));
}
function closeRemove() {
  removal.value = null;
  cancelRemoval();
}
async function confirmRemove() {
  if (removal.value && (await removePhotos(removal.value.map((photo) => photo.id)))) removal.value = null;
}
async function confirmMove(toId: string, code: string) {
  if (move.value && (await transfer(move.value.groupId, move.value.child, toId, code, move.value.ids))) move.value = null;
}
</script>
<template>
  <MfBreadcrumbs :items="crumbs" />
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
    <ShootTabs :institution-id="institutionId" :shoot-id="shootId" current="photos" />
    <div class="photo-context mb-6">
      <v-select
        :model-value="selectedGroupId"
        :items="groupItems"
        label="Группа съёмки"
        data-testid="photo-group"
        :disabled="busy || moveLoading || !!move || !!removal"
        @update:model-value="changeGroup"
      />
      <div class="photo-preview-action">
        <v-btn
          variant="outlined"
          :disabled="!childCodes.length"
          :aria-describedby="childCodes.length ? undefined : 'photo-preview-hint'"
          @click="showPreview()"
          >Предпросмотр</v-btn
        >
        <p v-if="group && !childCodes.length" id="photo-preview-hint" class="mf-muted">
          Назначьте кадры ребёнку, чтобы включить предпросмотр.
        </p>
      </div>
    </div>
    <p v-if="!group" class="mf-panel">В съёмке пока нет групп. Добавьте группу на странице съёмки.</p>
    <template v-else>
      <section class="mf-panel photo-readiness mb-6" aria-label="Состояние подборки">
        <div class="photo-readiness__head">
          <div>
            <h2>{{ group.name }}</h2>
            <template v-if="pageReady">
              <p class="mt-2" data-testid="photo-readiness">
                Кадров: {{ summary.photos }} · Детей: {{ childCodes.length }} · Без ребёнка: {{ summary.unassigned }}
              </p>
              <p class="mf-muted mt-2">
                {{ cover ? 'Обложка группы выбрана' : 'Обложка группы ещё не выбрана' }}
              </p>
            </template>
            <p v-else class="mf-muted mt-2" data-testid="photo-readiness">
              {{ mediaLoading ? 'Загружаем кадры группы…' : 'Кадры группы не загружены.' }}
            </p>
          </div>
          <GalleryImage v-if="cover" :src="cover.thumbSrc" alt="Обложка группы" class="group-cover" />
        </div>
        <PhotoStats v-if="stats" :stats="stats" class="mt-5" />
      </section>
      <section v-if="pathSteps" class="mf-panel photo-path mb-6" aria-labelledby="photo-path-heading" data-testid="photo-gallery-path">
        <h2 id="photo-path-heading">Путь к галерее</h2>
        <p class="mf-muted mt-2 mb-4">Родители увидят кадры группы только после отметки передачи ссылки.</p>
        <GalleryPath :steps="pathSteps" :fix="pathFix" />
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
        :child-codes="childCodes"
        :previous="previous"
        @files="queue.add($event, group.id)"
        @archive="startArchive"
        @start="queue.start"
        @pause="queue.pause"
        @retry="queue.retry"
        @clear="queue.clear"
        @remove="queue.remove"
      />
      <v-alert v-if="error && !move && !removal" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
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
        @remove="askRemove"
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
      <PhotoDeleteDialog
        :open="!!removal"
        :photos="removal ?? []"
        :busy="busy"
        :error="error"
        :retry="removalUnknown"
        @close="closeRemove"
        @confirm="confirmRemove"
      />
    </template>
  </template>
</template>
<style scoped>
.photo-context {
  display: flex;
  align-items: flex-start;
  gap: var(--mf-space-5);
  flex-wrap: wrap;
}
.photo-context > .v-input {
  flex: 1 1 260px;
  max-width: 540px;
}
.photo-preview-action {
  display: grid;
  gap: var(--mf-space-1);
  max-width: 320px;
}
.photo-preview-action p {
  font-size: var(--mf-text-sm);
}
.photo-readiness__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-5);
  flex-wrap: wrap;
}
.group-cover {
  width: 120px;
  height: 90px;
}
</style>
