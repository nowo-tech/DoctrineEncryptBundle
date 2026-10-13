// REQ-DEMO-013: convert Playwright PNG captures in a directory to WebP and remove the PNGs.
// Usage: node scripts/to-webp.mjs <dir> [quality]
import { readdir, unlink } from 'node:fs/promises';
import { join } from 'node:path';
import sharp from 'sharp';

const dir = process.argv[2];
const quality = Number(process.argv[3] ?? 90);
if (!dir) {
  console.error('usage: node scripts/to-webp.mjs <dir> [quality]');
  process.exit(2);
}
let converted = 0;
for (const name of await readdir(dir)) {
  if (!name.toLowerCase().endsWith('.png')) continue;
  const src = join(dir, name);
  const dest = join(dir, name.replace(/\.png$/i, '.webp'));
  await sharp(src).webp({ quality, effort: 6 }).toFile(dest);
  await unlink(src);
  converted++;
  console.log(`webp: ${dest}`);
}
console.log(`${converted} screenshot(s) converted to WebP`);
