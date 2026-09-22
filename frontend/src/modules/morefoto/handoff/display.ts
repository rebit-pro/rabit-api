export const formatMoment = (value: string | null | undefined) =>
  value
    ? new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Europe/Moscow'
      }).format(new Date(value)) + ' МСК'
    : 'Не назначено';
export const requestStatus = { submitted: 'На проверке', clarification: 'Нужно уточнение', transferred: 'Проверен и перенесён' };
const problems: Record<string, string> = {
  noPhotos: 'В группе ещё нет готовых фотографий.',
  photosProcessing: 'Часть фотографий ещё обрабатывается.',
  unassignedPhotos: 'Распределите все фотографии по детям.',
  noProducts: 'Нет доступной продукции с корректной ценой.',
  staffRequestsPending: 'Сначала завершите проверку списков сотрудников этой группы.'
};
/** Live problems are server codes; demo problems are already sentences. */
export const problemText = (problem: string) => problems[problem] ?? problem;
