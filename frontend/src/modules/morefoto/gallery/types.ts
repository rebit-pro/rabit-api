import type { SalesState } from '../commerce/sales-windows.js';

export interface GalleryPhoto {
  assignmentId?: string;
  id: string;
  code: string;
  /** Original uploaded basename, including its extension; absent in older API responses. */
  filename?: string;
  thumbSrc: string;
  previewSrc: string;
  width: number;
  height: number;
}
export interface GalleryChild {
  code: string;
  photos: GalleryPhoto[];
}
export type GalleryState = SalesState;
export interface GallerySnapshot {
  groupId: string;
  institutionName: string;
  groupName: string;
  shootName: string;
  audience: 'regular' | 'staff';
  state: GalleryState;
  scheduledOpenAt?: string | null;
  sentAt: string | null;
  /** Print sales close (T0+7). */
  closesAt: string | null;
  /** Digital sales close (T0+14). */
  digitalSalesUntil: string | null;
  /** D4: the markup is being corrected — frames can be viewed, buying is paused until the correction ends. */
  updating?: boolean;
  /** Internal key for public API calls; the browser keeps the original short address. */
  token?: string;
  referenceNow: string;
  delivery: string;
  curator: string;
  children: GalleryChild[];
}
