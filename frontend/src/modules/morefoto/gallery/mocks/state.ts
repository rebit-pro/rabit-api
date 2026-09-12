import { demoChangedEvent } from '../../mocks/storage';
export type GalleryScenario = 'default' | 'preparing' | 'closed' | 'empty';
let scenario: GalleryScenario = 'default';
export function setGalleryScenario(value: GalleryScenario): void {
  scenario = value;
  window.dispatchEvent(new Event(demoChangedEvent));
}
export function getGalleryScenario(): GalleryScenario {
  return scenario;
}
