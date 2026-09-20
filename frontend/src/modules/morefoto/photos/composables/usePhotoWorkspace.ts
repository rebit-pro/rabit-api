import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { useOrganization } from '../../organization/composables/useOrganization';
import { structureApi, structureError } from '../../structure/api';
import { photosChangedEvent, photoStateKey, readPhotos } from '../repository';
import { assignPhotos, chooseCover, moveChild } from '../service';
import { nextChildCode } from '../rules';
import { photoApiError, photoApiErrorCode, photosApi, type ServerPhoto } from '../api';
import type { ManagedGroup, ManagedInstitution, OrganizationSnapshot, PhotoShoot } from '../../organization/types';
import type { Group } from '../../structure/model';
import type { ManagedPhoto, PhotoState } from '../types';

export function usePhotoWorkspace() {
  const route = useRoute();
  const router = useRouter();
  const auth = useAuthStore();
  const organization = isMockApiEnabled ? useOrganization() : null;
  const liveInstitution = shallowRef<ManagedInstitution>();
  const liveShoot = shallowRef<PhotoShoot>();
  const liveGroups = shallowRef<ManagedGroup[]>([]);
  const liveReady = shallowRef(false);
  const contextLoading = shallowRef(false);
  const contextError = shallowRef('');
  const photos = shallowRef<PhotoState>(isMockApiEnabled ? readPhotos() : { photos: [], covers: {} });
  const mediaRevision = shallowRef(1);
  const mediaLoading = shallowRef(false);
  let mediaRequest = 0;
  const selectedGroupId = shallowRef(typeof route.query.group === 'string' ? route.query.group : '');
  const selected = shallowRef<string[]>([]);
  const filter = shallowRef('all');
  const busy = shallowRef(false);
  const error = shallowRef('');
  const notice = shallowRef('');
  let alive = true;
  const routeInstitutionId = String(route.params.institutionId);
  const routeShootId = String(route.params.shootId);
  const institution = computed(() =>
    isMockApiEnabled ? organization!.data.value?.institutions.find((item) => item.id === routeInstitutionId) : liveInstitution.value
  );
  const shoot = computed(() =>
    isMockApiEnabled
      ? organization!.data.value?.shoots.find((item) => item.id === routeShootId && item.institutionId === institution.value?.id)
      : liveShoot.value
  );
  const groups = computed(() =>
    isMockApiEnabled ? (organization!.data.value?.groups.filter((item) => item.shootId === shoot.value?.id) ?? []) : liveGroups.value
  );
  const data = computed<OrganizationSnapshot | null>(() => {
    if (isMockApiEnabled) return organization!.data.value;
    if (!liveReady.value || !liveInstitution.value || !liveShoot.value) return null;
    return {
      users: [],
      userOperations: [],
      institutions: [liveInstitution.value],
      shoots: [liveShoot.value],
      groups: liveGroups.value,
      operations: [],
      staff: []
    };
  });
  const loading = computed(() => (isMockApiEnabled ? organization!.loading.value : contextLoading.value) || mediaLoading.value);
  const loadError = computed(() => (isMockApiEnabled ? organization!.error.value : contextError.value));
  async function refreshPhotos(): Promise<boolean> {
    if (isMockApiEnabled) {
      photos.value = readPhotos();
      selected.value = selected.value.filter((id) => photos.value.photos.some((item) => item.id === id));
      return true;
    }
    const ticket = ++mediaRequest;
    mediaLoading.value = true;
    error.value = '';
    try {
      const serverPhotos: ServerPhoto[] = [];
      let page = 1;
      let total = 0;
      let covers: Record<string, string> = {};
      let revision = 1;
      do {
        const result = await photosApi.list(routeShootId, page);
        serverPhotos.push(...result.items);
        total = result.meta.total;
        if (page === 1) {
          covers = result.covers;
          revision = result.revision;
        }
        page++;
      } while (serverPhotos.length < total && page <= 1000);
      if (!alive || ticket !== mediaRequest) return false;
      const ready = serverPhotos.filter(
        (item): item is ServerPhoto & { thumbSrc: string; previewSrc: string } =>
          item.status === 'ready' && !!item.thumbSrc && !!item.previewSrc
      );
      mediaRevision.value = revision;
      photos.value = {
        covers,
        photos: ready.map<ManagedPhoto>((item) => ({
          id: item.id,
          code: item.code ?? item.filename,
          thumbSrc: item.thumbSrc,
          previewSrc: item.previewSrc,
          width: item.width,
          height: item.height,
          shootId: item.shootId,
          groupId: item.groupId,
          originalGroupId: item.originalGroupId,
          childCode: item.childCode,
          sequence: item.sequence,
          assignments: item.assignments,
          filename: item.filename,
          bytes: item.bytes,
          fingerprint: item.fingerprint,
          source: 'server',
          revision: item.revision
        }))
      };
      selected.value = selected.value.filter((id) => photos.value.photos.some((item) => item.id === id));
      return true;
    } catch (cause) {
      if (alive && ticket === mediaRequest) error.value = photoApiError(cause);
      return false;
    } finally {
      if (alive && ticket === mediaRequest) mediaLoading.value = false;
    }
  }
  async function reloadLive() {
    contextLoading.value = true;
    contextError.value = '';
    try {
      const [parent, page] = await Promise.all([
        structureApi.institution(routeInstitutionId, { shootsPage: 1, groupsPage: 1 }, 1),
        structureApi.list({ kind: 'group', institutionId: routeInstitutionId, shootId: routeShootId }, 1, 100)
      ]);
      if (!page.shoot || page.shoot.id !== routeShootId || page.shoot.institutionId !== routeInstitutionId)
        throw new Error('Съёмка не относится к этому учреждению.');
      liveInstitution.value = parent;
      liveShoot.value = page.shoot;
      liveGroups.value = page.items
        .filter((item): item is Group => 'groupKind' in item)
        .map((item) => ({
          id: item.id,
          institutionId: routeInstitutionId,
          shootId: routeShootId,
          shootName: page.shoot!.name,
          name: item.name,
          kind: item.groupKind,
          teacherId: item.teacherId,
          galleryToken: '',
          revision: item.revision,
          state: item.status,
          closesAt: item.closesAt,
          sentAt: item.sentAt ?? undefined
        }));
      liveReady.value = true;
      await refreshPhotos();
    } catch (cause) {
      if (alive) {
        liveReady.value = false;
        contextError.value = structureError(cause);
      }
    } finally {
      if (alive) contextLoading.value = false;
    }
  }
  const reload = isMockApiEnabled ? organization!.reload : reloadLive;
  if (!isMockApiEnabled) onMounted(() => void reloadLive());
  const group = computed(() => groups.value.find((item) => item.id === selectedGroupId.value));
  const editable = computed(() => group.value?.state === 'preparing');
  const groupPhotos = computed(() => photos.value.photos.filter((item) => item.groupId === group.value?.id));
  const childCodes = computed(() =>
    [...new Set(groupPhotos.value.flatMap((item) => item.assignments.map((assignment) => assignment.childCode)))].sort()
  );
  const visible = computed(() =>
    groupPhotos.value.filter(
      (item) =>
        filter.value === 'all' ||
        (filter.value === 'unassigned'
          ? item.assignments.length === 0
          : item.assignments.some((assignment) => assignment.childCode === filter.value))
    )
  );
  const suggestedCode = computed(() => (group.value ? nextChildCode(photos.value.photos, group.value.id) : 'A'));
  const cover = computed(() => groupPhotos.value.find((item) => item.id === photos.value.covers[group.value?.id ?? '']));
  const assignmentsEnabled = true;
  const transferEnabled = isMockApiEnabled;
  watch(
    groups,
    (value) => {
      if (!value.length) return;
      if (!value.some((item) => item.id === selectedGroupId.value))
        selectedGroupId.value = (value.find((item) => item.state === 'preparing') ?? value[0])?.id ?? '';
    },
    { immediate: true }
  );
  watch(selectedGroupId, (id) => {
    if (id && route.query.group !== id) void router.replace({ query: { ...route.query, group: id } });
    selected.value = [];
    filter.value = 'all';
    error.value = '';
    notice.value = '';
  });
  const storage = (event: StorageEvent) => {
    if (isMockApiEnabled && (event.key === null || event.key === 'morefoto:demo:' + photoStateKey)) void refreshPhotos();
  };
  const changed = () => void refreshPhotos();
  window.addEventListener(photosChangedEvent, changed);
  window.addEventListener('storage', storage);
  onScopeDispose(() => {
    alive = false;
    mediaRequest++;
    window.removeEventListener(photosChangedEvent, changed);
    window.removeEventListener('storage', storage);
  });
  async function act(operation: (token: string) => Promise<void>, message: string): Promise<boolean> {
    if (busy.value) return false;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
      await operation(auth.getAccessToken() ?? '');
      if (alive) {
        await refreshPhotos();
        selected.value = [];
        notice.value = message;
      }
      return true;
    } catch (cause) {
      if (alive) {
        if (!isMockApiEnabled && photoApiErrorCode(cause) === 'REVISION_CONFLICT') {
          const refreshed = await refreshPhotos();
          if (alive && refreshed) error.value = 'Разметка уже изменилась. Список обновлён — повторите действие.';
        } else {
          error.value = isMockApiEnabled && cause instanceof Error ? cause.message : photoApiError(cause);
        }
      }
      return false;
    } finally {
      if (alive) busy.value = false;
    }
  }
  function assign(value: string) {
    const ids = [...selected.value];
    const groupId = group.value?.id ?? '';
    const code = value.trim().toUpperCase();
    return act(async (token) => {
      if (isMockApiEnabled) {
        await assignPhotos(token, routeShootId, groupId, ids, code);
      } else {
        const result = await photosApi.assign(groupId, routeShootId, mediaRevision.value, ids, code);
        mediaRevision.value = result.revision;
      }
    }, 'Кадры назначены ребёнку.');
  }
  function setCover(id: string) {
    const groupId = group.value?.id ?? '';
    return act(async (token) => {
      if (isMockApiEnabled) {
        await chooseCover(token, routeShootId, groupId, id);
      } else {
        const result = await photosApi.cover(groupId, mediaRevision.value, id);
        mediaRevision.value = result.revision;
      }
    }, 'Обложка группы сохранена.');
  }
  function transfer(child: string, toId: string, code: string, ids: string[]) {
    if (!transferEnabled) {
      error.value = 'Перенос полного набора будет подключён в волне D3.';
      return Promise.resolve(false);
    }
    const groupId = group.value?.id ?? '';
    return act((token) => moveChild(token, routeShootId, groupId, child, toId, code, ids), 'Полный набор ребёнка перенесён.');
  }
  return {
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
    groupPhotos,
    assignmentsEnabled,
    transferEnabled,
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
  };
}
