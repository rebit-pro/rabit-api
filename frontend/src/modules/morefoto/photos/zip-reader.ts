/**
 * Minimal ZIP reader for the archive upload: the central directory is read once, every entry is cut out of the
 * archive only when its turn comes. A stored entry is a slice of the original file, so gigabytes never land in
 * memory; a deflated one is inflated by the browser stream.
 */
export interface ZipEntry {
  path: string;
  bytes: number;
  compressedBytes: number;
  method: number;
  offset: number;
  directory: boolean;
  encrypted: boolean;
}

export class ZipFormatError extends Error {}

const endSignature = 0x06054b50;
const zip64LocatorSignature = 0x07064b50;
const zip64EndSignature = 0x06064b50;
const centralSignature = 0x02014b50;
const localSignature = 0x04034b50;
const maxComment = 0xffff;

async function view(file: Blob, start: number, end: number): Promise<DataView> {
  return new DataView(await file.slice(start, end).arrayBuffer());
}

function u64(data: DataView, at: number): number {
  const value = data.getBigUint64(at, true);
  if (value > BigInt(Number.MAX_SAFE_INTEGER)) throw new ZipFormatError('Архив слишком большой.');
  return Number(value);
}

/** Split volumes (.z01, .zip.001) are parts of one archive and cannot be read one by one. */
export function isVolumePart(name: string): boolean {
  return /\.(z\d{2}|zip\.\d{3})$/i.test(name);
}

export async function readZipEntries(file: Blob): Promise<ZipEntry[]> {
  const tailStart = Math.max(0, file.size - 22 - maxComment);
  const tail = await view(file, tailStart, file.size);
  let end = -1;
  for (let at = tail.byteLength - 22; at >= 0; at--) {
    if (tail.getUint32(at, true) === endSignature) {
      end = at;
      break;
    }
  }
  if (end < 0) throw new ZipFormatError('Файл не является ZIP-архивом или повреждён.');
  if (tail.getUint16(end + 4, true) !== 0 || tail.getUint16(end + 6, true) !== 0)
    throw new ZipFormatError('Это часть многотомного архива. Нужны самостоятельные ZIP-архивы.');
  let count = tail.getUint16(end + 10, true);
  let size = tail.getUint32(end + 12, true);
  let offset = tail.getUint32(end + 16, true);
  if (count === 0xffff || size === 0xffffffff || offset === 0xffffffff) {
    const locatorAt = tailStart + end - 20;
    const locator = await view(file, locatorAt, locatorAt + 20);
    if (locatorAt < 0 || locator.getUint32(0, true) !== zip64LocatorSignature) throw new ZipFormatError('Повреждён каталог архива.');
    const zip64At = u64(locator, 8);
    const zip64 = await view(file, zip64At, zip64At + 56);
    if (zip64.getUint32(0, true) !== zip64EndSignature) throw new ZipFormatError('Повреждён каталог архива.');
    count = u64(zip64, 32);
    size = u64(zip64, 40);
    offset = u64(zip64, 48);
  }
  if (offset + size > file.size) throw new ZipFormatError('Архив обрезан: каталог выходит за конец файла.');
  const directory = await view(file, offset, offset + size);
  const utf8 = new TextDecoder('utf-8');
  // Windows Explorer writes non-Latin names in the OEM code page and does not set the UTF-8 flag.
  const oem = new TextDecoder('ibm866');
  const entries: ZipEntry[] = [];
  let at = 0;
  for (let index = 0; index < count; index++) {
    if (at + 46 > directory.byteLength || directory.getUint32(at, true) !== centralSignature)
      throw new ZipFormatError('Повреждён каталог архива.');
    const flags = directory.getUint16(at + 8, true);
    const nameLength = directory.getUint16(at + 28, true);
    const extraLength = directory.getUint16(at + 30, true);
    const commentLength = directory.getUint16(at + 32, true);
    const name = new Uint8Array(directory.buffer, directory.byteOffset + at + 46, nameLength);
    let compressedBytes = directory.getUint32(at + 20, true);
    let bytes = directory.getUint32(at + 24, true);
    let local = directory.getUint32(at + 42, true);
    for (let extra = at + 46 + nameLength; extra + 4 <= at + 46 + nameLength + extraLength; ) {
      const id = directory.getUint16(extra, true);
      const length = directory.getUint16(extra + 2, true);
      if (id === 0x0001) {
        // Only the values marked 0xFFFFFFFF in the record are present, in this fixed order.
        let field = extra + 4;
        if (bytes === 0xffffffff) {
          bytes = u64(directory, field);
          field += 8;
        }
        if (compressedBytes === 0xffffffff) {
          compressedBytes = u64(directory, field);
          field += 8;
        }
        if (local === 0xffffffff) local = u64(directory, field);
      }
      extra += 4 + length;
    }
    const path = ((flags & 0x800) !== 0 ? utf8 : oem).decode(name).replace(/\\/g, '/');
    entries.push({
      path,
      bytes,
      compressedBytes,
      method: directory.getUint16(at + 10, true),
      offset: local,
      directory: path.endsWith('/'),
      encrypted: (flags & 0x1) !== 0
    });
    at += 46 + nameLength + extraLength + commentLength;
  }

  return entries;
}

export async function zipEntryBlob(file: Blob, entry: ZipEntry): Promise<Blob> {
  if (entry.encrypted) throw new ZipFormatError('Файл в архиве защищён паролем.');
  const header = await view(file, entry.offset, entry.offset + 30);
  if (header.byteLength < 30 || header.getUint32(0, true) !== localSignature) throw new ZipFormatError('Повреждена запись архива.');
  const start = entry.offset + 30 + header.getUint16(26, true) + header.getUint16(28, true);
  const data = file.slice(start, start + entry.compressedBytes);
  if (entry.method === 0) return data;
  if (entry.method === 8) return new Response(data.stream().pipeThrough(new DecompressionStream('deflate-raw'))).blob();
  throw new ZipFormatError('Неподдерживаемый способ сжатия в архиве. Пересоберите архив в формате ZIP.');
}
