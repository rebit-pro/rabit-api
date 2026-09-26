import { folderCodes, type ArchivePlan } from './archive.ts';
import type { QueueStatus, UploadJob } from './types.ts';

/**
 * A file counts as delivered for a later resume only once its previews exist: an accepted file whose preparation
 * failed must be sent again, and the server restarts the preparation of the same photo.
 */
export function deliveredForResume(status: QueueStatus): boolean {
  return status === 'done' || status === 'duplicate';
}

/**
 * Turns a checked plan into queue jobs. Children's files delivered earlier are skipped (the remembered ones) or kept
 * (the ones still listed); group frames and everything not delivered are queued again.
 */
export function archiveJobs(
  plan: ArchivePlan,
  current: UploadJob[],
  target: { shootId: string; groupId: string; archives: ReadonlySet<string> },
  delivered: (archive: string, path: string) => boolean,
  id: () => string
): { jobs: UploadJob[]; skipped: number } {
  const { shootId, groupId, archives } = target;
  // Group frames are sent again every time: the server keeps one photo and only adds children labelled since.
  const kept = current.filter(
    (job) =>
      !(
        job.groupId === groupId &&
        job.archive &&
        archives.has(job.archive) &&
        (job.shared || !['processing', 'done', 'duplicate'].includes(job.status))
      )
  );
  const listed = new Set(kept.filter((job) => job.groupId === groupId && job.archive).map((job) => job.archive + '\n' + job.entry));
  const added: UploadJob[] = [];
  let skipped = 0;
  for (const folder of plan.folders) {
    const childCodes = folderCodes(folder, plan);
    for (const file of folder.files) {
      if (listed.has(file.archive + '\n' + file.path)) continue;
      if (folder.kind === 'child' && delivered(file.archive, file.path)) {
        skipped++;
        continue;
      }
      added.push({
        id: id(),
        shootId,
        groupId,
        filename: file.name,
        bytes: file.bytes,
        modified: 0,
        progress: 0,
        status: 'queued',
        message: 'Готов к отправке',
        childCodes,
        archive: file.archive,
        entry: file.path,
        folder: folder.code ?? folder.folder,
        shared: folder.kind === 'group'
      });
    }
  }

  return { jobs: [...kept, ...added], skipped };
}
