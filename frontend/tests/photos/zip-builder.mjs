import { deflateRawSync } from 'node:zlib';

const u16 = (value) => {
  const bytes = Buffer.alloc(2);
  bytes.writeUInt16LE(value);
  return bytes;
};
const u32 = (value) => {
  const bytes = Buffer.alloc(4);
  bytes.writeUInt32LE(value);
  return bytes;
};
const u64 = (value) => {
  const bytes = Buffer.alloc(8);
  bytes.writeBigUInt64LE(BigInt(value));
  return bytes;
};

/**
 * Builds a ZIP in memory for reader tests. CRC is left zero: the reader never checks it.
 * An entry: { name: Buffer | string, data: Buffer | string, deflate?: boolean, utf8?: boolean }.
 */
export function buildZip(entries, { zip64 = false, disk = 0 } = {}) {
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const entry of entries) {
    const name = Buffer.isBuffer(entry.name) ? entry.name : Buffer.from(entry.name, 'utf8');
    const raw = Buffer.isBuffer(entry.data) ? entry.data : Buffer.from(entry.data ?? '');
    const data = entry.deflate ? deflateRawSync(raw) : raw;
    const flags = entry.utf8 === false ? 0 : 0x800;
    const method = entry.deflate ? 8 : 0;
    const local = Buffer.concat([
      u32(0x04034b50),
      u16(20),
      u16(flags),
      u16(method),
      u32(0),
      u32(0),
      u32(data.length),
      u32(raw.length),
      u16(name.length),
      u16(4),
      name,
      Buffer.from([0xfe, 0xca, 0, 0]),
      data
    ]);
    const extra = zip64 ? Buffer.concat([u16(0x0001), u16(24), u64(raw.length), u64(data.length), u64(offset)]) : Buffer.alloc(0);
    const mark = (value) => (zip64 ? 0xffffffff : value);
    centrals.push(
      Buffer.concat([
        u32(0x02014b50),
        u16(45),
        u16(20),
        u16(flags),
        u16(method),
        u32(0),
        u32(0),
        u32(mark(data.length)),
        u32(mark(raw.length)),
        u16(name.length),
        u16(extra.length),
        u16(0),
        u16(0),
        u16(0),
        u32(0),
        u32(mark(offset)),
        name,
        extra
      ])
    );
    locals.push(local);
    offset += local.length;
  }
  const directory = Buffer.concat(centrals);
  const tail = [];
  if (zip64) {
    const recordAt = offset + directory.length;
    tail.push(
      Buffer.concat([
        u32(0x06064b50),
        u64(44),
        u16(45),
        u16(45),
        u32(0),
        u32(0),
        u64(entries.length),
        u64(entries.length),
        u64(directory.length),
        u64(offset)
      ])
    );
    tail.push(Buffer.concat([u32(0x07064b50), u32(0), u64(recordAt), u32(1)]));
  }
  tail.push(
    Buffer.concat([
      u32(0x06054b50),
      u16(disk),
      u16(disk),
      u16(zip64 ? 0xffff : entries.length),
      u16(zip64 ? 0xffff : entries.length),
      u32(zip64 ? 0xffffffff : directory.length),
      u32(zip64 ? 0xffffffff : offset),
      u16(0)
    ])
  );

  return new Blob([...locals, directory, ...tail]);
}
