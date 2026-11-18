import type { LinkGroup } from './types.ts';

export function shootDelivery(group: LinkGroup): {
  manual: boolean | null;
  label: string;
} {
  if (group.manualSentAt) return { manual: null, label: 'Отправка уже отмечена' };
  if (group.openingStatus === 'scheduled') return { manual: true, label: 'Открытие по расписанию' };
  if (group.openingStatus === 'opened') return { manual: true, label: 'Галерея открыта автоматически' };
  if (group.sentAt) return { manual: null, label: 'Галерея уже открыта' };
  if (group.openingStatus === 'blocked' || !group.prepared || group.problems.length) return { manual: null, label: 'Требует проверки' };
  return { manual: false, label: 'Готова к передаче' };
}
