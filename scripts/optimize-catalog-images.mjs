import { readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const catalogRoot = path.resolve('public/catalog');

async function files(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  return (await Promise.all(entries.map((entry) => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? files(target) : [target];
  }))).flat();
}

const sources = (await files(catalogRoot)).filter((file) => file.toLowerCase().endsWith('.png'));
let sourceBytes = 0;
let optimizedBytes = 0;

for (const source of sources) {
  sourceBytes += (await stat(source)).size;
  const parsed = path.parse(source);
  const fullOutput = path.join(parsed.dir, `${parsed.name}.webp`);
  await sharp(source).rotate().webp({ quality: 84, alphaQuality: 95, effort: 6 }).toFile(fullOutput);
  optimizedBytes += (await stat(fullOutput)).size;

  for (const width of [640, 960]) {
    const output = path.join(parsed.dir, `${parsed.name}-${width}.webp`);
    await sharp(source).rotate().resize({ width, withoutEnlargement: true }).webp({ quality: 82, alphaQuality: 95, effort: 6 }).toFile(output);
    optimizedBytes += (await stat(output)).size;
  }
}

const reduction = sourceBytes === 0 ? 0 : ((sourceBytes - optimizedBytes) / sourceBytes) * 100;
console.log(JSON.stringify({ images: sources.length, sourceBytes, optimizedBytes, reductionPercent: Number(reduction.toFixed(2)) }, null, 2));
