/**
 * Exports the Astro site's copy (src/content, both languages) to plain JSON
 * so the WordPress importer can rebuild every page from the same source.
 *   node wordpress/tools/export-content.mjs  →  wordpress/tools/build/content.json
 */
import { build } from 'esbuild';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const outDir = resolve(here, 'build');
mkdirSync(outDir, { recursive: true });

const entry = resolve(outDir, 'entry.ts');
writeFileSync(
  entry,
  `import * as en from '${root}/src/content/en';
import * as es from '${root}/src/content/es';
import { brand } from '${root}/src/config/brand';
import { pendingContent, pendingData } from '${root}/src/config/pendingContent';
import { interestParamMap } from '${root}/src/content/shared';
export default { en, es, brand, pendingContent, pendingData, interestParamMap };
`
);
const bundle = resolve(outDir, 'content.bundle.mjs');
await build({ entryPoints: [entry], bundle: true, format: 'esm', platform: 'node', outfile: bundle, logLevel: 'warning' });
const data = (await import(pathToFileURL(bundle).href + `?t=${Date.now()}`)).default;
writeFileSync(resolve(outDir, 'content.json'), JSON.stringify(data, null, 2));
writeFileSync(resolve(here, '../matsurfer/inc/data/content.json'), JSON.stringify(data, null, 1));
console.log('content.json written:', Object.keys(data.en).join(', '));
