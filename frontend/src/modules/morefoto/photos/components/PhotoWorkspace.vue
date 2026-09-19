<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { usePhotoWorkspace } from '../composables/usePhotoWorkspace';
import { usePhotoQueue } from '../composables/usePhotoQueue';
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
  loadError,
  reload,
  institution,
  shoot,
  groups,
  group,
  selectedGroupId,
  editable,
  assignmentsEnabled,
  groupPhotos,
  childCodes,
  visible,
  cover,
  suggestedCode,
  selected,
  filter,
  busy,
  error,
  notice,
  assign,
  setCover,
  transfer
} = workspace;
const queue = usePhotoQueue(String(route.params.shootId));
const { jobs, busy: uploading, error: uploadError, queued, accepted, failed } = queue;
const groupItems = computed(() =>
  groups.value.map((item) => ({
    title: item.name + (item.state === 'preparing' ? ' · Подготовка' : ' · Подборка опубликована'),
    value: item.id
  }))
);
const unassigned = computed(() => groupPhotos.value.filter((photo) => !photo.childCode).length);
const preview = shallowRef(false);
const previewChild = shallowRef('');
const move = shallowRef<{ child: string; ids: string[] } | null>(null);
const targets = computed(() =>
  groups.value.filter((item) => item.id !== group.value?.id && item.kind === group.value?.kind && item.state === 'preparing')
);
function showPreview(code = '') {
  previewChild.value = code;
  preview.value = true;
}
function showMove(code: string) {
  error.value = '';
  move.value = { child: code, ids: groupPhotos.value.filter((photo) => photo.childCode === code).map((photo) => photo.id) };
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
        v-model="selectedGroupId"
        :items="groupItems"
        label="Группа съёмки"
        data-testid="photo-group"
        :disabled="busy || !!move"
      /><v-btn variant="outlined" :disabled="!childCodes.length" @click="showPreview()">Предпросмотр</v-btn>
    </div>
    <p v-if="!group" class="mf-panel">В съёмке пока нет групп. Добавьте группу на странице съёмки.</p>
    <template v-else>
      <v-alert v-if="!assignmentsEnabled" type="info" variant="tonal" class="mb-6" data-testid="d1-boundary">
        Оригиналы сохраняются приватно, а защищённые превью готовятся на сервере. Разметка по детям и обложки появятся в D2.
      </v-alert>
      <section class="mf-panel photo-readiness mb-6" aria-label="Состояние подборки">
        <div>
          <h2>{{ group.name }}</h2>
          <p class="mt-2" data-testid="photo-readiness">
            Кадров: {{ groupPhotos.length }} · Детей: {{ childCodes.length }} · Без ребёнка: {{ unassigned }}
          </p>
          <p class="mf-muted mt-2">{{ cover ? 'Обложка группы выбрана' : 'Обложка группы ещё не выбрана' }}</p>
        </div>
        <GalleryImage
          v-if="cover"
          :src="cover.thumbSrc"
          alt="Обложка группы"
          :width="cover.width"
          :height="cover.height"
          class="group-cover"
        />
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
        :disabled="busy || !editable"
        :queued="queued"
        :accepted="accepted"
        :failed="failed"
        :error="uploadError"
        :group-name="group.name"
        @files="queue.add($event, group.id)"
        @start="queue.start"
        @retry="queue.retry"
        @clear="queue.clear"
        @remove="queue.remove"
      />
      <v-alert v-if="error && !move" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
      <PhotoCollection
        v-model:selected="selected"
        v-model:filter="filter"
        :photos="visible"
        :child-codes="childCodes"
        :cover-id="cover?.id"
        :disabled="!editable || !assignmentsEnabled"
        :busy="busy || uploading"
        :suggested-code="suggestedCode"
        @assign="assign"
        @cover="setCover"
        @preview="showPreview"
        @move="showMove"
      />
      <PhotoPreview :open="preview" :photos="groupPhotos" :initial-child="previewChild" :group-name="group.name" @close="preview = false" />
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
