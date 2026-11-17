export interface InstitutionDigitalArchive {
  id: string;
  status: 'pending' | 'ready' | 'failed' | 'empty' | 'expired';
  files: number;
  attempts: number;
  bytes: number | null;
  error: string | null;
  capturedAt: string;
  readyAt: string | null;
  expiresAt: string | null;
}

export interface InstitutionDigitalArchives {
  canCreate: boolean;
  items: InstitutionDigitalArchive[];
}
