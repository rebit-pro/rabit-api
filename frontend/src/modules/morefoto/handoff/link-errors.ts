/** An F2 save failure reduced to what the text depends on: no HTTP answer at all, or its status and error code. */
export interface LinkProblem {
  network: boolean;
  status?: number;
  code?: string;
}

// A draft is restored with its Idempotency-Key (#28), so reloading the page or reopening the form repeats the same
// attempt: only «Загрузить актуальные данные» gives the form fresh data and a new key.
const messages: Record<string, string> = {
  REVISION_CONFLICT: 'Ссылку уже изменили. Загрузите актуальные данные и повторите действие.',
  SIGNATURE_CONFLICT: 'Фотографии, условия или списки изменились после открытия формы. Загрузите актуальные данные и проверьте заново.',
  LINK_NOT_READY: 'Группа ещё не готова: устраните проблемы, указанные в карточке.',
  LINK_NOT_PREPARED: 'Подборка или условия изменились. Организатор должен проверить ссылку перед передачей.',
  LINK_ALREADY_SENT: 'Приём уже запускался. Дату можно исправить отдельно.',
  LINK_NOT_SENT: 'Передача ссылки ещё не отмечена.',
  SENT_AT_IN_FUTURE: 'Дата передачи не может быть позже текущего времени.',
  SENT_AT_BEFORE_LINK: 'Ссылку не могли передать раньше, чем её выдали после проверки.',
  SENT_AT_UNCHANGED: 'Новая дата совпадает с записанной.',
  IDEMPOTENCY_CONFLICT: 'Эта попытка уже сохранена сервером с другими данными. Загрузите актуальные данные.',
  GROUP_NOT_FOUND: 'Группа больше не доступна в вашей области.',
  FORBIDDEN: 'Для этого действия недостаточно прав.'
};

/** Text of an F2 save failure. No answer is an unknown outcome, never a proof that nothing was saved (#81). */
export function linkErrorText(problem: LinkProblem): string {
  if (problem.network)
    return 'Ответ сервера не получен — изменение могло сохраниться. Отправьте форму ещё раз без правок (повтор безопасен) или загрузите актуальные данные.';
  const known = problem.code ? messages[problem.code] : undefined;
  if (known) return known;
  if (problem.status === 403) return messages.FORBIDDEN!;
  if (problem.status === 404) return messages.GROUP_NOT_FOUND!;
  if (problem.status === 422) return 'Проверьте заполненные поля формы.';
  return 'Сервер не сохранил изменение. Повторите действие позже.';
}
