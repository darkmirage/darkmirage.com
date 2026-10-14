// Bundles each entry in src/ into a plain browser script (IIFE) in the theme's assets/js/, which
// is what WordPress, lyrics and the gallery load. The output is committed: the server has no Node.
// `node build.mjs --check` builds to memory and fails if a committed script differs.
import { build } from 'esbuild';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('../blog/wp-content/themes/dmdune/assets/js/', import.meta.url));
const ENTRIES = ['chrome', 'dmdune', 'editor', 'timeline'];
const check = process.argv.includes('--check');

const result = await build({
  entryPoints: ENTRIES.map((e) => `src/${e}.ts`),
  outdir: OUT,
  bundle: true,
  format: 'iife',
  target: 'es2020',
  charset: 'utf8',
  legalComments: 'none',
  banner: { js: '/* Built from frontend/src by `npm run build`; edit the TypeScript there, not this file. */' },
  write: !check,
  logLevel: 'warning',
});

if (check) {
  const stale = result.outputFiles.filter((f) => {
    let old = '';
    try { old = readFileSync(f.path, 'utf8'); } catch { /* missing counts as stale */ }
    return old !== f.text;
  });
  if (stale.length) {
    console.error('Out of date (run `npm run build` in frontend/ and commit):\n  ' + stale.map((f) => f.path).join('\n  '));
    process.exit(1);
  }
  console.log('Built scripts match the TypeScript.');
}
