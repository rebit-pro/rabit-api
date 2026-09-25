/** Status tones of the design system (design plan 7.2): color never carries the meaning alone, the text does. */
export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'pending';

export const STATUS_TONES: readonly StatusTone[] = ['success', 'warning', 'danger', 'info', 'neutral', 'pending'];

/** Tone of a domain value; unknown values stay neutral instead of borrowing a misleading color. */
export function toneOf<K extends string>(map: Readonly<Record<K, StatusTone>>, value: string | null | undefined): StatusTone {
  return null !== value && undefined !== value && Object.prototype.hasOwnProperty.call(map, value) ? map[value as K] : 'neutral';
}
