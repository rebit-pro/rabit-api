import type { HandoffCommand } from './types';

/**
 * The command a handoff form opens with. A stored draft of the same kind is always restored with its body and
 * Idempotency-Key: repeating a save whose answer was lost must be a replay, and the user's edits are replaced only by
 * the explicit «Загрузить актуальные данные». `stale` marks a draft written for another server revision (#28).
 */
export function openDraft(
  draft: HandoffCommand | null,
  fresh: HandoffCommand
): { command: HandoffCommand; restored: boolean; stale: boolean } {
  if (draft?.kind !== fresh.kind) return { command: fresh, restored: false, stale: false };
  return { command: draft, restored: true, stale: draft.revision !== fresh.revision };
}
