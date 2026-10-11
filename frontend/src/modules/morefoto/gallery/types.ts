import type { SalesState } from '../commerce/sales-windows.js';

export interface GalleryPhoto {
  assignmentId?: string;
  id: string;
  code: string;
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
  sentAt: string | null;
  /** Print sales close (T0+7). */
  closesAt: string | null;
  /** Digital sales close (T0+14). */
  digitalSalesUntil: string | null;
  referenceNow: string;
  delivery: string;
  curator: string;
  children: GalleryChild[];
}
