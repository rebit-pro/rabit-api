import type { ManagedPhoto } from './types.js';

// 48 fills whole rows at 2, 3, 4 and 6 columns of the frame grid.
export const photoPageSize = 48;
export interface PhotoGroupSummary {
  photos: number;
  unassigned: number;
  children: string[];
}
export interface PhotoPageView {
  items: ManagedPhoto[];
  total: number;
  summary: PhotoGroupSummary;
}
/** `all`, `unassigned` or a child code taken from the URL; anything else shows all frames. */
export function photoFilter(value: unknown): string {
  return typeof value === 'string' && (value === 'unassigned' || /^[A-Z]{1,3}$/.test(value)) ? value : 'all';
}
export function photoPage(value: unknown): number {
  return typeof value === 'string' && /^[1-9]\d{0,5}$/.test(value) ? Number(value) : 1;
}
export function photoPages(total: number, pageSize = photoPageSize): number {
  return Math.max(1, Math.ceil(total / pageSize));
}
function matches(photo: ManagedPhoto, filter: string): boolean {
  if (filter === 'all') return true;
  if (filter === 'unassigned') return photo.assignments.length === 0;
  return photo.assignments.some((assignment) => assignment.childCode === filter);
}
/** Demo twin of MED-02: the same page, total and group summary over the frames kept in the browser. */
export function localPhotoPage(
  photos: ManagedPhoto[],
  groupId: string,
  filter: string,
  page: number,
  pageSize = photoPageSize
): PhotoPageView {
  const group = photos.filter((photo) => photo.groupId === groupId);
  const shown = group.filter((photo) => matches(photo, filter));
  return {
    items: shown.slice((page - 1) * pageSize, page * pageSize),
    total: shown.length,
    summary: {
      photos: group.length,
      unassigned: group.filter((photo) => photo.assignments.length === 0).length,
      children: [...new Set(group.flatMap((photo) => photo.assignments.map((assignment) => assignment.childCode)))].sort()
    }
  };
}
