import type { GalleryChild, GalleryPhoto } from '../gallery/types.js';
export interface ManagedPhoto extends GalleryPhoto {
  shootId: string;
  groupId: string;
  originalGroupId: string;
  childCode: string | null;
  sequence: number | null;
  filename: string;
  bytes: number;
  fingerprint: string;
  source: 'seed' | 'local';
  revision: number;
}
import type { StaffRequest, Operation } from '../handoff/types.js';
export interface PhotoState {
  staffRequests?: StaffRequest[];
  staffOperations?: Operation[];
  photos: ManagedPhoto[];
  covers: Record<string, string>;
}
export type QueueStatus = 'queued' | 'processing' | 'done' | 'duplicate' | 'error' | 'interrupted';
export interface UploadJob {
  id: string;
  shootId: string;
  groupId: string;
  filename: string;
  bytes: number;
  modified: number;
  status: QueueStatus;
  progress: number;
  message: string;
}
export interface PreparedPhoto {
  fingerprint: string;
  width: number;
  height: number;
  thumb: Blob;
  preview: Blob;
}
export interface ChildBundle extends GalleryChild {
  groupId: string;
}
