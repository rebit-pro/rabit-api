import type { QuestionProblem } from './types.ts';
import { MESSAGE_MAX, NAME_MAX } from './rules.ts';

/** Same bound as the server: the curator must be able to write or call back. */
export const CONTACT_MAX = 120;

export interface FeedbackDraft {
  name: string;
  contact: string;
  message: string;
}

export function isFeedbackContact(value: string): boolean {
  const contact = value.trim().replace(/\s+/g, ' ');
  if (contact.length === 0 || contact.length > CONTACT_MAX) return false;
  if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact)) return true;
  return /^\+?[\d\s()-]+$/.test(contact) && contact.replace(/\D/g, '').length >= 10;
}

export function feedbackFieldProblems(draft: FeedbackDraft): Partial<Record<keyof FeedbackDraft, string>> {
  const problems: Partial<Record<keyof FeedbackDraft, string>> = {};
  const name = draft.name.trim();
  const message = draft.message.trim();
  if (name.length === 0) problems.name = 'Укажите, как к вам обращаться';
  else if (name.length > NAME_MAX) problems.name = `Не больше ${NAME_MAX} символов`;
  if (draft.contact.trim().length === 0) problems.contact = 'Укажите телефон или email';
  else if (!isFeedbackContact(draft.contact)) problems.contact = 'Проверьте телефон или email';
  if (message.length === 0) problems.message = 'Напишите, чем помочь';
  else if (message.length > MESSAGE_MAX) problems.message = `Не больше ${MESSAGE_MAX} символов`;
  return problems;
}

/** One Idempotency-Key per payload: an unchanged repeat after a lost answer never creates a second request. */
export function feedbackPayloadKey(draft: FeedbackDraft): string {
  return JSON.stringify([draft.name.trim(), draft.contact.trim(), draft.message.trim()]);
}

export function feedbackProblemMessage(problem: QuestionProblem): string {
  if (problem.network) return 'Нет связи с сервером. Текст сохранён — отправьте ещё раз.';
  if (problem.code === 'RATE_LIMITED') return 'Сейчас слишком много обращений. Попробуйте через час.';
  if (problem.code === 'INVALID_FEEDBACK_CONTACT') return 'Проверьте телефон или email — по нему мы ответим.';
  if (problem.code === 'INVALID_QUESTION_NAME') return `Имя — от 1 до ${NAME_MAX} символов, без служебных знаков.`;
  if (problem.code === 'INVALID_QUESTION_MESSAGE') return `Сообщение — от 1 до ${MESSAGE_MAX} символов, без служебных знаков.`;
  if (problem.code === 'IDEMPOTENCY_CONFLICT') return 'Обращение уже отправляется. Подождите немного и проверьте ещё раз.';
  return 'Не удалось отправить обращение. Повторите попытку.';
}
