import type { DistributionSegment } from '@/components/viz/MfDistribution.vue';
import { groupStateTone } from './statusTone';

/** Groups by the state of the parents' ordering, as one split for the overview, an institution and a shoot. */
export function groupStateSegments(counts: { preparing: number; open: number; closed: number }): DistributionSegment[] {
  return [
    // Two neutral states would merge in one bar: here «готовятся» is the work in progress (info).
    { key: 'preparing', label: 'Готовятся', value: counts.preparing, tone: 'info' },
    { key: 'open', label: 'Приём открыт', value: counts.open, tone: groupStateTone.open },
    { key: 'closed', label: 'Приём завершён', value: counts.closed, tone: groupStateTone.closed }
  ];
}
