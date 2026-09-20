import { onScopeDispose, shallowRef, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { conditionsApi, conditionsError, type ConditionsSnapshot } from './api';

export function useConditions(groupId?: MaybeRefOrGetter<string | null>) {
  const snapshot = shallowRef<ConditionsSnapshot | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  const groupScoped = groupId !== undefined;
  let generation = 0;
  let alive = true;
  async function reload(): Promise<boolean> {
    const id = groupScoped ? toValue(groupId!) : null;
    const request = ++generation;
    error.value = '';
    if (groupScoped && !id) {
      snapshot.value = null;
      loading.value = false;
      return false;
    }
    loading.value = true;
    try {
      const result = await conditionsApi.get(id);
      if (!alive || request !== generation) return false;
      snapshot.value = result;
      return true;
    } catch (cause) {
      if (alive && request === generation) {
        snapshot.value = null;
        error.value = conditionsError(cause);
      }
      return false;
    } finally {
      if (alive && request === generation) loading.value = false;
    }
  }
  watch(
    () => (groupScoped ? toValue(groupId!) : 'global'),
    () => void reload(),
    { immediate: true }
  );
  onScopeDispose(() => {
    alive = false;
    generation++;
  });
  return { snapshot, loading, error, reload };
}
