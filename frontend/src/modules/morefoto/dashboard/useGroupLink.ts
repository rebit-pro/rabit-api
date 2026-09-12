import { shallowRef } from 'vue';
import type { Group } from '../types';
export function useGroupLink() {
  const notice = shallowRef('');
  const url = (group: Group) => (group.galleryToken ? window.location.origin + '/g/' + group.galleryToken : '');
  async function copy(group: Group) {
    const value = url(group);
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      notice.value = 'Ссылка скопирована. Дата передачи не изменена.';
    } catch {
      notice.value = 'Не удалось скопировать автоматически. Выделите ссылку в поле и скопируйте её.';
    }
  }
  return { notice, url, copy };
}
