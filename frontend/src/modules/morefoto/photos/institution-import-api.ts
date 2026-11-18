import api from '@/api/http';
import { isAxiosError } from 'axios';

export function importGroupsUnavailable(cause: unknown): boolean {
  return isAxiosError(cause) && cause.response?.status === 409 && cause.response.data?.error?.code === 'IMPORT_GROUPS_UNAVAILABLE';
}

export async function confirmImportGroups(
  institutionId: string,
  shootId: string,
  groups: string[],
  key: string,
  signal: AbortSignal
): Promise<string[]> {
  const response = await api.post<{
    groupIds: string[];
  }>(
    '/api/v1/institutions/' + encodeURIComponent(institutionId) + '/shoots/' + encodeURIComponent(shootId) + '/import-groups',
    { groups },
    { signal, headers: { 'Idempotency-Key': key } }
  );
  return response.data.groupIds;
}
