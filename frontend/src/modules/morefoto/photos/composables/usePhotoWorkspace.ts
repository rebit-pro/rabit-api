import { computed, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useOrganization } from '../../organization/composables/useOrganization';
import { photosChangedEvent, photoStateKey, readPhotos } from '../repository';
import { assignPhotos, chooseCover, moveChild } from '../service';
import { nextChildCode } from '../rules';
import type { PhotoState } from '../types';

export function usePhotoWorkspace() {
  const route = useRoute();
  const router = useRouter();
  const auth = useAuthStore();
  const organization = useOrganization();
  const photos = shallowRef<PhotoState>(readPhotos());
  const selectedGroupId = shallowRef(typeof route.query.group === 'string' ? route.query.group : '');
  const selected = shallowRef<string[]>([]);
  const filter = shallowRef('all');
  const busy = shallowRef(false);
  const error = shallowRef('');
  const notice = shallowRef('');
  let alive = true;
  const institution = computed(() => organization.data.value?.institutions.find((item) => item.id === route.params.institutionId));
  const shoot = computed(() =>
    organization.data.value?.shoots.find((item) => item.id === route.params.shootId && item.institutionId === institution.value?.id)
  );
  const groups = computed(() => organization.data.value?.groups.filter((item) => item.shootId === shoot.value?.id) ?? []);
  const group = computed(() => groups.value.find((item) => item.id === selectedGroupId.value));
  const editable = computed(() => group.value?.state === 'preparing');
  const groupPhotos = computed(() => photos.value.photos.filter((item) => item.groupId === group.value?.id));
  const childCodes = computed(() =>
    [...new Set(groupPhotos.value.map((item) => item.childCode).filter((code): code is string => !!code))].sort()
  );
  const visible = computed(() =>
    groupPhotos.value.filter(
      (item) => filter.value === 'all' || (filter.value === 'unassigned' ? !item.childCode : item.childCode === filter.value)
    )
  );
  const suggestedCode = computed(() => (group.value ? nextChildCode(photos.value.photos, group.value.id) : 'A'));
  const cover = computed(() => groupPhotos.value.find((item) => item.id === photos.value.covers[group.value?.id ?? '']));
  watch(groups, (value) => {
    if (!value.some((item) => item.id === selectedGroupId.value))
      selectedGroupId.value = (value.find((item) => item.state === 'preparing') ?? value[0])?.id ?? '';
  });
  watch(selectedGroupId, (id) => {
    if (id && route.query.group !== id) void router.replace({ query: { ...route.query, group: id } });
    selected.value = [];
    filter.value = 'all';
    error.value = '';
    notice.value = '';
  });
  const refresh = () => {
    photos.value = readPhotos();
    selected.value = selected.value.filter((id) => groupPhotos.value.some((item) => item.id === id));
  };
  const storage = (event: StorageEvent) => {
    if (event.key === null || event.key === 'morefoto:demo:' + photoStateKey) refresh();
  };
  window.addEventListener(photosChangedEvent, refresh);
  window.addEventListener('storage', storage);
  onScopeDispose(() => {
    alive = false;
    window.removeEventListener(photosChangedEvent, refresh);
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
        refresh();
        selected.value = [];
        notice.value = message;
      }
      return true;
    } catch (cause) {
      if (alive) error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить изменения.';
      return false;
    } finally {
      if (alive) busy.value = false;
    }
  }
  function assign(code: string) {
    const ids = [...selected.value];
    const groupId = group.value?.id ?? '';
    return act((token) => assignPhotos(token, String(route.params.shootId), groupId, ids, code), 'Кадры назначены ребёнку.');
  }
  function setCover(id: string) {
    const groupId = group.value?.id ?? '';
    return act((token) => chooseCover(token, String(route.params.shootId), groupId, id), 'Обложка группы сохранена.');
  }
  function transfer(child: string, toId: string, code: string, ids: string[]) {
    const groupId = group.value?.id ?? '';
    return act(
      (token) => moveChild(token, String(route.params.shootId), groupId, child, toId, code, ids),
      'Полный набор ребёнка перенесён.'
    );
  }
  return {
    ...organization,
    loadError: organization.error,
    institution,
    shoot,
    groups,
    group,
    selectedGroupId,
    editable,
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
  };
}
