import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { isMockApiEnabled } from '@/mocks/config';
import { useOrganization } from '../../organization/composables/useOrganization';
import { structureApi, structureError } from '../../structure/api';
import { photosChangedEvent, photoStateKey, readPhotos } from '../repository';
import { assignPhotos, chooseCover, moveChild } from '../service';
import { freeChildCode, validChildCode } from '../rules';
import { localPhotoPage, photoFilter, photoPage, photoPages, photoPageSize, type PhotoGroupSummary } from '../paging';
import { managedPreviewSource } from '../previews';
import { childTransferError, photoApiError, photoApiErrorCode, photosApi, type PhotoListQuery, type ServerPhoto } from '../api';
import type { ManagedGroup, ManagedInstitution, OrganizationSnapshot, PhotoShoot } from '../../organization/types';
import type { Group } from '../../structure/model';
import type { ManagedPhoto } from '../types';

type ReadyPhoto = ServerPhoto & { thumbSrc: string; previewSrc: string };
interface PageData {
  items: ManagedPhoto[];
  total: number;
  summary: PhotoGroupSummary;
  covers: Record<string, string>;
}
const emptySummary: PhotoGroupSummary = { photos: 0, unassigned: 0, children: [] };
function isReady(item: ServerPhoto): item is ReadyPhoto {
  return item.status === 'ready' && !!item.thumbSrc && !!item.previewSrc;
}
function managedPhoto(item: ReadyPhoto): ManagedPhoto {
  return {
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
  };
}
function filterQuery(filter: string): PhotoListQuery {
  if (filter === 'all') return {};
  return filter === 'unassigned' ? { assigned: false } : { childCode: filter };
}

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
  // One page of the selected group and a summary of the whole group: the screen never holds the full shoot.
  const items = shallowRef<ManagedPhoto[]>([]);
  const total = shallowRef(0);
  const summary = shallowRef<PhotoGroupSummary>(emptySummary);
  const cover = shallowRef<{ id: string; thumbSrc: string }>();
  const mediaRevision = shallowRef(1);
  const mediaLoading = shallowRef(false);
  let mediaRequest = 0;
  const selectedGroupId = shallowRef(typeof route.query.group === 'string' ? route.query.group : '');
  const filter = shallowRef(photoFilter(route.query.filter));
  const page = shallowRef(photoPage(route.query.page));
  const selected = shallowRef<string[]>([]);
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
  const group = computed(() => groups.value.find((item) => item.id === selectedGroupId.value));
  const editable = computed(() => group.value?.state === 'preparing');
  const childCodes = computed(() => summary.value.children);
  const suggestedCode = computed(() => freeChildCode(new Set(summary.value.children)));
  const pages = computed(() => photoPages(total.value));
  // Everything that defines the shown page: a change reloads it and is written to the URL.
  const pageKey = computed(() => (group.value ? [group.value.id, filter.value, page.value].join('|') : ''));
  function coverSource(id: string, shown: ManagedPhoto[]): string {
    const onPage = shown.find((item) => item.id === id)?.thumbSrc;
    if (onPage) return onPage;
    return isMockApiEnabled ? (readPhotos().photos.find((item) => item.id === id)?.thumbSrc ?? '') : managedPreviewSource(id, 'thumb');
  }
  function applyPage(next: PageData) {
    const coverId = next.covers[group.value?.id ?? ''];
    items.value = next.items;
    total.value = next.total;
    summary.value = next.summary;
    cover.value = coverId ? { id: coverId, thumbSrc: coverSource(coverId, next.items) } : undefined;
    selected.value = selected.value.filter((id) => next.items.some((item) => item.id === id));
    // A page emptied by labelling or a child moved to another group must not leave the screen on an empty view.
    if (page.value > photoPages(next.total)) setPage(photoPages(next.total));
    else if (filter.value !== 'all' && filter.value !== 'unassigned' && !next.summary.children.includes(filter.value)) setFilter('all');
  }
  async function refreshPhotos(): Promise<boolean> {
    const groupId = group.value?.id;
    if (!groupId) {
      applyPage({ items: [], total: 0, summary: emptySummary, covers: {} });
      return true;
    }
    if (isMockApiEnabled) {
      const state = readPhotos();
      applyPage({ ...localPhotoPage(state.photos, groupId, filter.value, page.value), covers: state.covers });
      return true;
    }
    const ticket = ++mediaRequest;
    mediaLoading.value = true;
    error.value = '';
    try {
      const result = await photosApi.list(routeShootId, {
        groupId,
        status: 'ready',
        page: page.value,
        pageSize: photoPageSize,
        ...filterQuery(filter.value)
      });
      if (!alive || ticket !== mediaRequest) return false;
      mediaRevision.value = result.revision;
      applyPage({
        items: result.items.filter(isReady).map(managedPhoto),
        total: result.meta.total,
        summary: result.summary ?? emptySummary,
        covers: result.covers
      });
      return true;
    } catch (cause) {
      if (alive && ticket === mediaRequest) error.value = photoApiError(cause);
      return false;
    } finally {
      if (alive && ticket === mediaRequest) mediaLoading.value = false;
    }
  }
  /** All frames of one child in the selected group, for the set preview and the full-set transfer. */
  async function childPhotos(code: string): Promise<ManagedPhoto[]> {
    const groupId = group.value?.id ?? '';
    if (isMockApiEnabled)
      return readPhotos().photos.filter(
        (item) => item.groupId === groupId && item.assignments.some((assignment) => assignment.childCode === code)
      );
    const photos: ManagedPhoto[] = [];
    for (let next = 1; ; next++) {
      const result = await photosApi.list(routeShootId, { groupId, childCode: code, status: 'ready', page: next, pageSize: 100 });
      photos.push(...result.items.filter(isReady).map(managedPhoto));
      if (!result.items.length || photos.length >= result.meta.total) return photos;
    }
  }
  function syncRoute() {
    const query: LocationQueryRaw = { ...route.query };
    delete query.group;
    delete query.filter;
    delete query.page;
    if (selectedGroupId.value) query.group = selectedGroupId.value;
    if (filter.value !== 'all') query.filter = filter.value;
    if (page.value > 1) query.page = String(page.value);
    if (route.query.group !== query.group || route.query.filter !== query.filter || route.query.page !== query.page)
      void router.replace({ query });
  }
  function changeGroup(id: string) {
    if (id === selectedGroupId.value) return;
    selectedGroupId.value = id;
    filter.value = 'all';
    page.value = 1;
    selected.value = [];
    error.value = '';
    notice.value = '';
  }
  function setFilter(value: string) {
    if (value === filter.value) return;
    filter.value = value;
    page.value = 1;
    selected.value = [];
  }
  function setPage(value: number) {
    if (value === page.value) return;
    page.value = value;
    selected.value = [];
  }
  function pickGroup(list: ManagedGroup[]) {
    if (list.length && !list.some((item) => item.id === selectedGroupId.value))
      changeGroup((list.find((item) => item.state === 'preparing') ?? list[0])!.id);
  }
  async function reloadLive() {
    contextLoading.value = true;
    contextError.value = '';
    try {
      const [parent, structure] = await Promise.all([
        structureApi.institution(routeInstitutionId, { shootsPage: 1, groupsPage: 1 }, 1),
        structureApi.list({ kind: 'group', institutionId: routeInstitutionId, shootId: routeShootId }, 1, 100)
      ]);
      if (!structure.shoot || structure.shoot.id !== routeShootId || structure.shoot.institutionId !== routeInstitutionId)
        throw new Error('Съёмка не относится к этому учреждению.');
      const before = pageKey.value;
      liveInstitution.value = parent;
      liveShoot.value = structure.shoot;
      liveGroups.value = structure.items
        .filter((item): item is Group => 'groupKind' in item)
        .map((item) => ({
          id: item.id,
          institutionId: routeInstitutionId,
          shootId: routeShootId,
          shootName: structure.shoot!.name,
          name: item.name,
          kind: item.groupKind,
          teacherId: item.teacherId,
          galleryToken: '',
          revision: item.revision,
          state: item.status,
          closesAt: item.closesAt,
          sentAt: item.sentAt ?? undefined
        }));
      pickGroup(liveGroups.value);
      liveReady.value = true;
      // A new page key is loaded by its watcher; a repeated load of the same page is requested here.
      if (pageKey.value === before) await refreshPhotos();
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
  watch(groups, pickGroup, { immediate: true });
  watch(pageKey, () => {
    syncRoute();
    void refreshPhotos();
  });
  if (pageKey.value) {
    syncRoute();
    void refreshPhotos();
  }
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
  async function act(
    operation: (token: string) => Promise<void>,
    message: string,
    validationMessage = '',
    describe: (cause: unknown) => string = photoApiError
  ): Promise<boolean> {
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
        const code = photoApiErrorCode(cause);
        if (!isMockApiEnabled && (code === 'REVISION_CONFLICT' || code === 'SET_CHANGED')) {
          const refreshed = await refreshPhotos();
          if (alive && refreshed)
            error.value = code === 'SET_CHANGED' ? describe(cause) : 'Разметка уже изменилась. Список обновлён — повторите действие.';
        } else if (validationMessage && code === 'VALIDATION_FAILED') {
          error.value = validationMessage;
        } else {
          error.value = isMockApiEnabled && cause instanceof Error ? cause.message : describe(cause);
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
    if (!validChildCode(code)) {
      error.value = 'Код ребёнка — от 1 до 3 латинских букв. Коды кадров вроде A001 создаются автоматически.';
      return Promise.resolve(false);
    }
    return act(
      async (token) => {
        if (isMockApiEnabled) {
          await assignPhotos(token, routeShootId, groupId, ids, code);
        } else {
          const result = await photosApi.assign(groupId, routeShootId, mediaRevision.value, ids, code);
          mediaRevision.value = result.revision;
        }
      },
      'Кадры назначены ребёнку.',
      'Не удалось назначить кадры. Проверьте код ребёнка и выбранные фотографии.'
    );
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
  function transfer(child: string, toId: string, value: string, ids: string[]) {
    const groupId = group.value?.id ?? '';
    const code = value.trim().toUpperCase();
    if (!validChildCode(code)) {
      error.value = 'Код в целевой группе — от 1 до 3 латинских букв.';
      return Promise.resolve(false);
    }
    return act(
      async (token) => {
        if (isMockApiEnabled) {
          await moveChild(token, routeShootId, groupId, child, toId, code, ids);
        } else {
          const result = await photosApi.transferChild(routeShootId, {
            fromGroupId: groupId,
            toGroupId: toId,
            childCode: child,
            targetCode: code,
            expectedPhotoIds: ids,
            revision: mediaRevision.value
          });
          mediaRevision.value = result.revision;
        }
      },
      'Полный набор ребёнка перенесён.',
      'Не удалось перенести набор. Проверьте код в целевой группе.',
      childTransferError
    );
  }
  return {
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
  };
}
