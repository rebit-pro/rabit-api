import type { StatusTone } from '../../../components/status/tones';

/**
 * One place that decides the tone of every domain status (design plan 10.4). Labels stay with their modules;
 * a tone never changes meaning between screens: «заблокирован» is danger, «ожидает регистрации» is pending.
 */
export const accountStatusTone = { active: 'success', pending: 'pending', blocked: 'danger' } as const satisfies Record<string, StatusTone>;

export const groupStateTone = { preparing: 'neutral', open: 'success', closed: 'neutral' } as const satisfies Record<string, StatusTone>;

export const staffRequestTone = { submitted: 'info', clarification: 'warning', transferred: 'success' } as const satisfies Record<
  string,
  StatusTone
>;

export const paymentTone = { unpaid: 'neutral', pending: 'warning', declined: 'danger', paid: 'success' } as const satisfies Record<
  string,
  StatusTone
>;

export const productionTone = {
  'not-started': 'neutral',
  queued: 'info',
  printing: 'info',
  ready: 'success',
  delivered: 'success'
} as const satisfies Record<string, StatusTone>;

export interface LinkStatusInput {
  state: string;
  sentAt?: string | null;
  prepared?: boolean;
}

/** Link card of a group: closed intake, open intake, ready to hand over or still to be checked. */
export function linkStatus(group: LinkStatusInput): { tone: StatusTone; text: string } {
  if ('closed' === group.state) return { tone: 'neutral', text: 'Приём завершён' };
  if (group.sentAt) return { tone: 'success', text: 'Приём открыт' };
  if (group.prepared) return { tone: 'info', text: 'Готова к передаче' };
  return { tone: 'warning', text: 'Требует проверки' };
}
