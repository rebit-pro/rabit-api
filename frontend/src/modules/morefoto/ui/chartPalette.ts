/**
 * The only mapping of cabinet categories to the eight data pastels (design plan 9.2). Tiles, distributions and
 * avatars share the indices, so a category keeps its color on every screen.
 */
export const CHART_CATEGORY = {
  requests: 0,
  orders: 1,
  photos: 2,
  staff: 3,
  institutions: 4,
  groups: 5,
  shoots: 6,
  other: 7
} as const;

export type ChartCategory = keyof typeof CHART_CATEGORY;
export type ChartIndex = (typeof CHART_CATEGORY)[ChartCategory];

/** Roles in a staff split: four of the pastels, never the status tones. */
export const ROLE_PASTEL = { organizer: 4, curator: 3, head: 6, teacher: 5 } as const;

/** Series beyond six collapse into «прочее» (pebble): more pastels in one chart stop being distinguishable. */
export const MAX_SERIES = 6;
