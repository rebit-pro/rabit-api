import type { LinkGroup } from './types';

export type GalleryStepKey = 'photos' | 'assign' | 'conditions' | 'staff' | 'prepare' | 'transmit';
export interface GalleryStep {
  key: GalleryStepKey;
  title: string;
  /** What is still missing for this step; empty when it is done or not reached yet. */
  hint: string;
  state: 'done' | 'current' | 'todo';
}
type PathFacts = Pick<LinkGroup, 'kind' | 'problems' | 'prepared' | 'sentAt'>;

const hints: Record<string, string> = {
  noPhotos: 'В группе ещё нет готовых кадров — загрузите фотографии.',
  photosProcessing: 'Часть кадров ещё обрабатывается — дождитесь превью.',
  unassignedPhotos: 'Есть кадры без ребёнка — назначьте код или удалите лишние.',
  noProducts: 'Нет доступной продукции с корректной ценой.',
  staffRequestsPending: 'Завершите проверку списков сотрудников этой группы.',
  prepare: 'Организатор подтверждает кадры и условия в «Ссылках и сроках» — после этого ссылку можно скопировать.',
  transmit: 'Родители увидят кадры только после этой отметки; с неё же начинается срок приёма заказов.'
};

/**
 * Steps from uploading frames to the open gallery, built from the link readiness the server already reports.
 * Parents see the frames only after the transmission is marked; until then the gallery says «ещё готовятся».
 */
export function galleryPath(group: PathFacts): GalleryStep[] {
  const blocked = (codes: string[]) => codes.filter((code) => group.problems.includes(code));
  const steps: { key: GalleryStepKey; title: string; problems: string[]; done: boolean }[] = [
    { key: 'photos', title: 'Загрузить кадры', problems: blocked(['noPhotos', 'photosProcessing']), done: false },
    { key: 'assign', title: 'Распределить кадры по детям', problems: blocked(['unassignedPhotos']), done: false },
    { key: 'conditions', title: 'Настроить условия продажи', problems: blocked(['noProducts']), done: false },
    ...(group.kind === 'staff'
      ? [{ key: 'staff' as const, title: 'Проверить списки сотрудников', problems: blocked(['staffRequestsPending']), done: false }]
      : []),
    { key: 'prepare', title: 'Проверить ссылку', problems: [], done: group.prepared || !!group.sentAt },
    { key: 'transmit', title: 'Отметить передачу ссылки', problems: [], done: !!group.sentAt }
  ];
  for (const step of steps) if (step.key !== 'prepare' && step.key !== 'transmit') step.done = !step.problems.length;
  // Without frames there is nothing to distribute yet.
  steps[1]!.done &&= !group.problems.includes('noPhotos');
  const current = group.sentAt ? -1 : steps.findIndex((step) => !step.done);
  return steps.map((step, index) => ({
    key: step.key,
    title: step.title,
    hint: step.problems.length ? step.problems.map((code) => hints[code]).join(' ') : index === current ? (hints[step.key] ?? '') : '',
    state: index === current ? 'current' : step.done ? 'done' : 'todo'
  }));
}
