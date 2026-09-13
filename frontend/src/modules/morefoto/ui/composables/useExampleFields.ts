import { computed, ref, watch } from 'vue';
import type { UiFieldState } from '../types';

export function useExampleFields(state: () => UiFieldState, longText: () => boolean) {
  const name = ref<string | null>('');
  const product = ref<string | null>(null);
  const institution = ref<string | null>(null);
  const comment = ref('');
  const listState = ref<'ready' | 'empty' | 'error'>('ready');
  const products = computed(() =>
    listState.value === 'ready'
      ? [
          {
            id: 'print',
            name: longText() ? 'Набор из двух одинаковых отпечатков в подарочной упаковке' : 'Отпечаток 15 × 21'
          },
          { id: 'canvas', name: 'Холст 30 × 45' },
          { id: 'digital', name: 'Электронный файл' }
        ]
      : []
  );
  const institutions = ['Детский сад «Облако»', 'Школа «Горизонт»', 'Центр дополнительного образования «Большие открытия»'];
  const disabled = computed(() => state() === 'disabled' || state() === 'loading');
  const readonly = computed(() => state() === 'readonly');
  const error = computed(() => (state() === 'error' ? 'Проверьте значение и исправьте поле.' : ''));
  const choiceError = computed(() => (listState.value === 'error' ? 'Не удалось загрузить продукцию. Повторите загрузку.' : error.value));
  watch(
    [state, longText],
    () => {
      const filled = state() !== 'empty';
      name.value = filled ? (longText() ? 'Тестовый покупатель с длинным составным именем' : 'Тестовый покупатель') : '';
      product.value = filled ? 'print' : null;
      institution.value = filled ? (institutions[0] ?? null) : null;
      comment.value = filled ? 'Пожалуйста, положите отпечатки в один конверт.' : '';
    },
    { immediate: true }
  );
  function changeList(value: 'ready' | 'empty' | 'error') {
    listState.value = value;
    if (value !== 'ready') product.value = null;
  }
  return {
    name,
    product,
    institution,
    comment,
    products,
    institutions,
    listState,
    disabled,
    readonly,
    error,
    choiceError,
    changeList
  };
}
