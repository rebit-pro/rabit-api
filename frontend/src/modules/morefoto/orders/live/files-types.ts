export type FilesState = 'unpaid' | 'review' | 'empty' | 'expired' | 'available';
export type DownloadStatus = 'pending' | 'ready' | 'failed' | 'expired';
export type DownloadKind = 'file' | 'zip';
export interface OrderFile {
  photoId: string;
  code: string;
  childCode: string;
  filename: string;
  mimeType: string;
  bytes: number;
}
export interface OrderFiles {
  state: FilesState;
  expiresAt: string | null;
  canDownload: boolean;
  totalBytes: number;
  items: OrderFile[];
}
export interface FileDownload {
  id: string;
  kind: DownloadKind;
  status: DownloadStatus;
  expiresAt: string | null;
  filename: string | null;
  error: string | null;
  contentUrl: string | null;
}
export interface DownloadBody {
  kind: DownloadKind;
  photoIds?: string[];
}
