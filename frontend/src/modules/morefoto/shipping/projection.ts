import type { Transfer, TransferSummary } from './types.js';
import { printCount } from '../production/rules.ts';
export function transferSummary(t: Transfer, allowed: Set<string>, staff: boolean): TransferSummary {
  return {
    id: t.id,
    number: t.number,
    institutionId: t.institutionId,
    institutionName: t.institutionName,
    shootId: t.shootId,
    shootName: t.shootName,
    at: t.at,
    recordedAt: t.recordedAt,
    actor: t.actor,
    responsible: t.responsible,
    receiver: t.receiver,
    comment: staff ? t.comment : '',
    groups: t.groups
      .filter((g) => allowed.has(g.groupId))
      .map((g) => ({
        groupId: g.groupId,
        groupName: g.groupName,
        kind: g.kind,
        packs: new Set(g.rows.map((r) => r.orderId)).size,
        prints: printCount(g.rows),
        deadline: g.deadline
      }))
  };
}
