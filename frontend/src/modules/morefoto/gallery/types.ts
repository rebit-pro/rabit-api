export interface GalleryPhoto {
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
export type GalleryState = 'preparing' | 'open' | 'closed';
export interface GallerySnapshot {
  groupId: string;
  institutionName: string;
  groupName: string;
  shootName: string;
  audience: 'regular' | 'staff';
  state: GalleryState;
  sentAt: string | null;
  closesAt: string | null;
  referenceNow: string;
  delivery: string;
  curator: string;
  children: GalleryChild[];
}
