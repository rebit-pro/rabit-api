import type { GalleryChild, GalleryPhoto } from '../gallery/types.js';

export interface PhotoAssignment {
  childId: string;
  childCode: string;
  sequence: number;
  code: string;
}
export interface ManagedPhoto extends GalleryPhoto {
  shootId: string;
  groupId: string;
  originalGroupId: string;
  childCode: string | null;
  sequence: number | null;
  assignments: PhotoAssignment[];
  filename: string;
  bytes: number;
  fingerprint: string;
  source: 'seed' | 'local' | 'server';
  revision: number;
}
import type { StaffRequest, Operation } from '../handoff/types.js';
export interface PhotoState {
  staffRequests?: StaffRequest[];
  staffOperations?: Operation[];
  photos: ManagedPhoto[];
  covers: Record<string, string>;
}
export type QueueStatus = 'queued' | 'uploading' | 'processing' | 'done' | 'duplicate' | 'error' | 'interrupted';
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
  serverId?: string;
  acceptedAt?: number;
  checkAt?: number;
  checks?: number;
  /** Archive upload: codes sent with the file, the archive identity and the entry path inside it. */
  childCodes?: string[];
  archive?: string;
  entry?: string;
  /** Archive folder the file comes from: a child code or a group folder name. */
  folder?: string;
  shared?: boolean;
  /** Automatic repeats after transient failures and the time of the next one. */
  attempts?: number;
  retryAt?: number;
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
