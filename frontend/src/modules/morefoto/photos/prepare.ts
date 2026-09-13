import { fileProblem, photoLimits } from './rules';
import type { PreparedPhoto } from './types';
function check(signal: AbortSignal) {
  if (signal.aborted) throw new Error('Подготовка остановлена. Выберите файл снова для повтора.');
}
async function render(bitmap: ImageBitmap, longest: number): Promise<Blob> {
  const scale = Math.min(1, longest / Math.max(bitmap.width, bitmap.height));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(bitmap.width * scale));
  canvas.height = Math.max(1, Math.round(bitmap.height * scale));
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Браузер не поддерживает подготовку превью.');
  context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
  const fontSize = Math.max(16, Math.round(canvas.width / 11));
  context.font = '600 ' + fontSize + 'px sans-serif';
  context.textAlign = 'center';
  context.strokeStyle = 'rgba(0,0,0,.35)';
  context.fillStyle = 'rgba(255,255,255,.65)';
  context.lineWidth = 2;
  context.save();
  context.translate(canvas.width / 2, canvas.height / 2);
  context.rotate(-Math.PI / 7);
  for (const y of [-canvas.height / 3, 0, canvas.height / 3]) {
    context.strokeText('MoreFoto · ПРЕВЬЮ', 0, y);
    context.fillText('MoreFoto · ПРЕВЬЮ', 0, y);
  }
  context.restore();
  return new Promise((resolve, reject) =>
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Не удалось создать превью.'))), 'image/webp', 0.78)
  );
}
export async function preparePhoto(file: File, signal: AbortSignal, progress: (value: number) => void): Promise<PreparedPhoto> {
  const problem = fileProblem(file);
  if (problem) throw new Error(problem);
  check(signal);
  progress(10);
  const bytes = await file.arrayBuffer();
  check(signal);
  const hash = await crypto.subtle.digest('SHA-256', bytes);
  const fingerprint = Array.from(new Uint8Array(hash), (value) => value.toString(16).padStart(2, '0')).join('');
  progress(30);
  let bitmap: ImageBitmap;
  try {
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
  } catch {
    throw new Error('Изображение не читается. Проверьте исходный файл.');
  }
  try {
    check(signal);
    if (bitmap.width * bitmap.height > photoLimits.pixels) throw new Error('Изображение больше 40 мегапикселей. Уменьшите размер.');
    progress(50);
    const preview = await render(bitmap, 1200);
    check(signal);
    progress(75);
    const thumb = await render(bitmap, 320);
    check(signal);
    return { fingerprint, width: bitmap.width, height: bitmap.height, thumb, preview };
  } finally {
    bitmap.close();
  }
}
