import api from '@/api/http';
import type { ArchiveVersion, GroupArchive, GroupArchiveStatus } from './archive-types';

const group = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId) + '/production';
const version = (versionId: string) => '/api/v1/production/versions/' + encodeURIComponent(versionId);
/** A command key without dashes; one key per attempt, a lost answer is resolved by reloading the block. */
const key = () => crypto.randomUUID().replace(/-/g, '');

export const archiveApi = {
  /** PRD-01: states of the groups of one page of «Ссылки и сроки». */
  async statuses(groupIds: string[]): Promise<GroupArchiveStatus[]> {
    if (!groupIds.length) return [];
    return (await api.get<GroupArchiveStatus[]>('/api/v1/production/groups', { params: { groupIds: groupIds.join(',') } })).data;
  },
  async group(groupId: string): Promise<GroupArchive> {
    return (await api.get<GroupArchive>(group(groupId))).data;
  },
  /** PRD-03: the first archive of a group closed before the release or a supplement; the organizer only. */
  async build(groupId: string): Promise<ArchiveVersion> {
    return (await api.post<ArchiveVersion>(group(groupId) + '/versions', null, { headers: { 'Idempotency-Key': key() } })).data;
  },
  /** A new link revokes the one given to the lab; an expired or failed archive is built again. */
  async renew(versionId: string): Promise<ArchiveVersion> {
    return (await api.post<ArchiveVersion>(version(versionId) + '/renewal', null, { headers: { 'Idempotency-Key': key() } })).data;
  },
  /** PRD-07: the CSV composition; the request carries the Bearer, so it is fetched and saved as a blob. */
  async manifest(exportUrl: string): Promise<Blob> {
    return (await api.get<Blob>(exportUrl, { responseType: 'blob' })).data;
  }
};
