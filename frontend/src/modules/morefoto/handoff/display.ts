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
